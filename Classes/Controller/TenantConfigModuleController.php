<?php

declare(strict_types=1);

namespace Taketool\Sitepackage\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Taketool\Sitepackage\TenantConfig\TenantConfigRepository;
use Taketool\Sitepackage\TenantConfig\TenantSettingsDefinitionProvider;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Type\Bitmask\Permission;

#[AsController]
final readonly class TenantConfigModuleController
{
    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private SiteFinder $siteFinder,
        private TenantConfigRepository $repository,
        private TenantSettingsDefinitionProvider $definitionProvider,
        private UriBuilder $uriBuilder,
    ) {}

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $pageId = (int)($request->getQueryParams()['id'] ?? $request->getParsedBody()['id'] ?? 0);
        $view = $this->moduleTemplateFactory->create($request);
        $view->setTitle($this->getLanguageService()->sL('LLL:EXT:sitepackage/Resources/Private/Language/locallang_mod_tenantconfig.xlf:title'));

        $variables = ['state' => 'noPage', 'pageId' => $pageId];
        $backendUser = $this->getBackendUser();
        $pageInfo = $pageId > 0 ? BackendUtility::readPageAccess($pageId, $backendUser->getPagePermsClause(Permission::PAGE_SHOW)) : false;
        if (is_array($pageInfo)) {
            $view->getDocHeaderComponent()->setPageBreadcrumb($pageInfo);
        }

        if ($pageId > 0) {
            $variables = $this->resolve($pageId, $backendUser, $variables);
            if (isset($variables['redirect'])) {
                return new RedirectResponse($variables['redirect']);
            }
        }

        $view->assignMultiple($variables);
        return $view->renderResponse('TenantConfig/Index');
    }

    private function resolve(int $pageId, BackendUserAuthentication $backendUser, array $variables): array
    {
        try {
            $site = $this->siteFinder->getSiteByPageId($pageId);
        } catch (SiteNotFoundException) {
            return ['state' => 'noSite'] + $variables;
        }
        $rootPageId = $site->getRootPageId();
        $rootPage = BackendUtility::readPageAccess($rootPageId, $backendUser->getPagePermsClause(Permission::PAGE_SHOW));
        $variables['site'] = [
            'identifier' => $site->getIdentifier(),
            'rootPageId' => $rootPageId,
            'rootPageTitle' => is_array($rootPage) ? (string)($rootPage['title'] ?? '') : '',
        ];
        if (!is_array($rootPage) || !$backendUser->check('tables_modify', TenantConfigRepository::TABLE)) {
            return ['state' => 'noAccess'] + $variables;
        }

        $returnUrl = (string)$this->uriBuilder->buildUriFromRoute('web_layout', ['id' => $pageId]);
        $record = $this->repository->findByRootPageId($rootPageId);
        if ($record !== null) {
            $variables['redirect'] = (string)$this->uriBuilder->buildUriFromRoute('record_edit', [
                'edit' => [TenantConfigRepository::TABLE => [(int)$record['uid'] => 'edit']],
                'returnUrl' => $returnUrl,
            ]);
            return $variables;
        }

        $sets = $this->definitionProvider->getGroupedDefinitions($site->getConfiguration()['dependencies'] ?? []);
        if ($sets === []) {
            return ['state' => 'noSets'] + $variables;
        }
        $variables['state'] = 'missing';
        $languageService = $this->getLanguageService();
        $variables['sets'] = array_map(
            static fn(array $group): array => [
                'label' => str_starts_with($group['set']->label, 'LLL:') ? ($languageService->sL($group['set']->label) ?: $group['set']->name) : $group['set']->label,
                'name' => $group['set']->name,
            ],
            $sets
        );
        $variables['createUrl'] = (string)$this->uriBuilder->buildUriFromRoute('record_edit', [
            'edit' => [TenantConfigRepository::TABLE => [$rootPageId => 'new']],
            'returnUrl' => $returnUrl,
        ]);
        return $variables;
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    private function getLanguageService(): \TYPO3\CMS\Core\Localization\LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
