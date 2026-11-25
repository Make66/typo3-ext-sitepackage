<?php

declare(strict_types=1);

namespace Taketool\Sitepackage\TenantConfig;

use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class TenantConfigLabel
{
    public function getTitle(array &$parameters): void
    {
        $prefix = $this->getLanguageService()?->sL('LLL:EXT:sitepackage/Resources/Private/Language/locallang_tenantconfig.xlf:label.prefix') ?: 'Configuration';
        $pid = (int)($parameters['row']['pid'] ?? 0);
        $identifier = '';
        if ($pid > 0) {
            try {
                $identifier = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($pid)->getIdentifier();
            } catch (SiteNotFoundException) {
            }
        }
        $parameters['title'] = $prefix . ': ' . ($identifier !== '' ? $identifier : '[' . $pid . ']');
    }

    private function getLanguageService(): ?LanguageService
    {
        return $GLOBALS['LANG'] ?? null;
    }
}
