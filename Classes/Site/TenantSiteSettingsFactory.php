<?php

declare(strict_types=1);

namespace Taketool\Sitepackage\Site;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Taketool\Sitepackage\TenantConfig\TenantConfigRepository;
use Taketool\Sitepackage\TenantConfig\TenantSettingsDefinitionProvider;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use TYPO3\CMS\Core\Site\SiteSettingsFactory;
use TYPO3\CMS\Core\Site\SiteSettingsProvider;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[Autoconfigure(public: true)]
readonly class TenantSiteSettingsFactory extends SiteSettingsFactory
{
    public function createSettings(array $sets = [], ?string $siteIdentifier = null, array $inlineSettings = []): SiteSettings
    {
        $rawSettings = [];
        if ($siteIdentifier !== null) {
            $rawSettings = $this->loadLocalSettings($siteIdentifier) ?? $inlineSettings;
        }

        $overrides = $siteIdentifier === null ? [] : $this->getTenantOverrides($siteIdentifier, $sets);
        if ($overrides === []) {
            return $this->composeSettings($rawSettings, $sets);
        }

        return SiteSettings::create(
            $this->settingsFactory->resolveSettings(
                ...$this->getSettingsProviders($rawSettings, $sets),
                ...[new SiteSettingsProvider($overrides)]
            )
        );
    }

    /**
     * @param list<string> $sets
     * @return array<string, mixed>
     */
    private function getTenantOverrides(string $siteIdentifier, array $sets): array
    {
        try {
            $rootPageId = $this->getRootPageId($siteIdentifier);
            if ($rootPageId <= 0 || $sets === []) {
                return [];
            }
            $overrides = GeneralUtility::makeInstance(TenantConfigRepository::class)->findOverridesByRootPageId($rootPageId);
            if ($overrides === []) {
                return [];
            }
            $allowed = GeneralUtility::makeInstance(TenantSettingsDefinitionProvider::class)->getDefinitions($sets);
            return array_intersect_key($overrides, $allowed);
        } catch (\Throwable) {
            return [];
        }
    }

    private function getRootPageId(string $siteIdentifier): int
    {
        $fileName = $this->configPath . '/' . $siteIdentifier . '/config.yaml';
        if (!file_exists($fileName)) {
            return 0;
        }
        $configuration = $this->yamlFileLoader->load(GeneralUtility::fixWindowsFilePath($fileName));
        return (int)($configuration['rootPageId'] ?? 0);
    }
}
