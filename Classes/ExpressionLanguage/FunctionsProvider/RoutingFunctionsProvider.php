<?php

declare(strict_types=1);

namespace Taketool\Sitepackage\ExpressionLanguage\FunctionsProvider;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * sitepackagePluginPage('<key>'[, '<module>']): true if the page's "Contains
 * plugin" field is <module> (default: <key>) or its uid is listed in site
 * setting sitepackage.routing.<key>Pages. See Documentation/RouteEnhancers.md.
 */
class RoutingFunctionsProvider implements ExpressionFunctionProviderInterface
{
    public function getFunctions(): array
    {
        return [
            $this->getPluginPageFunction(),
        ];
    }

    protected function getPluginPageFunction(): ExpressionFunction
    {
        return new ExpressionFunction(
            'sitepackagePluginPage',
            static fn() => null, // Not implemented, we only use the evaluator
            static function (array $arguments, string $key, string $module = ''): bool {
                $page = $arguments['page'] ?? [];
                if (!is_array($page) || $page === []) {
                    return false;
                }
                if ((string)($page['module'] ?? '') === ($module !== '' ? $module : $key)) {
                    return true;
                }
                $site = $arguments['site'] ?? null;
                if (!$site instanceof Site) {
                    return false;
                }
                $pageId = (int)(($page['l10n_parent'] ?? 0) ?: ($page['uid'] ?? 0));
                $pageIds = $site->getSettings()->get('sitepackage.routing.' . $key . 'Pages', []);
                if (!is_array($pageIds)) {
                    $pageIds = explode(',', (string)$pageIds);
                }
                return $pageId > 0 && in_array($pageId, array_map('intval', $pageIds), true);
            }
        );
    }
}
