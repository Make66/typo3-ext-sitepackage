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
use TYPO3\CMS\Core\Information\Typo3Version;

/**
 * Migrates all "powermail_pi1" list plugins (CType "list" + list_type "powermail_pi1")
 * to the dedicated content element "Powermail" (CType "powermail_pi1"), as registered by
 * powermail 8+ in TYPO3 v11/v12.
 *
 * The FlexForm data (pi_flexform) stays untouched: powermail registers the very same
 * data structure (FlexformPi1.xml) for both the old and the new pointer combination.
 */
class Mig12Command extends Command
{
    private const TABLE = 'tt_content';
    private const OLD_CTYPE = 'list';
    private const LIST_TYPE = 'powermail_pi1';
    private const NEW_CTYPE = 'powermail_pi1';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly CacheManager $cacheManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Migrate all "powermail_pi1" plugins to the content element "Powermail" (TYPO3 v12 only)')
            ->setHelp(
                'Sets CType="powermail_pi1" and clears list_type on every tt_content record that still uses' . PHP_EOL
                . 'the generic plugin (CType="list", list_type="powermail_pi1").' . PHP_EOL
                . 'Hidden and soft-deleted records are migrated as well, so that restoring them from the' . PHP_EOL
                . 'recycler does not resurrect a broken plugin. Use --dry-run to see what would happen.'
            )
            ->addOption(
                'dry-run',
                'd',
                InputOption::VALUE_NONE,
                'Only show the records that would be migrated, do not write anything'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Migrate powermail_pi1 plugins to content element "Powermail"');

        $typo3Version = new Typo3Version();
        if ($typo3Version->getMajorVersion() !== 12) {
            $io->error(sprintf(
                'This migration is designed for TYPO3 v12.x only, but this installation runs TYPO3 %s.',
                $typo3Version->getVersion()
            ));

            return Command::FAILURE;
        }

        $dryRun = (bool)$input->getOption('dry-run');
        $records = $this->findRecordsToMigrate();

        if ($records === []) {
            $io->success(sprintf(
                'Nothing to do: no record left with CType="%s" and list_type="%s".',
                self::OLD_CTYPE,
                self::LIST_TYPE
            ));
            $io->writeln(sprintf(
                ' Already using CType="%s": %d record(s).',
                self::NEW_CTYPE,
                $this->countAlreadyMigrated()
            ));

            return Command::SUCCESS;
        }

        $deleted = count(array_filter($records, static fn(array $row): bool => (int)$row['deleted'] === 1));
        $io->writeln(sprintf(
            ' Found <info>%d</info> record(s) to migrate: %d live, %d deleted.',
            count($records),
            count($records) - $deleted,
            $deleted
        ));
        $io->writeln(sprintf(
            ' Already using CType="%s": %d record(s).',
            self::NEW_CTYPE,
            $this->countAlreadyMigrated()
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

        if ($dryRun) {
            $io->note('Dry run: no record has been changed.');

            return Command::SUCCESS;
        }

        $migrated = $this->migrate();
        $this->cacheManager->flushCachesInGroup('pages');

        $io->success(sprintf(
            'Migrated %d record(s) to CType="%s" and flushed the page caches.',
            $migrated,
            self::NEW_CTYPE
        ));

        return Command::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function findRecordsToMigrate(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder
            ->select('uid', 'pid', 'header', 'hidden', 'deleted', 'sys_language_uid')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq(
                    'CType',
                    $queryBuilder->createNamedParameter(self::OLD_CTYPE)
                ),
                $queryBuilder->expr()->eq(
                    'list_type',
                    $queryBuilder->createNamedParameter(self::LIST_TYPE)
                )
            )
            ->orderBy('pid')
            ->addOrderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    private function countAlreadyMigrated(): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();

        return (int)$queryBuilder
            ->count('uid')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq(
                    'CType',
                    $queryBuilder->createNamedParameter(self::NEW_CTYPE)
                )
            )
            ->executeQuery()
            ->fetchOne();
    }

    private function migrate(): int
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);

        return (int)$connection->update(
            self::TABLE,
            [
                'CType' => self::NEW_CTYPE,
                'list_type' => '',
            ],
            [
                'CType' => self::OLD_CTYPE,
                'list_type' => self::LIST_TYPE,
            ]
        );
    }
}
