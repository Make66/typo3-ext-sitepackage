<?php

declare(strict_types=1);

namespace Taketool\Sitepackage\EventListener\Calendarize;

use HDNET\Calendarize\Controller\CalendarController;
use HDNET\Calendarize\Event\GenericActionAssignmentEvent;

/**
 * The month view marks today and shows a "Today" button, so the cached page must
 * not outlive the current day, see Documentation/Calendarize.md
 */
final class MonthTodayCacheLifetimeEventListener
{
    public function __invoke(GenericActionAssignmentEvent $event): void
    {
        if ($event->getClassName() !== CalendarController::class || $event->getFunctionName() !== 'monthAction') {
            return;
        }

        $secondsUntilMidnight = (new \DateTimeImmutable('tomorrow'))->getTimestamp() - time();
        $GLOBALS['TYPO3_REQUEST']?->getAttribute('frontend.cache.collector')
            ?->restrictMaximumLifetime(max(1, $secondsUntilMidnight));
    }
}
