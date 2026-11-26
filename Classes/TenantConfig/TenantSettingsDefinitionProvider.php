<?php

declare(strict_types=1);

namespace Taketool\Sitepackage\TenantConfig;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Settings\CategoryDefinition;
use TYPO3\CMS\Core\Settings\SettingDefinition;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use TYPO3\CMS\Core\Site\Set\SetDefinition;
use TYPO3\CMS\Core\Site\Set\SetRegistry;
use TYPO3\CMS\Core\Site\SiteSettingsFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[Autoconfigure(public: true)]
final readonly class TenantSettingsDefinitionProvider
{
    private const DEFAULT_SETS = 'taketool/*, apicodo/*';
    private const DEFAULT_EXCLUDES = '*apiKey*, *ApiKey*, chatbot.serverApiUrl, chatbot.serviceUrl';
    private const DEFAULT_TAB_ORDER = 'taketool/sitepackage, taketool/apicodo, taketool/infodienste, taketool/tsa, apicodo/chatbot';

    public function __construct(
        private SetRegistry $setRegistry,
        private ExtensionConfiguration $extensionConfiguration,
    ) {}

    /**
     * @param list<string> $dependencies
     * @return list<SetDefinition>
     */
    public function getAllowedSets(array $dependencies): array
    {
        if ($dependencies === []) {
            return [];
        }
        $patterns = $this->getPatterns('sets', self::DEFAULT_SETS);
        return array_values(array_filter(
            $this->setRegistry->getSets(...$dependencies),
            fn(SetDefinition $set): bool => $this->matchesAny($set->name, $patterns)
        ));
    }

    /**
     * @param list<string> $dependencies
     * @return array<string, SettingDefinition>
     */
    public function getDefinitions(array $dependencies): array
    {
        $excludes = $this->getPatterns('excludeSettings', self::DEFAULT_EXCLUDES);
        $definitions = [];
        foreach ($this->getAllowedSets($dependencies) as $set) {
            foreach ($set->settingsDefinitions as $definition) {
                if ($definition->readonly || $this->matchesAny($definition->key, $excludes)) {
                    unset($definitions[$definition->key]);
                    continue;
                }
                $definitions[$definition->key] = $definition;
            }
        }
        return $definitions;
    }

    /**
     * @param list<string> $dependencies
     * @return list<array{set: SetDefinition, categories: list<array{label: string, definitions: list<SettingDefinition>}>}>
     */
    public function getGroupedDefinitions(array $dependencies): array
    {
        $allowedSets = $this->getAllowedSets($dependencies);
        $definitions = $this->getDefinitions($dependencies);

        $categoryDefinitions = [];
        foreach ($this->setRegistry->getSets(...$dependencies) as $set) {
            foreach ($set->categoryDefinitions as $category) {
                if ($category instanceof CategoryDefinition) {
                    $categoryDefinitions[$category->key] = $category;
                }
            }
        }

        $owner = [];
        foreach ($allowedSets as $set) {
            foreach ($set->settingsDefinitions as $definition) {
                if (isset($definitions[$definition->key])) {
                    $owner[$definition->key] = $set->name;
                }
            }
        }

        $groups = [];
        foreach ($allowedSets as $set) {
            $categories = [];
            foreach ($set->settingsDefinitions as $definition) {
                if (($owner[$definition->key] ?? null) !== $set->name) {
                    continue;
                }
                $categoryKey = (string)($definition->category ?? '');
                $categories[$categoryKey] ??= [
                    'label' => ($categoryDefinitions[$categoryKey] ?? null)?->label ?? '',
                    'description' => (string)(($categoryDefinitions[$categoryKey] ?? null)?->description ?? ''),
                    'definitions' => [],
                ];
                $categories[$categoryKey]['definitions'][] = $definitions[$definition->key];
            }
            if ($categories !== []) {
                $groups[] = ['set' => $set, 'categories' => array_values($categories)];
            }
        }
        return $this->sortGroups($groups);
    }

    /**
     * @param list<array{set: SetDefinition, categories: list<array>}> $groups
     * @return list<array{set: SetDefinition, categories: list<array>}>
     */
    private function sortGroups(array $groups): array
    {
        $order = $this->getPatterns('tabOrder', self::DEFAULT_TAB_ORDER);
        $rank = static function (string $setName) use ($order): int {
            foreach ($order as $index => $pattern) {
                if (fnmatch($pattern, $setName)) {
                    return $index;
                }
            }
            return PHP_INT_MAX;
        };
        usort($groups, static fn(array $a, array $b): int => $rank($a['set']->name) <=> $rank($b['set']->name));
        return $groups;
    }

    public function getEffectiveSettingsWithoutOverrides(Site $site): SiteSettings
    {
        $factory = GeneralUtility::makeInstance(SiteSettingsFactory::class);
        $configuration = $site->getConfiguration();
        return $factory->composeSettings(
            $factory->loadLocalSettings($site->getIdentifier()) ?? ($configuration['settings'] ?? []),
            $configuration['dependencies'] ?? []
        );
    }

    /**
     * @return list<string>
     */
    private function getPatterns(string $option, string $default): array
    {
        try {
            $value = $this->extensionConfiguration->get('sitepackage', 'tenantConfig/' . $option);
        } catch (\Throwable) {
            $value = null;
        }
        if (!is_string($value) || trim($value) === '') {
            $value = $default;
        }
        return GeneralUtility::trimExplode(',', $value, true);
    }

    /**
     * @param list<string> $patterns
     */
    private function matchesAny(string $subject, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $subject)) {
                return true;
            }
        }
        return false;
    }
}
