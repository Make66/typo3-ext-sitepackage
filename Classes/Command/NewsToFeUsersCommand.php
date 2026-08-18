<?php

declare(strict_types=1);

namespace Taketool\Sitepackage\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * One-off conversion of georgringer/news records into fe_users records within
 * the same pid - written for the Hochschullehre tenant's "Mitglieder" sysfolder
 * (pid 2111), whose 100 members were originally entered as news records instead
 * of fe_users, which is what taketool/members (see Documentation/Setup.md there)
 * actually expects to find in that storage folder.
 *
 * Field mapping: news "Header" (title column) -> fe_users first_name/last_name
 * (split on the last whitespace, so multi-word first names like "Amir Madany
 * Mamlouk" stay together), news teaser -> fe_users tx_members_institut, and the
 * news fal_media FAL relation -> a new fe_users "image" FAL relation pointing at
 * the same physical sys_file (the file itself is not duplicated, only the
 * sys_file_reference row). Every created user gets username/email
 * "firstname.lastname@<domain>" and the fixed password "Test123!" (hashed via
 * the FE password hashing service) as placeholder login credentials.
 *
 * Idempotent: a news record is skipped if a fe_users row with its generated
 * username already exists in the target pid, so this command can be re-run
 * safely (e.g. after fixing a subset of source data) without creating
 * duplicates. See Documentation/NewsToFeUsersImport.md for the prompt this was
 * built from and usage notes.
 */
class NewsToFeUsersCommand extends Command
{
    private const NEWS_TABLE = 'tx_news_domain_model_news';
    private const FE_USERS_TABLE = 'fe_users';
    private const SYS_FILE_REFERENCE_TABLE = 'sys_file_reference';
    private const NEWS_MEDIA_FIELD = 'fal_media';
    private const FE_USERS_IMAGE_FIELD = 'image';
    private const DUMMY_PASSWORD = 'Test123!';

    private const UMLAUT_MAP = [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
        'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
    ];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly PasswordHashFactory $passwordHashFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Convert news records in a pid into fe_users records in a pid (built for the Hochschullehre "Mitglieder" folder, pid 2111)')
            ->setHelp(
                'Reads every non-deleted tx_news_domain_model_news record from --source-pid and creates' . PHP_EOL
                . 'a matching fe_users record in --target-pid: news "Header" is split into first_name/last_name' . PHP_EOL
                . '(last word = last name), teaser becomes tx_members_institut, and the fal_media image (if any)' . PHP_EOL
                . 'is copied over as the fe_users "image" FAL relation. news hidden becomes fe_users disable.' . PHP_EOL
                . PHP_EOL
                . 'Every created user gets a placeholder username/email "firstname.lastname@<domain>" and the' . PHP_EOL
                . 'fixed password "Test123!" - there is no real email address on the source news records.' . PHP_EOL
                . PHP_EOL
                . 'Safe to re-run: a news record is skipped if a fe_users row with its generated username' . PHP_EOL
                . 'already exists in --target-pid. Use --dry-run to preview before writing anything.'
            )
            ->addOption('source-pid', null, InputOption::VALUE_REQUIRED, 'pid to read news records from', '2111')
            ->addOption('target-pid', null, InputOption::VALUE_REQUIRED, 'pid to create fe_users records in', '2111')
            ->addOption('email-domain', null, InputOption::VALUE_REQUIRED, 'domain used for the placeholder username/email', 'example.com')
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Only show what would be created, do not write anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Convert news records to fe_users records');

        $sourcePid = (int)$input->getOption('source-pid');
        $targetPid = (int)$input->getOption('target-pid');
        $emailDomain = (string)$input->getOption('email-domain');
        $dryRun = (bool)$input->getOption('dry-run');

        $newsRecords = $this->findNewsRecords($sourcePid);
        if ($newsRecords === []) {
            $io->success(sprintf('Nothing to do: no news records found in pid %d.', $sourcePid));

            return Command::SUCCESS;
        }

        $existingUsernames = $this->findExistingUsernames($targetPid);
        $usedUsernames = $existingUsernames;

        $rows = [];
        $created = 0;
        $skipped = 0;
        $hashedPassword = $this->passwordHashFactory->getDefaultHashInstance('FE')->getHashedPassword(self::DUMMY_PASSWORD);

        foreach ($newsRecords as $news) {
            [$firstName, $lastName] = $this->splitName((string)$news['title']);
            $baseUsername = $this->slugify($firstName) . '.' . $this->slugify($lastName) . '@' . $emailDomain;

            if (in_array($baseUsername, $existingUsernames, true)) {
                $rows[] = [$news['uid'], $firstName, $lastName, $baseUsername, '-', '-', 'skipped (exists)'];
                $skipped++;
                continue;
            }

            $username = $this->buildUniqueUsername($baseUsername, $usedUsernames);
            $usedUsernames[] = $username;
            $mediaCount = $this->countMedia((int)$news['uid']);
            $rows[] = [
                $news['uid'],
                $firstName,
                $lastName,
                $username,
                (string)$news['teaser'],
                (int)$news['hidden'] === 1 ? 'yes' : 'no',
                $mediaCount > 0 ? 'yes' : 'no',
            ];

            if (!$dryRun) {
                $feUserUid = $this->createFeUser(
                    $targetPid,
                    $firstName,
                    $lastName,
                    $username,
                    $hashedPassword,
                    (string)$news['teaser'],
                    (int)$news['hidden'],
                    $mediaCount
                );
                $this->copyMedia((int)$news['uid'], $feUserUid, $targetPid);
            }
            $created++;
        }

        $io->writeln(sprintf(
            ' Found <info>%d</info> news record(s) in pid %d: %d to create, %d already migrated.',
            count($newsRecords),
            $sourcePid,
            $created,
            $skipped
        ));

        if ($dryRun || $output->isVerbose()) {
            $io->table(['news uid', 'first_name', 'last_name', 'username', 'institut', 'hidden', 'has image'], $rows);
        }

        if ($dryRun) {
            $io->note('Dry run: no record has been changed.');

            return Command::SUCCESS;
        }

        $io->success(sprintf(
            'Created %d fe_users record(s) in pid %d (%d skipped, already present).',
            $created,
            $targetPid,
            $skipped
        ));

        return Command::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function findNewsRecords(int $pid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::NEWS_TABLE);
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder
            ->select('uid', 'title', 'teaser', 'hidden')
            ->from(self::NEWS_TABLE)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT))
            )
            ->orderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @return array<int, string>
     */
    private function findExistingUsernames(int $pid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::FE_USERS_TABLE);
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder
            ->select('username')
            ->from(self::FE_USERS_TABLE)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, \PDO::PARAM_INT))
            )
            ->executeQuery()
            ->fetchFirstColumn();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $title): array
    {
        $parts = preg_split('/\s+/', trim($title)) ?: [];
        if (count($parts) <= 1) {
            return [$title, ''];
        }

        $lastName = array_pop($parts);

        return [implode(' ', $parts), $lastName];
    }

    /**
     * @param array<int, string> $usedUsernames
     */
    private function buildUniqueUsername(string $baseUsername, array $usedUsernames): string
    {
        [$localPart, $domain] = explode('@', $baseUsername, 2);
        $username = $baseUsername;

        $suffix = 2;
        while (in_array($username, $usedUsernames, true)) {
            $username = $localPart . $suffix . '@' . $domain;
            $suffix++;
        }

        return $username;
    }

    private function slugify(string $value): string
    {
        $value = strtr($value, self::UMLAUT_MAP);
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT', $value);
        if ($transliterated !== false) {
            $value = $transliterated;
        }
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

        return trim($value, '-');
    }

    private function countMedia(int $newsUid): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::SYS_FILE_REFERENCE_TABLE);
        $queryBuilder->getRestrictions()->removeAll();

        return (int)$queryBuilder
            ->count('uid')
            ->from(self::SYS_FILE_REFERENCE_TABLE)
            ->where(
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter(self::NEWS_TABLE)),
                $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter(self::NEWS_MEDIA_FIELD)),
                $queryBuilder->expr()->eq('uid_foreign', $queryBuilder->createNamedParameter($newsUid, \PDO::PARAM_INT))
            )
            ->executeQuery()
            ->fetchOne();
    }

    private function createFeUser(
        int $pid,
        string $firstName,
        string $lastName,
        string $username,
        string $hashedPassword,
        string $institut,
        int $hidden,
        int $mediaCount
    ): int {
        $connection = $this->connectionPool->getConnectionForTable(self::FE_USERS_TABLE);
        $now = time();

        $connection->insert(self::FE_USERS_TABLE, [
            'pid' => $pid,
            'tstamp' => $now,
            'crdate' => $now,
            'disable' => $hidden,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => trim($firstName . ' ' . $lastName),
            'username' => $username,
            'password' => $hashedPassword,
            'email' => $username,
            'tx_members_institut' => trim($institut),
            // Extbase's DataMapper gates FAL relation fetching on this column being
            // non-empty (see fetchRelatedEager()) - it does NOT rely purely on the
            // sys_file_reference rows existing, unlike TYPO3 core's own FAL usage.
            // Must be kept in sync with the sys_file_reference rows created below.
            'image' => $mediaCount > 0 ? (string)$mediaCount : '',
        ]);

        return (int)$connection->lastInsertId(self::FE_USERS_TABLE);
    }

    private function copyMedia(int $newsUid, int $feUserUid, int $pid): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::SYS_FILE_REFERENCE_TABLE);
        $queryBuilder->getRestrictions()->removeAll();

        $mediaReferences = $queryBuilder
            ->select('uid_local', 'table_local')
            ->from(self::SYS_FILE_REFERENCE_TABLE)
            ->where(
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter(self::NEWS_TABLE)),
                $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter(self::NEWS_MEDIA_FIELD)),
                $queryBuilder->expr()->eq('uid_foreign', $queryBuilder->createNamedParameter($newsUid, \PDO::PARAM_INT))
            )
            ->orderBy('sorting_foreign')
            ->executeQuery()
            ->fetchAllAssociative();

        if ($mediaReferences === []) {
            return;
        }

        $connection = $this->connectionPool->getConnectionForTable(self::SYS_FILE_REFERENCE_TABLE);
        $now = time();
        $sorting = 1;

        foreach ($mediaReferences as $reference) {
            $connection->insert(self::SYS_FILE_REFERENCE_TABLE, [
                'pid' => $pid,
                'tstamp' => $now,
                'crdate' => $now,
                'uid_local' => (int)$reference['uid_local'],
                'uid_foreign' => $feUserUid,
                'tablenames' => self::FE_USERS_TABLE,
                'fieldname' => self::FE_USERS_IMAGE_FIELD,
                'table_local' => $reference['table_local'],
                'sorting_foreign' => $sorting,
            ]);
            $sorting++;
        }
    }
}
