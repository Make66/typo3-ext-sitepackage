<?php

declare(strict_types=1);

use HDNET\Calendarize\Event\GenericActionAssignmentEvent;
use HDNET\Calendarize\Event\IndexRepositoryDefaultConstraintEvent;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Taketool\Sitepackage\EventListener\Calendarize\MonthCategoryColorEventListener;
use Taketool\Sitepackage\EventListener\Calendarize\MonthCategoryConstraintEventListener;
use Taketool\Sitepackage\EventListener\Calendarize\MonthCategoryFilterEventListener;
use Taketool\Sitepackage\EventListener\Calendarize\MonthTodayCacheLifetimeEventListener;

return static function (ContainerConfigurator $containerConfigurator): void {
    // calendarize is optional, see Documentation/Calendarize.md
    if (!class_exists(IndexRepositoryDefaultConstraintEvent::class)) {
        return;
    }

    $services = $containerConfigurator->services();
    $services->defaults()->autowire()->autoconfigure()->private();

    $services->set(MonthCategoryConstraintEventListener::class)
        ->tag('event.listener', [
            'identifier' => 'sitepackage/calendarize-month-category-constraint',
            'event' => IndexRepositoryDefaultConstraintEvent::class,
            'after' => 'defaultEventConstraints',
        ]);

    $services->set(MonthCategoryFilterEventListener::class)
        ->tag('event.listener', [
            'identifier' => 'sitepackage/calendarize-month-category-filter',
            'event' => GenericActionAssignmentEvent::class,
        ]);

    $services->set(MonthCategoryColorEventListener::class)
        ->tag('event.listener', [
            'identifier' => 'sitepackage/calendarize-month-category-color',
            'event' => GenericActionAssignmentEvent::class,
        ]);

    $services->set(MonthTodayCacheLifetimeEventListener::class)
        ->tag('event.listener', [
            'identifier' => 'sitepackage/calendarize-month-today-cache-lifetime',
            'event' => GenericActionAssignmentEvent::class,
        ]);
};
