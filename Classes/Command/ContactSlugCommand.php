<?php

declare(strict_types=1);

namespace Taketool\Sitepackage\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\Model\RecordStateFactory;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Fills empty tx_sitepackage_domain_model_contact.slug values, see
 * Documentation/RouteEnhancers.md.
 */
class ContactSlugCommand extends Command
{
    private const TABLE = 'tx_sitepackage_domain_model_contact';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Generate URL slugs for contact records that have none (needed by Configuration/Routes/Contact.yaml)')
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Only show what would be written');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool)$input->getOption('dry-run');

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $rows = $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq('slug', $queryBuilder->createNamedParameter('')),
                    $queryBuilder->expr()->isNull('slug'),
                ),
            )
            ->orderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();

        if ($rows === []) {
            $io->success('All contacts already have a slug.');
            return Command::SUCCESS;
        }

        $slugHelper = GeneralUtility::makeInstance(
            SlugHelper::class,
            self::TABLE,
            'slug',
            $GLOBALS['TCA'][self::TABLE]['columns']['slug']['config']
        );
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);

        foreach ($rows as $row) {
            $pid = (int)$row['pid'];
            $slug = $slugHelper->generate($row, $pid);
            $state = RecordStateFactory::forName(self::TABLE)->fromArray($row, $pid, (int)$row['uid']);
            $slug = $slugHelper->buildSlugForUniqueInPid($slug, $state);
            $io->writeln(sprintf('%d: %s %s -> %s', $row['uid'], $row['first_name'], $row['last_name'], $slug));
            if (!$dryRun) {
                $connection->update(self::TABLE, ['slug' => $slug], ['uid' => (int)$row['uid']]);
            }
        }

        $io->success(sprintf('%s %d slug(s).', $dryRun ? 'Would write' : 'Wrote', count($rows)));
        return Command::SUCCESS;
    }
}
