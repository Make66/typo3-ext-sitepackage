<?php

declare(strict_types=1);

namespace Taketool\Sitepackage\EventListener;

use Doctrine\DBAL\ArrayParameterType;
use TYPO3\CMS\Backend\View\Event\PageContentPreviewRenderingEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

/**
 * Page-module preview of the sitepackage_contact plugin, see
 * Documentation/ContactPlugin.md "Backend preview".
 */
final readonly class ContactPluginPreviewRenderer
{
    private const CTYPE = 'sitepackage_contact';
    private const CONTACT_TABLE = 'tx_sitepackage_domain_model_contact';

    public function __construct(
        private FlexFormTools $flexFormTools,
        private ViewFactoryInterface $viewFactory,
        private ConnectionPool $connectionPool,
    ) {}

    #[AsEventListener('sitepackage/contact-plugin-preview')]
    public function __invoke(PageContentPreviewRenderingEvent $event): void
    {
        if ($event->getTable() !== 'tt_content'
            || $event->getRecordType() !== self::CTYPE
            || $event->getPreviewContent() !== null
        ) {
            return;
        }

        $flexFormXml = (string)($event->getRecord()->getRawRecord()?->get('pi_flexform') ?? '');
        $settings = [];
        if ($flexFormXml !== '') {
            $flexFormData = $this->flexFormTools->convertFlexFormContentToArray($flexFormXml);
            $settings = $flexFormData['settings'] ?? [];
        }

        $pluginType = ($settings['pluginType'] ?? '') === 'show' ? 'show' : 'list';
        $categoryUids = $this->uidList($settings['categories'] ?? '');
        $listSource = (string)($settings['listSource'] ?? '');
        if ($listSource !== 'persons' && $listSource !== 'categories') {
            $listSource = $categoryUids !== [] ? 'categories' : 'persons';
        }
        $singleViewPage = $this->uidList($settings['singleViewPage'] ?? '')[0] ?? 0;

        $view = $this->viewFactory->create(new ViewFactoryData(
            templateRootPaths: ['EXT:sitepackage/Resources/Private/Templates/'],
            request: $event->getPageLayoutContext()->getCurrentRequest(),
        ));
        $view->assignMultiple([
            'pluginType' => $pluginType,
            'listSource' => $listSource,
            'persons' => $listSource === 'persons' ? $this->contactNames($this->uidList($settings['persons'] ?? '')) : [],
            'categories' => $listSource === 'categories' ? $this->titles('sys_category', 'title', $categoryUids) : [],
            'displayMode' => ($settings['displayMode'] ?? 'single') === 'multiple' ? 'multiple' : 'single',
            'singleViewPage' => $singleViewPage > 0
                ? ['uid' => $singleViewPage, 'title' => $this->titles('pages', 'title', [$singleViewPage])[0] ?? '']
                : null,
        ]);

        $event->setPreviewContent($view->render('Backend/ContactPluginPreview'));
    }

    /**
     * @param list<int> $uids
     * @return list<string> names in the given uid order
     */
    private function contactNames(array $uids): array
    {
        $rows = $this->rowsByUid(self::CONTACT_TABLE, ['first_name', 'last_name', 'department_text'], $uids);

        $names = [];
        foreach ($uids as $uid) {
            if (!isset($rows[$uid])) {
                continue;
            }
            $row = $rows[$uid];
            $person = trim($row['first_name'] . ' ' . $row['last_name']);
            $names[] = match (true) {
                $person !== '' && $row['department_text'] !== '' => $person . ' (' . $row['department_text'] . ')',
                $person !== '' => $person,
                default => (string)$row['department_text'],
            };
        }

        return $names;
    }

    /**
     * @param list<int> $uids
     * @return list<string> titles in the given uid order
     */
    private function titles(string $table, string $field, array $uids): array
    {
        $rows = $this->rowsByUid($table, [$field], $uids);

        return array_values(array_map(
            static fn(int $uid): string => (string)$rows[$uid][$field],
            array_filter($uids, static fn(int $uid): bool => isset($rows[$uid]))
        ));
    }

    /**
     * @param list<string> $fields
     * @param list<int> $uids
     * @return array<int, array<string, mixed>>
     */
    private function rowsByUid(string $table, array $fields, array $uids): array
    {
        if ($uids === []) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $rows = $queryBuilder->select('uid', ...$fields)
            ->from($table)
            ->where($queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($uids, ArrayParameterType::INTEGER)))
            ->executeQuery()
            ->fetchAllAssociative();

        return array_column($rows, null, 'uid');
    }

    /**
     * @return list<int>
     */
    private function uidList(mixed $value): array
    {
        $uids = array_map('intval', GeneralUtility::trimExplode(',', (string)$value, true));

        return array_values(array_filter($uids, static fn(int $uid): bool => $uid > 0));
    }
}
