<?php

declare(strict_types=1);

namespace Taketool\Sitepackage\ExpressionLanguage;

use Taketool\Sitepackage\ExpressionLanguage\FunctionsProvider\RoutingFunctionsProvider;
use TYPO3\CMS\Core\ExpressionLanguage\AbstractProvider;

/**
 * Adds sitepackagePluginPage() to the "routing" context (limitToPages of
 * route enhancers), see Documentation/RouteEnhancers.md.
 */
class RoutingConditionProvider extends AbstractProvider
{
    public function __construct()
    {
        $this->expressionLanguageProviders = [
            RoutingFunctionsProvider::class,
        ];
    }
}
