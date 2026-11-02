<?php

declare(strict_types=1);

namespace Taketool\Sitepackage\EventListener\Calendarize;

use Doctrine\DBAL\ArrayParameterType;
use HDNET\Calendarize\Controller\CalendarController;
use HDNET\Calendarize\Event\GenericActionAssignmentEvent;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

/**
 * Category colors for the calendarize month view, see Documentation/Calendarize.md
 */
final class MonthCategoryColorEventListener
{
    /**
     * Strong colors (white text readable) for categories without their own color
     */
    private const PALETTE = ['#c62828', '#1565c0', '#2e7d32', '#e65100', '#6a1b9a', '#00838f', '#ad1457', '#4e342e'];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly ConfigurationManagerInterface $configurationManager,
    ) {}

    public function __invoke(GenericActionAssignmentEvent $event): void
    {
        if ($event->getClassName() !== CalendarController::class || $event->getFunctionName() !== 'monthAction') {
            return;
        }
        $variables = $event->getVariables();
        $variables['extended']['categoryColors'] = $this->getCategoryColors();
        $event->setVariables($variables);
    }

    /**
     * Colors of all categories used by events in the plugin's storage folders,
     * ordered by title so the palette fallback stays the same from month to month.
     *
     * @return array<int, string> category uid => color
     */
    private function getCategoryColors(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_category');
        $queryBuilder
            ->selectLiteral('DISTINCT c.uid, c.title, c.tx_sitepackage_color')
            ->from('sys_category', 'c')
            ->join('c', 'sys_category_record_mm', 'mm', 'mm.uid_local = c.uid')
            ->join('mm', 'tx_calendarize_domain_model_event', 'e', 'e.uid = mm.uid_foreign')
            ->where(
                $queryBuilder->expr()->eq('mm.tablenames', $queryBuilder->createNamedParameter('tx_calendarize_domain_model_event')),
                $queryBuilder->expr()->eq('mm.fieldname', $queryBuilder->createNamedParameter('categories')),
            )
            ->orderBy('c.title');

        $frameworkConfig = $this->configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK);
        $storagePids = GeneralUtility::intExplode(',', (string)($frameworkConfig['persistence']['storagePid'] ?? ''), true);
        if ($storagePids !== []) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->in('e.pid', $queryBuilder->createNamedParameter($storagePids, ArrayParameterType::INTEGER))
            );
        }

        $colors = [];
        foreach ($queryBuilder->executeQuery()->fetchAllAssociative() as $position => $row) {
            $colors[(int)$row['uid']] = $row['tx_sitepackage_color'] ?: self::PALETTE[$position % \count(self::PALETTE)];
        }

        return $colors;
    }
}
