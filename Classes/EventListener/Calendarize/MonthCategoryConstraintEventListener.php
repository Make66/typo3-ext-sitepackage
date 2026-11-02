<?php

declare(strict_types=1);

namespace Taketool\Sitepackage\EventListener\Calendarize;

use HDNET\Calendarize\Domain\Model\Dto\Search;
use HDNET\Calendarize\Domain\Repository\EventRepository;
use HDNET\Calendarize\Event\IndexRepositoryDefaultConstraintEvent;
use HDNET\Calendarize\Register;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Category filter for the calendarize month view, see Documentation/Calendarize.md
 */
final class MonthCategoryConstraintEventListener
{
    public const CTYPE = 'calendarize_month';
    private const EVENT_TABLE = 'tx_calendarize_domain_model_event';

    public function __construct(
        private readonly EventRepository $eventRepository,
    ) {}

    public function __invoke(IndexRepositoryDefaultConstraintEvent $event): void
    {
        $arguments = $event->getAdditionalSlotArguments();
        if (($arguments['contentRecord']['CType'] ?? '') !== self::CTYPE
            || empty($arguments['settings']['monthCategoryFilter'])
            || !\in_array(Register::UNIQUE_REGISTER_KEY, $event->getIndexTypes(), true)
        ) {
            return;
        }

        $categories = self::getSelectedCategories($GLOBALS['TYPO3_REQUEST'] ?? null);
        if ($categories === []) {
            return;
        }

        $search = new Search();
        $search->setCategories($categories);
        $eventIds = $this->eventRepository->findBySearch($search);

        $foreignIds = $event->getForeignIds();
        if (isset($foreignIds[self::EVENT_TABLE])) {
            $eventIds = array_intersect($eventIds, $foreignIds[self::EVENT_TABLE]);
        }
        $eventIds[] = -1;
        $foreignIds[self::EVENT_TABLE] = array_values(array_unique($eventIds));
        $event->setForeignIds($foreignIds);
    }

    /**
     * @return int[]
     */
    public static function getSelectedCategories(?ServerRequestInterface $request): array
    {
        $categories = $request?->getQueryParams()['tx_calendarize_calendar']['customSearch']['categories'] ?? [];
        if (!\is_array($categories)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $categories), static fn(int $uid) => $uid > 0)));
    }
}
