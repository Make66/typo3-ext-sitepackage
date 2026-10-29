<?php

declare(strict_types=1);

namespace Taketool\Sitepackage\EventListener\Calendarize;

use Doctrine\DBAL\ArrayParameterType;
use HDNET\Calendarize\Controller\CalendarController;
use HDNET\Calendarize\Domain\Repository\CategoryRepository;
use HDNET\Calendarize\Event\GenericActionAssignmentEvent;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;

/**
 * Category filter for the calendarize month view, see Documentation/Calendarize.md
 */
final class MonthCategoryFilterEventListener
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly CategoryRepository $categoryRepository,
        private readonly ConfigurationManagerInterface $configurationManager,
    ) {}

    public function __invoke(GenericActionAssignmentEvent $event): void
    {
        if ($event->getClassName() !== CalendarController::class || $event->getFunctionName() !== 'monthAction') {
            return;
        }
        $variables = $event->getVariables();
        if (empty($variables['settings']['monthCategoryFilter'])) {
            return;
        }

        $variables['extended']['categories'] = $this->getCategories((int)($variables['contentObject']['uid'] ?? 0));
        $variables['extended']['categoryFilter'] = MonthCategoryConstraintEventListener::getSelectedCategories(
            $GLOBALS['TYPO3_REQUEST'] ?? null
        );
        $event->setVariables($variables);
    }

    private function getCategories(int $contentUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_category_record_mm');
        $queryBuilder
            ->selectLiteral('DISTINCT mm.uid_local')
            ->from('sys_category_record_mm', 'mm')
            ->join('mm', 'tx_calendarize_domain_model_event', 'e', 'e.uid = mm.uid_foreign')
            ->where(
                $queryBuilder->expr()->eq('mm.tablenames', $queryBuilder->createNamedParameter('tx_calendarize_domain_model_event')),
                $queryBuilder->expr()->eq('mm.fieldname', $queryBuilder->createNamedParameter('categories')),
            );

        $frameworkConfig = $this->configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK);
        $storagePids = GeneralUtility::intExplode(',', (string)($frameworkConfig['persistence']['storagePid'] ?? ''), true);
        if ($storagePids !== []) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->in('e.pid', $queryBuilder->createNamedParameter($storagePids, ArrayParameterType::INTEGER))
            );
        }

        $contentCategories = $this->getContentCategories($contentUid);
        if ($contentCategories !== []) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->in('mm.uid_local', $queryBuilder->createNamedParameter($contentCategories, ArrayParameterType::INTEGER))
            );
        }

        $categoryIds = $queryBuilder->executeQuery()->fetchFirstColumn();
        if ($categoryIds === []) {
            return [];
        }

        return $this->categoryRepository->findByIds($categoryIds, ['title' => QueryInterface::ORDER_ASCENDING])->toArray();
    }

    /**
     * @return int[]
     */
    private function getContentCategories(int $contentUid): array
    {
        if ($contentUid <= 0) {
            return [];
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_category_record_mm');

        return array_map('intval', $queryBuilder
            ->select('uid_local')
            ->from('sys_category_record_mm')
            ->where(
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter('tt_content')),
                $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter('categories')),
                $queryBuilder->expr()->eq('uid_foreign', $queryBuilder->createNamedParameter($contentUid, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchFirstColumn());
    }
}
