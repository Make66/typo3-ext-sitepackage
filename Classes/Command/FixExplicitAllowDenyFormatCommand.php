<?php

declare(strict_types=1);

namespace Taketool\Sitepackage\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Migrates be_groups.explicit_allowdeny entries stored in TYPO3's old
 * "table:field:value:ALLOW" / "table:field:value:DENY" format to the plain
 * "table:field:value" form current TYPO3 versions actually read.
 *
 * The old 4-segment format dates back to when "explicitDeny" authMode still
 * existed. TYPO3's own UI (TcaItemsProcessorFunctions::populateExplicitAuthValues())
 * and its save-time integrity check (BackendUserGroupIntegrityCheck) both
 * compare against the plain 3-segment form, so any group still carrying the
 * old suffix silently fails every authMode match for that value -
 * BackendUserAuthentication::checkAuthMode() does a literal, comma-bounded
 * string match via GeneralUtility::inList(), and "table:field:value" is never
 * a bounded match inside "...,table:field:value:ALLOW,...". A CType allow-list
 * entry stops matching and the "Edit" option for that content type disappears
 * for every non-admin editor in the group - no error, nothing in the log.
 *
 * ":ALLOW" entries are safe to reformat (stripping the suffix keeps the same
 * meaning). ":DENY" entries are dropped instead of reformatted: modern
 * "explicitAllow" mode has no per-item deny, so stripping the suffix would
 * silently turn a historical DENY into an ALLOW - a permission escalation,
 * not a fix. Already-well-formed (3-segment) entries and anything else that
 * doesn't match the old pattern are left untouched, so this command is safe
 * to run repeatedly across any number of installations.
 */
class FixExplicitAllowDenyFormatCommand extends Command
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly CacheManager $cacheManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Migrate legacy "table:field:value:ALLOW/DENY" be_groups.explicit_allowdeny entries to the format TYPO3 actually reads')
            ->setHelp(
                'TYPO3 historically stored be_groups.explicit_allowdeny entries as' . PHP_EOL
                . '"table:field:value:ALLOW" or "table:field:value:DENY". Current TYPO3' . PHP_EOL
                . 'versions only understand the plain "table:field:value" form; the old' . PHP_EOL
                . 'suffix silently breaks every authMode match for that entry (e.g. a CType' . PHP_EOL
                . 'allow-list), so affected editors lose the ability to edit those records -' . PHP_EOL
                . 'no error, the "Edit" option just does not appear.' . PHP_EOL
                . PHP_EOL
                . 'ALLOW entries are reformatted (suffix stripped). DENY entries are dropped' . PHP_EOL
                . '(explicitAllow mode has no per-item deny anymore - keeping the value would' . PHP_EOL
                . 'turn a historical DENY into an ALLOW). Use --dry-run to preview changes.'
            )
            ->addOption(
                'dry-run',
                'd',
                InputOption::VALUE_NONE,
                'Only show the groups that would be migrated, do not write anything'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Migrate legacy be_groups.explicit_allowdeny format');

        $dryRun = (bool)$input->getOption('dry-run');
        $groups = $this->findNonEmptyGroups();

        $rows = [];
        $droppedDenyCount = 0;
        foreach ($groups as $group) {
            [$migrated, $droppedDeny] = $this->migrateValue($group['explicit_allowdeny']);
            if ($migrated === $group['explicit_allowdeny']) {
                continue;
            }
            $rows[] = [$group['uid'], $group['title'], $group['explicit_allowdeny'], $migrated];
            $droppedDenyCount += $droppedDeny;

            if (!$dryRun) {
                $this->connectionPool->getConnectionForTable('be_groups')->update(
                    'be_groups',
                    ['explicit_allowdeny' => $migrated],
                    ['uid' => (int)$group['uid']]
                );
            }
        }

        if ($rows === []) {
            $io->success('Nothing to do: no legacy ":ALLOW"/":DENY" suffixed entries found.');

            return Command::SUCCESS;
        }

        $io->writeln(sprintf(' Found <info>%d</info> group(s) with legacy entries.', count($rows)));
        if ($droppedDenyCount > 0) {
            $io->warning(sprintf(
                '%d ":DENY" entr%s dropped entirely (see command help for why they cannot be reformatted).',
                $droppedDenyCount,
                $droppedDenyCount === 1 ? 'y was' : 'ies were'
            ));
        }

        if ($dryRun || $output->isVerbose()) {
            $io->table(['uid', 'title', 'before', 'after'], $rows);
        }

        if ($dryRun) {
            $io->note('Dry run: no record has been changed.');

            return Command::SUCCESS;
        }

        $this->cacheManager->flushCachesInGroup('system');

        $io->success(sprintf('Migrated %d be_groups record(s).', count($rows)));

        return Command::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function findNonEmptyGroups(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_groups');
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder
            ->select('uid', 'title', 'explicit_allowdeny')
            ->from('be_groups')
            ->where(
                $queryBuilder->expr()->neq(
                    'explicit_allowdeny',
                    $queryBuilder->createNamedParameter('')
                )
            )
            ->orderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @return array{0: string, 1: int} [migrated value, count of dropped DENY entries]
     */
    private function migrateValue(string $explicitAllowDeny): array
    {
        $items = GeneralUtility::trimExplode(',', $explicitAllowDeny, true);
        $result = [];
        $droppedDeny = 0;
        foreach ($items as $item) {
            $parts = explode(':', $item);
            if (count($parts) === 4 && ($parts[3] === 'ALLOW' || $parts[3] === 'DENY')) {
                if ($parts[3] === 'ALLOW') {
                    $result[] = implode(':', array_slice($parts, 0, 3));
                } else {
                    $droppedDeny++;
                }
                continue;
            }
            $result[] = $item;
        }

        return [implode(',', $result), $droppedDeny];
    }
}
