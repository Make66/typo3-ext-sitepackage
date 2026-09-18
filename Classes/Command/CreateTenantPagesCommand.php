<?php

declare(strict_types=1);

namespace Taketool\Sitepackage\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Creates (or reuses) a tree of `pages` records from a JSON plan, via
 * DataHandler - never raw SQL, same rule `html2typo3`'s
 * ImportContentElementsCommand follows for tt_content/sys_file. Built for the
 * `newTenant` skill's two page-creation moments: the initial root+404
 * bootstrap for a brand-new tenant, and the later bulk creation of a mapped
 * remote page tree once the user has confirmed it - both are just a tree of
 * page nodes, so one command covers both.
 *
 * Idempotent by (parent pid, slug-or-title): a node whose plan entry matches
 * an already-existing, non-deleted sibling under the same parent is reused
 * rather than duplicated, the same "re-running only creates what's missing"
 * property `infodienste:seed:calden` has for its own domain tables. This
 * matters here specifically because the newTenant workflow re-runs this
 * command after every round of user confirmation - a page a previous run
 * already created (or that existed beforehand) must never be recreated.
 *
 * Plan JSON shape:
 * {
 *   "pages": [
 *     {
 *       "title": "musterstadt.orts.page",
 *       "pid": 0,                 // required on a top-level node: an existing pid to attach under (0 for a new site root)
 *       "slug": "/",              // optional; omit to let TYPO3 generate one from the title
 *       "isSiteroot": true,
 *       "hidden": false,
 *       "navHide": false,
 *       "doktype": 1,
 *       "children": [
 *         {"title": "404", "slug": "/404", "navHide": true}
 *       ]
 *     }
 *   ]
 * }
 *
 * A nested `children` entry never needs its own "pid" - its parent is
 * whichever node it's nested under, whether that parent was just created or
 * already existed. `slug`/`title` matching for the existing-sibling check
 * only ever looks at direct children of the resolved parent pid, matching
 * how TYPO3 itself scopes slug uniqueness.
 */
final class CreateTenantPagesCommand extends Command
{
    private const TABLE = 'pages';

    protected function configure(): void
    {
        $this
            ->setDescription('Creates (or reuses) a tree of pages records from a JSON plan - see class docblock for the plan shape.')
            ->addOption('json', null, InputOption::VALUE_REQUIRED, 'Path to the page-tree plan JSON file.')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Do not ask for confirmation before writing.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be created/reused without writing anything.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Sitepackage — tenant page tree');

        Bootstrap::initializeBackendAuthentication();

        $jsonPath = (string)$input->getOption('json');
        if ($jsonPath === '' || !is_file($jsonPath)) {
            $io->error('No such plan file: ' . $jsonPath);
            return Command::FAILURE;
        }

        $plan = json_decode((string)file_get_contents($jsonPath), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($plan['pages'] ?? null)) {
            $io->error('Plan JSON must have a top-level "pages" array.');
            return Command::FAILURE;
        }

        $dryRun = (bool)$input->getOption('dry-run');
        $report = [];
        $data = [];
        $tempIdCounter = 0;

        foreach ($plan['pages'] as $node) {
            $this->planNode($node, null, $report, $data, $tempIdCounter);
        }

        $rows = array_map(
            static fn(array $r): array => [$r['status'], $r['title'], $r['slug'] ?? '(auto)', $r['pid']],
            $report
        );
        $io->table(['Action', 'Title', 'Slug', 'Parent pid'], $rows);

        $toCreate = array_filter($report, static fn(array $r): bool => $r['status'] === 'create');
        if ($toCreate === []) {
            $io->success('Nothing to create — every page in the plan already exists.');
            $this->printJsonResult($io, $report);
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $io->note('Dry run — no records were created.');
            return Command::SUCCESS;
        }

        if (!(bool)$input->getOption('yes')
            && !$io->confirm(sprintf('Create %d new page(s)?', count($toCreate)))
        ) {
            $io->warning('Aborted.');
            return Command::SUCCESS;
        }

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, []);
        $dataHandler->process_datamap();

        if ($dataHandler->errorLog !== []) {
            foreach ($dataHandler->errorLog as $error) {
                $io->error($error);
            }
            return Command::FAILURE;
        }

        foreach ($report as &$r) {
            if ($r['status'] === 'create' && isset($dataHandler->substNEWwithIDs[$r['tempId']])) {
                $r['uid'] = (int)$dataHandler->substNEWwithIDs[$r['tempId']];
                $r['status'] = 'created';
            }
        }
        unset($r);

        $io->success(sprintf('Created %d page(s).', count($toCreate)));
        $this->printJsonResult($io, $report);

        return Command::SUCCESS;
    }

    /**
     * Recursively turns one plan node (and its children) into report rows +
     * DataHandler datamap entries, resolving each node's parent pid - either
     * the given `$parentPid` (an already-known, real pid) or, once this
     * node's own parent was itself newly created earlier in this same call,
     * that parent's "NEW_..." temp id. DataHandler resolves a "NEW_..."
     * placeholder used as `pid` against its own `substNEWwithIDs` map as long
     * as the parent's data-map entry was added before the child's - so
     * parents are always planned (and thus appended to `$data`) before their
     * children below.
     *
     * @param array<string, mixed> $node
     * @param array<int, array<string, mixed>> $report
     * @param array<string, array<string, mixed>> $data
     */
    private function planNode(array $node, int|string|null $parentPid, array &$report, array &$data, int &$tempIdCounter): void
    {
        $pid = $parentPid ?? ($node['pid'] ?? null);
        if ($pid === null) {
            throw new \RuntimeException('Top-level plan node "' . ($node['title'] ?? '?') . '" needs an explicit "pid".', 1758200000);
        }

        $title = (string)$node['title'];
        $slug = $node['slug'] ?? null;
        $existingUid = is_int($pid) ? $this->findExistingSibling($pid, $slug, $title) : null;

        if ($existingUid !== null) {
            $hasContent = $this->pageHasContent($existingUid);
            $report[] = [
                'status' => $hasContent ? 'existing-has-content' : 'existing-empty',
                'title' => $title,
                'slug' => $slug,
                'pid' => $pid,
                'uid' => $existingUid,
            ];
            $ownPidForChildren = $existingUid;
        } else {
            $tempId = 'NEW_page_' . $tempIdCounter++;
            $data[self::TABLE][$tempId] = array_filter([
                'pid' => $pid,
                'title' => $title,
                'slug' => $slug,
                'is_siteroot' => !empty($node['isSiteroot']) ? 1 : 0,
                'hidden' => !empty($node['hidden']) ? 1 : 0,
                'nav_hide' => !empty($node['navHide']) ? 1 : 0,
                'doktype' => (int)($node['doktype'] ?? 1),
            ], static fn($v): bool => $v !== null);

            $report[] = [
                'status' => 'create',
                'title' => $title,
                'slug' => $slug,
                'pid' => $pid,
                'tempId' => $tempId,
            ];
            $ownPidForChildren = $tempId;
        }

        foreach ($node['children'] ?? [] as $child) {
            $this->planNode($child, $ownPidForChildren, $report, $data, $tempIdCounter);
        }
    }

    /**
     * Always requires a title match, and additionally a slug match when the
     * plan gives one. Title alone would be too loose for an ordinary nested
     * page (two different pages can share a title), but slug alone is
     * actually wrong for a site-root node: every site root in a multi-site
     * install lives at `pid = 0` with the same `slug = '/'` (confirmed live
     * in this install - both the "calden" and "main" site roots share it),
     * so matching on slug there would false-positive-match a completely
     * unrelated tenant's root page. ANDing both conditions together is
     * correct for both cases without needing to special-case `isSiteroot`.
     */
    private function findExistingSibling(int $pid, ?string $slug, string $title): ?int
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $queryBuilder->select('uid')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('title', $queryBuilder->createNamedParameter($title))
            );

        if ($slug !== null) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('slug', $queryBuilder->createNamedParameter($slug)));
        }

        $uid = $queryBuilder->executeQuery()->fetchOne();

        return $uid !== false ? (int)$uid : null;
    }

    private function pageHasContent(int $pid): bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $count = $queryBuilder->count('uid')
            ->from('tt_content')
            ->where($queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, ParameterType::INTEGER)))
            ->executeQuery()
            ->fetchOne();

        return (int)$count > 0;
    }

    /**
     * @param array<int, array<string, mixed>> $report
     */
    private function printJsonResult(SymfonyStyle $io, array $report): void
    {
        $result = array_map(static function (array $r): array {
            unset($r['tempId']);
            return $r;
        }, $report);

        $io->writeln('RESULT_JSON:' . json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
