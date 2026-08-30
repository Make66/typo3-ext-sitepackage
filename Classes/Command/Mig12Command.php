<?php

declare(strict_types=1);

namespace Taketool\Sitepackage\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Information\Typo3Version;

/**
 * Cleans up legacy TYPO3 v11 data that blocks (or was left broken by) the v12 migration.
 * Four independent steps, each selectable via its own flag. --all runs the first three;
 * --legacy-scss-constants is deliberately excluded from --all (see below) and must always
 * be selected explicitly:
 *
 * --content-types
 *   Migrates all "list" plugins (CType "list" + list_type in powermail_pi1/news_pi1/
 *   media2click_list) to their dedicated content element (CType = the list_type itself),
 *   as registered by powermail 8+, georgringer/news and amazing/media2click in TYPO3 v12.
 *   FlexForm data (pi_flexform) stays untouched: these extensions register the very same
 *   data structure for both the old and the new pointer combination.
 *
 * --category-dedup
 *   Removes duplicate sys_category_record_mm rows that block TYPO3 v12's composite
 *   primary key (uid_local, uid_foreign, tablenames, fieldname) from being added by
 *   database:updateschema.
 *
 * --powermail-marketing
 *   Replaces NULL with '' in tx_powermail_domain_model_mail's marketing_* columns, which
 *   otherwise makes database:updateschema fail widening them to TEXT DEFAULT '' NOT NULL.
 *
 * --legacy-scss-constants
 *   Every mandant still carries its old bootstrap_package color/typography config in
 *   sys_template.constants (a "plugin.bootstrap_package { settings.scss { ... } }" block,
 *   or the equivalent dotted-notation lines), left over from before the site's fileadmin
 *   SCSS was migrated to the current _variables.scss standard (see
 *   packages/sitepackage/Documentation/SCSS_migration.md). bk2k's CompileService injects
 *   those DB values as Sass variables before every compile, but _variables.scss's own plain
 *   assignments run immediately after and win — so for any key _variables.scss already
 *   defines, the DB value is provably dead. This step removes the DB block/lines only when
 *   *every* key found in them also exists in that mandant's _variables.scss; any row with
 *   even one key that's only ever defined in the DB is left untouched and reported instead,
 *   since removing it would silently change the compiled output for that one value (this is
 *   exactly what went wrong for `waschmaschinendoktor` in the sibling project's own
 *   migration history — a handful of "looked like a shared default" values that were never
 *   real Sass !default anywhere, only ever supplied by this DB block). NOT included in
 *   --all: unlike the other three, "safe" here depends on a mandant's current
 *   _variables.scss content, not just database state, so it warrants running (and
 *   reviewing the dry-run table) on its own.
 *
 * Use --dry-run to preview any of the first three steps without writing anything. Without
 * any step flag, --dry-run alone previews content-types/category-dedup/powermail-marketing
 * ("show me everything that needs doing"); without --dry-run either, the command refuses to
 * run and asks for an explicit flag, since this is meant to run against production
 * databases. --legacy-scss-constants always needs its own explicit flag, dry-run or not.
 */
class Mig12Command extends Command
{
    private const TABLE = 'tt_content';
    private const OLD_CTYPE = 'list';

    /**
     * list_type values registered via ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT;
     * the CType after migration equals the list_type value itself.
     *
     * @var string[]
     */
    private const PLUGIN_LIST_TYPES = ['powermail_pi1', 'news_pi1', 'media2click_list'];

    private const CATEGORY_MM_TABLE = 'sys_category_record_mm';

    private const POWERMAIL_TABLE = 'tx_powermail_domain_model_mail';

    /**
     * @var string[]
     */
    private const POWERMAIL_MARKETING_COLUMNS = [
        'marketing_referer_domain',
        'marketing_referer',
        'marketing_country',
        'marketing_browser_language',
        'marketing_page_funnel',
    ];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly CacheManager $cacheManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Clean up legacy TYPO3 v11 data blocking the v12 migration (TYPO3 v12 only)')
            ->setHelp(
                'Three independent cleanup steps, each selectable via its own flag:' . PHP_EOL
                . PHP_EOL
                . '  --content-types       Migrate CType="list" plugin records (powermail_pi1, news_pi1,' . PHP_EOL
                . '                        media2click_list) to their native CType. Hidden and soft-deleted' . PHP_EOL
                . '                        records are migrated as well, so restoring them from the recycler' . PHP_EOL
                . '                        does not resurrect a broken plugin.' . PHP_EOL
                . '  --category-dedup      Remove duplicate sys_category_record_mm rows blocking the' . PHP_EOL
                . '                        composite primary key TYPO3 v12 adds to that table.' . PHP_EOL
                . '  --powermail-marketing Replace NULL with \'\' in tx_powermail_domain_model_mail' . PHP_EOL
                . '                        marketing_* columns, which otherwise blocks widening them to' . PHP_EOL
                . '                        TEXT DEFAULT \'\' NOT NULL.' . PHP_EOL
                . '  --all                 Run the three steps above.' . PHP_EOL
                . '  --legacy-scss-constants' . PHP_EOL
                . '                        Remove each mandant\'s legacy' . PHP_EOL
                . '                        plugin.bootstrap_package.settings.scss constants from' . PHP_EOL
                . '                        sys_template.constants, but only where every key in them is' . PHP_EOL
                . '                        already covered by that mandant\'s fileadmin _variables.scss —' . PHP_EOL
                . '                        anything not fully covered is left alone and reported. Always' . PHP_EOL
                . '                        needs its own explicit flag: never implied by --all or by' . PHP_EOL
                . '                        --dry-run alone.' . PHP_EOL
                . PHP_EOL
                . 'Use --dry-run to preview --content-types/--category-dedup/--powermail-marketing' . PHP_EOL
                . '(and --legacy-scss-constants, if also given) without writing anything. With no step' . PHP_EOL
                . 'flag and no --dry-run, the command refuses to run and asks for an explicit flag. With' . PHP_EOL
                . 'no step flag but --dry-run given, it previews content-types/category-dedup/' . PHP_EOL
                . 'powermail-marketing.'
            )
            ->addOption(
                'content-types',
                null,
                InputOption::VALUE_NONE,
                'Migrate CType="list" plugin records (powermail_pi1, news_pi1, media2click_list) to their native CType'
            )
            ->addOption(
                'category-dedup',
                null,
                InputOption::VALUE_NONE,
                'Remove duplicate sys_category_record_mm rows blocking the composite primary key'
            )
            ->addOption(
                'powermail-marketing',
                null,
                InputOption::VALUE_NONE,
                'Replace NULL with \'\' in tx_powermail_domain_model_mail marketing_* columns'
            )
            ->addOption(
                'all',
                null,
                InputOption::VALUE_NONE,
                'Run --content-types, --category-dedup and --powermail-marketing'
            )
            ->addOption(
                'legacy-scss-constants',
                null,
                InputOption::VALUE_NONE,
                'Remove legacy plugin.bootstrap_package.settings.scss constants that are fully covered by the mandant\'s _variables.scss (never implied by --all)'
            )
            ->addOption(
                'dry-run',
                'd',
                InputOption::VALUE_NONE,
                'Only show what would be changed, do not write anything'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('TYPO3 v11 → v12 legacy data cleanup');

        $typo3Version = new Typo3Version();
        if ($typo3Version->getMajorVersion() !== 12) {
            $io->error(sprintf(
                'This migration is designed for TYPO3 v12.x only, but this installation runs TYPO3 %s.',
                $typo3Version->getVersion()
            ));

            return Command::FAILURE;
        }

        $dryRun = (bool)$input->getOption('dry-run');
        $runContentTypes = (bool)$input->getOption('content-types');
        $runCategoryDedup = (bool)$input->getOption('category-dedup');
        $runPowermailMarketing = (bool)$input->getOption('powermail-marketing');
        $runAll = (bool)$input->getOption('all');
        $runLegacyScssConstants = (bool)$input->getOption('legacy-scss-constants');
        $anySelected = $runContentTypes || $runCategoryDedup || $runPowermailMarketing || $runAll || $runLegacyScssConstants;

        if (!$anySelected && !$dryRun) {
            $io->error(
                'No cleanup step selected. Use --content-types, --category-dedup, --powermail-marketing, '
                . '--all, and/or --legacy-scss-constants (add --dry-run to preview a step without selecting it, '
                . 'except --legacy-scss-constants, which always needs its own explicit flag).'
            );

            return Command::FAILURE;
        }

        if (!$anySelected && $dryRun) {
            // "show me everything that needs doing" — safe because dry-run never writes.
            // --legacy-scss-constants stays opt-in even here: it needs its own explicit flag.
            $runAll = true;
        }

        if ($runAll) {
            $runContentTypes = true;
            $runCategoryDedup = true;
            $runPowermailMarketing = true;
        }

        $changed = 0;

        if ($runContentTypes) {
            $changed += $this->runContentTypeMigration($io, $output, $dryRun);
        }

        if ($runCategoryDedup) {
            $changed += $this->runCategoryDedup($io, $dryRun);
        }

        if ($runPowermailMarketing) {
            $changed += $this->runPowermailMarketingFix($io, $dryRun);
        }

        if ($runLegacyScssConstants) {
            $changed += $this->runLegacyScssConstantsCleanup($io, $dryRun);
        }

        if ($dryRun) {
            $io->note('Dry run: no record has been changed.');

            return Command::SUCCESS;
        }

        $io->success(sprintf('Cleanup finished (%d row(s)/cell(s) changed across the selected step(s)).', $changed));

        return Command::SUCCESS;
    }

    // -----------------------------------------------------------------
    // Step 1: CType="list" plugin migration
    // -----------------------------------------------------------------

    private function runContentTypeMigration(SymfonyStyle $io, OutputInterface $output, bool $dryRun): int
    {
        $io->section('Migrate CType="list" plugin records to their native CType');

        $totalMigrated = 0;
        $anyFound = false;

        foreach (self::PLUGIN_LIST_TYPES as $listType) {
            $records = $this->findRecordsToMigrate($listType);
            $alreadyMigrated = $this->countAlreadyMigrated($listType);

            if ($records === []) {
                $io->writeln(sprintf(
                    ' [%s] Nothing to do. Already using CType="%s": %d record(s).',
                    $listType,
                    $listType,
                    $alreadyMigrated
                ));

                continue;
            }

            $anyFound = true;
            $deleted = count(array_filter($records, static fn(array $row): bool => (int)$row['deleted'] === 1));
            $io->writeln(sprintf(
                ' [%s] Found <info>%d</info> record(s) to migrate: %d live, %d deleted. Already using CType="%s": %d record(s).',
                $listType,
                count($records),
                count($records) - $deleted,
                $deleted,
                $listType,
                $alreadyMigrated
            ));

            if ($dryRun || $output->isVerbose()) {
                $io->table(
                    ['uid', 'pid', 'sys_language_uid', 'hidden', 'deleted', 'header'],
                    array_map(static fn(array $row): array => [
                        $row['uid'],
                        $row['pid'],
                        $row['sys_language_uid'],
                        $row['hidden'],
                        $row['deleted'],
                        $row['header'],
                    ], $records)
                );
            }

            if (!$dryRun) {
                $totalMigrated += $this->migratePluginRecords($listType);
            }
        }

        if (!$dryRun) {
            if ($totalMigrated > 0) {
                $this->cacheManager->flushCachesInGroup('pages');
                $io->writeln(sprintf(' Migrated %d record(s) in total and flushed the page caches.', $totalMigrated));
            } else {
                $io->writeln(' Nothing to migrate for any plugin signature.');
            }
        } elseif (!$anyFound) {
            $io->writeln(' Nothing to migrate for any plugin signature.');
        }

        return $totalMigrated;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function findRecordsToMigrate(string $listType): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder
            ->select('uid', 'pid', 'header', 'hidden', 'deleted', 'sys_language_uid')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter(self::OLD_CTYPE)),
                $queryBuilder->expr()->eq('list_type', $queryBuilder->createNamedParameter($listType))
            )
            ->orderBy('pid')
            ->addOrderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    private function countAlreadyMigrated(string $listType): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();

        return (int)$queryBuilder
            ->count('uid')
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter($listType)))
            ->executeQuery()
            ->fetchOne();
    }

    private function migratePluginRecords(string $listType): int
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);

        return (int)$connection->update(
            self::TABLE,
            ['CType' => $listType, 'list_type' => ''],
            ['CType' => self::OLD_CTYPE, 'list_type' => $listType]
        );
    }

    // -----------------------------------------------------------------
    // Step 2: sys_category_record_mm deduplication
    // -----------------------------------------------------------------

    private function runCategoryDedup(SymfonyStyle $io, bool $dryRun): int
    {
        $io->section('Deduplicate sys_category_record_mm');

        $exactDuplicates = $this->findExactDuplicateCategoryRows();
        if ($exactDuplicates !== []) {
            $io->error(sprintf(
                'Found %d group(s) of rows that are fully identical across all 6 columns '
                . '(uid_local, uid_foreign, tablenames, fieldname, sorting, sorting_foreign). '
                . 'This is the one case the deduplication delete cannot safely disambiguate. '
                . 'Aborting without deleting anything — needs manual review.',
                count($exactDuplicates)
            ));
            $io->table(
                ['uid_local', 'uid_foreign', 'tablenames', 'fieldname', 'sorting', 'sorting_foreign', 'row_count'],
                $exactDuplicates
            );

            return 0;
        }

        $groups = $this->findDuplicateCategoryGroups();
        if ($groups === []) {
            $io->writeln(' Nothing to do: no duplicate (uid_local, uid_foreign, tablenames, fieldname) groups found.');

            return 0;
        }

        $extraRows = array_sum(array_map(static fn(array $row): int => (int)$row['row_count'] - 1, $groups));
        $io->writeln(sprintf(
            ' Found <info>%d</info> duplicate group(s), %d extra row(s) to remove.',
            count($groups),
            $extraRows
        ));

        if ($dryRun) {
            $io->table(
                ['uid_local', 'uid_foreign', 'tablenames', 'fieldname', 'row_count'],
                $groups
            );

            return 0;
        }

        $connection = $this->connectionPool->getConnectionForTable(self::CATEGORY_MM_TABLE);
        $connection->beginTransaction();

        try {
            // Defensive re-check against a TOCTOU gap between the check above and this real run.
            if ($this->findExactDuplicateCategoryRows() !== []) {
                $connection->rollBack();
                $io->error('Fully identical duplicate rows appeared since the last check. Aborting without deleting anything — needs manual review.');

                return 0;
            }

            $deleted = $this->deleteDuplicateCategoryRows();
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        $io->writeln(sprintf(' Deleted %d duplicate row(s).', $deleted));

        return $deleted;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function findDuplicateCategoryGroups(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::CATEGORY_MM_TABLE);
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder
            ->select('uid_local', 'uid_foreign', 'tablenames', 'fieldname')
            ->addSelectLiteral('COUNT(*) AS row_count')
            ->from(self::CATEGORY_MM_TABLE)
            ->groupBy('uid_local', 'uid_foreign', 'tablenames', 'fieldname')
            ->having('COUNT(*) > 1')
            ->orderBy('uid_local')
            ->addOrderBy('uid_foreign')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Rows identical across every column of the table — the one case the self-join delete
     * in deleteDuplicateCategoryRows() cannot safely tell apart (its ordering predicate is a
     * strict inequality on sorting/sorting_foreign, the only columns that ever differ between
     * true duplicates).
     *
     * @return array<int, array<string, mixed>>
     */
    private function findExactDuplicateCategoryRows(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::CATEGORY_MM_TABLE);
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder
            ->select('uid_local', 'uid_foreign', 'tablenames', 'fieldname', 'sorting', 'sorting_foreign')
            ->addSelectLiteral('COUNT(*) AS row_count')
            ->from(self::CATEGORY_MM_TABLE)
            ->groupBy('uid_local', 'uid_foreign', 'tablenames', 'fieldname', 'sorting', 'sorting_foreign')
            ->having('COUNT(*) > 1')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Keeps exactly one row per (uid_local, uid_foreign, tablenames, fieldname) group — the
     * row with the group-minimum (sorting_foreign, sorting) — and deletes the rest. The join
     * predicate is a strict inequality, so the minimum row never matches its own delete
     * condition against any sibling and is always kept, exactly once, without needing window
     * functions (portable to MySQL 5.6+/MariaDB 10.0+).
     */
    private function deleteDuplicateCategoryRows(): int
    {
        $connection = $this->connectionPool->getConnectionForTable(self::CATEGORY_MM_TABLE);
        $tableName = self::CATEGORY_MM_TABLE;

        return (int)$connection->executeStatement(
            "DELETE m1 FROM {$tableName} m1
            INNER JOIN {$tableName} m2
              ON  m1.uid_local   = m2.uid_local
              AND m1.uid_foreign = m2.uid_foreign
              AND m1.tablenames  = m2.tablenames
              AND m1.fieldname   = m2.fieldname
              AND (
                   m2.sorting_foreign <  m1.sorting_foreign
                OR (m2.sorting_foreign = m1.sorting_foreign AND m2.sorting < m1.sorting)
              )"
        );
    }

    // -----------------------------------------------------------------
    // Step 3: powermail marketing_* NULL fix
    // -----------------------------------------------------------------

    private function runPowermailMarketingFix(SymfonyStyle $io, bool $dryRun): int
    {
        $io->section('Fix NULL values in tx_powermail_domain_model_mail marketing_* columns');

        $totalFixed = 0;
        $anyNull = false;

        foreach (self::POWERMAIL_MARKETING_COLUMNS as $column) {
            $nullCount = $this->countNullMarketing($column);

            if ($nullCount === 0) {
                $io->writeln(sprintf(' [%s] Nothing to do: no NULL values.', $column));

                continue;
            }

            $anyNull = true;
            $io->writeln(sprintf(' [%s] Found <info>%d</info> NULL row(s).', $column, $nullCount));

            if (!$dryRun) {
                $totalFixed += $this->fixNullMarketing($column);
            }
        }

        if ($dryRun) {
            if (!$anyNull) {
                $io->writeln(' Nothing to do for any column.');
            }
        } elseif ($totalFixed > 0) {
            $io->writeln(sprintf(' Replaced NULL with \'\' in %d cell(s) in total.', $totalFixed));
        } else {
            $io->writeln(' Nothing to do for any column.');
        }

        return $totalFixed;
    }

    private function countNullMarketing(string $column): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::POWERMAIL_TABLE);
        $queryBuilder->getRestrictions()->removeAll();

        return (int)$queryBuilder
            ->count('uid')
            ->from(self::POWERMAIL_TABLE)
            ->where($queryBuilder->expr()->isNull($column))
            ->executeQuery()
            ->fetchOne();
    }

    private function fixNullMarketing(string $column): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::POWERMAIL_TABLE);
        $queryBuilder->getRestrictions()->removeAll();

        return (int)$queryBuilder
            ->update(self::POWERMAIL_TABLE)
            ->set($column, '')
            ->where($queryBuilder->expr()->isNull($column))
            ->executeStatement();
    }

    // -----------------------------------------------------------------
    // Step 4: retire fully-covered legacy plugin.bootstrap_package.settings.scss
    // sys_template.constants blocks (NOT part of --all — see class docblock)
    // -----------------------------------------------------------------

    private function runLegacyScssConstantsCleanup(SymfonyStyle $io, bool $dryRun): int
    {
        $io->section('Retire fully-covered legacy plugin.bootstrap_package.settings.scss constants');

        $rows = $this->findSysTemplateRowsWithScssConstants();
        if ($rows === []) {
            $io->writeln(' Nothing to do: no sys_template row references plugin.bootstrap_package.');

            return 0;
        }

        $removed = 0;
        $tableRows = [];

        foreach ($rows as $row) {
            $uid = (int)$row['uid'];
            $constants = (string)$row['constants'];
            $dbKeys = $this->extractLegacyScssConstantKeys($constants);

            if ($dbKeys === []) {
                // "plugin.bootstrap_package" appears (e.g. in a comment) but not as a real
                // settings.scss block/dotted line — nothing this step is responsible for.
                continue;
            }

            $mandant = $this->extractMandantName($constants);
            if ($mandant === null) {
                $tableRows[] = [$uid, '?', 'skip', 'no top-level "mandant" constant found'];

                continue;
            }

            $variablesScssPath = $this->getVariablesScssPath($mandant);
            if (!is_file($variablesScssPath)) {
                $tableRows[] = [$uid, $mandant, 'skip', 'mandant not yet migrated (_variables.scss missing)'];

                continue;
            }

            $fileKeys = $this->parseVariablesScssKeys($variablesScssPath);
            $missingKeys = array_diff(array_keys($dbKeys), array_keys($fileKeys));

            if ($missingKeys !== []) {
                $tableRows[] = [
                    $uid,
                    $mandant,
                    'UNSAFE',
                    'missing from _variables.scss: ' . implode(', ', $missingKeys),
                ];

                continue;
            }

            if ($dryRun) {
                $tableRows[] = [
                    $uid,
                    $mandant,
                    'safe to remove',
                    sprintf('%d key(s), all covered by _variables.scss', count($dbKeys)),
                ];

                continue;
            }

            // Read fresh immediately before writing and re-check, in case the record (or the
            // mandant's _variables.scss) changed since the scan above started.
            $freshConstants = $this->fetchConstantsFresh($uid);
            if ($freshConstants === null) {
                $tableRows[] = [$uid, $mandant, 'skip', 'record disappeared before write'];

                continue;
            }

            $freshDbKeys = $this->extractLegacyScssConstantKeys($freshConstants);
            $freshMissingKeys = array_diff(array_keys($freshDbKeys), array_keys($fileKeys));
            if ($freshDbKeys === [] || $freshMissingKeys !== []) {
                $tableRows[] = [$uid, $mandant, 'skip', 'constants changed since scan — re-run to re-check'];

                continue;
            }

            $newConstants = $this->removeLegacyScssConstants($freshConstants);
            $this->writeConstants($uid, $newConstants);
            $removed++;
            $tableRows[] = [$uid, $mandant, 'removed', sprintf('%d key(s)', count($freshDbKeys))];
        }

        if ($tableRows !== []) {
            $io->table(['sys_template.uid', 'mandant', 'status', 'detail'], $tableRows);
        }

        if ($dryRun) {
            $io->writeln(' Dry run: nothing written. Rows marked "safe to remove" would have their legacy scss constants block stripped.');
        } else {
            $io->writeln(sprintf(' Removed the legacy scss constants block from %d record(s).', $removed));
        }

        return $removed;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function findSysTemplateRowsWithScssConstants(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_template');
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder
            ->select('uid', 'pid', 'constants')
            ->from('sys_template')
            ->where(
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0)),
                $queryBuilder->expr()->like(
                    'constants',
                    $queryBuilder->createNamedParameter('%plugin.bootstrap_package%')
                )
            )
            ->orderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    private function fetchConstantsFresh(int $uid): ?string
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_template');
        $queryBuilder->getRestrictions()->removeAll();

        $value = $queryBuilder
            ->select('constants')
            ->from('sys_template')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid)))
            ->executeQuery()
            ->fetchOne();

        return $value === false ? null : (string)$value;
    }

    private function writeConstants(int $uid, string $constants): void
    {
        $connection = $this->connectionPool->getConnectionForTable('sys_template');
        $connection->update(
            'sys_template',
            ['constants' => $constants],
            ['uid' => $uid]
        );
    }

    private function extractMandantName(string $constants): ?string
    {
        if (preg_match('/^mandant\s*=\s*(\S+)/m', $constants, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function getVariablesScssPath(string $mandant): string
    {
        return Environment::getPublicPath() . '/fileadmin/templates/' . $mandant . '/_variables.scss';
    }

    /**
     * @return array<string, string> key => raw value text (trimmed)
     */
    private function parseVariablesScssKeys(string $filePath): array
    {
        $content = (string)file_get_contents($filePath);
        $keys = [];

        if (preg_match_all('/^\$([a-zA-Z0-9_-]+)\s*:\s*([^;]*);/m', $content, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $keys[$match[1]] = trim($match[2]);
            }
        }

        return $keys;
    }

    /**
     * Extracts every key defined either inside the nested
     * plugin.bootstrap_package { settings.scss { ... } } block or via the equivalent
     * dotted-notation form (plugin.bootstrap_package.settings.scss.<key> = ...), which
     * TYPO3's TypoScript parser treats identically once merged into the constants array —
     * both are exactly what bk2k's CompileService::getVariablesFromConstants() injects as
     * Sass variables before every theme.scss/global.scss/custom.scss compile. Excludes the
     * "mandant" key itself, which is TypoScript path-substitution metadata, not a Sass
     * variable consumed by any SCSS file.
     *
     * @return array<string, string> key => raw value text (trimmed)
     */
    private function extractLegacyScssConstantKeys(string $constants): array
    {
        $keys = [];

        $blockSpan = $this->findBootstrapPackageBlockSpan($constants);
        if ($blockSpan !== null) {
            [$start, $end] = $blockSpan;
            $blockText = substr($constants, $start, $end - $start);

            if (preg_match('/settings\.scss\s*\{(.*)\}\s*\}\s*$/s', $blockText, $inner) === 1) {
                foreach (explode("\n", $inner[1]) as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#')) {
                        continue;
                    }
                    if (preg_match('/^([a-zA-Z0-9_-]+)\s*=\s*(.*)$/', $line, $match) === 1) {
                        $key = $match[1];
                        if ($key !== 'mandant') {
                            $keys[$key] = trim($match[2]);
                        }
                    }
                }
            }
        }

        if (preg_match_all(
            '/^plugin\.bootstrap_package\.settings\.scss\.([a-zA-Z0-9_-]+)\s*=\s*(.*)$/m',
            $constants,
            $dottedMatches,
            PREG_SET_ORDER
        ) !== false) {
            foreach ($dottedMatches as $match) {
                $key = $match[1];
                if ($key !== 'mandant') {
                    $keys[$key] = trim($match[2]);
                }
            }
        }

        return $keys;
    }

    /**
     * Finds the byte offset span [start, end) of the full
     * "plugin.bootstrap_package { ... }" block using brace-depth counting, not a
     * fixed-whitespace regex — indentation varies between mandants and a lazy/greedy regex
     * would either under- or over-match. The search pattern requires "plugin.bootstrap_package"
     * to be directly followed (only whitespace) by "{", so it never matches the unrelated
     * dotted-notation override lines. Returns null if no such block is found.
     *
     * @return array{0: int, 1: int}|null
     */
    private function findBootstrapPackageBlockSpan(string $constants): ?array
    {
        if (preg_match('/plugin\.bootstrap_package\s*\{/', $constants, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $markerPos = $matches[0][1];
        $bracePos = $markerPos + strlen($matches[0][0]) - 1;

        $depth = 0;
        $length = strlen($constants);
        for ($i = $bracePos; $i < $length; $i++) {
            if ($constants[$i] === '{') {
                $depth++;
            } elseif ($constants[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return [$markerPos, $i + 1];
                }
            }
        }

        return null;
    }

    /**
     * Strips the plugin.bootstrap_package { settings.scss { ... } } block (whole span, found
     * the same brace-balanced way as extraction) and every standalone
     * plugin.bootstrap_package.settings.scss.<key> = ... line. Leaves everything else in the
     * field — page.logo.*, page.theme.*, plugin.tx_cookieconsent.settings, etc. — untouched.
     */
    private function removeLegacyScssConstants(string $constants): string
    {
        $blockSpan = $this->findBootstrapPackageBlockSpan($constants);
        if ($blockSpan !== null) {
            [$start, $end] = $blockSpan;
            // Also swallow one trailing newline so removal doesn't leave a stray blank line.
            if ($end < strlen($constants) && $constants[$end] === "\n") {
                $end++;
            }
            $constants = substr($constants, 0, $start) . substr($constants, $end);
        }

        return (string)preg_replace(
            '/^plugin\.bootstrap_package\.settings\.scss\.[a-zA-Z0-9_-]+\s*=.*\R?/m',
            '',
            $constants
        );
    }
}
