<?php

declare(strict_types=1);

namespace Taketool\Sitepackage\Form\Element;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Taketool\Sitepackage\TenantConfig\TenantConfigRepository;
use Taketool\Sitepackage\TenantConfig\TenantSettingsDefinitionProvider;
use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Settings\SettingDefinition;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\StringUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

#[Autoconfigure(public: true, shared: false)]
class TenantSiteSettingsElement extends AbstractFormElement
{
    private const LLL = 'LLL:EXT:sitepackage/Resources/Private/Language/locallang_tenantconfig.xlf:';

    public function __construct(
        private readonly SiteFinder $siteFinder,
        private readonly TenantConfigRepository $repository,
        private readonly TenantSettingsDefinitionProvider $definitionProvider,
        private readonly UriBuilder $uriBuilder,
        private readonly ViewFactoryInterface $viewFactory,
    ) {}

    public function render(): array
    {
        $resultArray = $this->initializeResultArray();
        $parameterArray = $this->data['parameterArray'];
        $readOnly = (bool)($parameterArray['fieldConf']['config']['readOnly'] ?? false);
        $isNew = ($this->data['command'] ?? '') === 'new';
        $uid = $isNew ? 0 : (int)($this->data['vanillaUid'] ?? 0);
        $pid = $isNew ? (int)($this->data['effectivePid'] ?? 0) : (int)($this->data['databaseRow']['pid'] ?? 0);

        $variables = [
            'state' => 'fields',
            'readOnly' => $readOnly,
        ];

        $site = $this->findSite($pid);
        if ($site === null) {
            $variables['state'] = 'noSite';
            return $this->renderView($resultArray, $variables);
        }
        $rootPageId = $site->getRootPageId();
        $rootPage = BackendUtility::getRecord('pages', $rootPageId) ?? [];
        $variables['site'] = [
            'identifier' => $site->getIdentifier(),
            'rootPageId' => $rootPageId,
            'rootPageTitle' => (string)($rootPage['title'] ?? ''),
        ];

        $existing = $this->repository->findByRootPageId($rootPageId, $uid);
        if ($existing !== null) {
            $variables['state'] = 'exists';
            $variables['editUrl'] = (string)$this->uriBuilder->buildUriFromRoute('record_edit', [
                'edit' => [TenantConfigRepository::TABLE => [(int)$existing['uid'] => 'edit']],
                'returnUrl' => (string)($this->data['returnUrl'] ?? ''),
            ]);
            return $this->renderView($resultArray, $variables);
        }

        $variables['movedToRoot'] = $pid !== $rootPageId;
        $dependencies = $site->getConfiguration()['dependencies'] ?? [];
        $groups = $this->definitionProvider->getGroupedDefinitions($dependencies);
        if ($groups === []) {
            $variables['state'] = 'noSets';
            return $this->renderView($resultArray, $variables);
        }

        $effective = $this->definitionProvider->getEffectiveSettingsWithoutOverrides($site);
        $values = TenantConfigRepository::decodeSettings($parameterArray['itemFormElValue'] ?? null);
        $baseName = (string)$parameterArray['itemFormElName'];

        $viewGroups = [];
        foreach ($groups as $group) {
            $categories = [];
            $overriddenCount = 0;
            foreach ($group['categories'] as $category) {
                $fields = [];
                foreach ($category['definitions'] as $definition) {
                    $field = $this->buildField($definition, $effective, $values, $baseName);
                    $overriddenCount += $field['overridden'] ? 1 : 0;
                    $fields[] = $field;
                }
                $categories[] = [
                    'label' => $this->translate($category['label'] !== '' ? $category['label'] : $group['set']->label),
                    'description' => $this->translate($category['description'] ?? ''),
                    'fields' => $fields,
                ];
            }
            $viewGroups[] = [
                'label' => $this->translate($group['set']->label),
                'name' => $group['set']->name,
                'overriddenCount' => $overriddenCount,
                'categories' => $categories,
            ];
        }
        $variables['groups'] = $viewGroups;
        $variables['tabId'] = 'tenantconfig-' . preg_replace('/[^a-z0-9_-]/i', '-', $site->getIdentifier());
        $resultArray['javaScriptModules'][] = JavaScriptModuleInstruction::create('@typo3/backend/tab.js');

        return $this->renderView($resultArray, $variables);
    }

    private function buildField(SettingDefinition $definition, SiteSettings $effective, array $values, string $baseName): array
    {
        $effectiveValue = $effective->has($definition->key) ? $effective->get($definition->key) : $definition->default;
        $hasValue = array_key_exists($definition->key, $values) && $values[$definition->key] !== null;
        $value = $hasValue ? $values[$definition->key] : null;

        $field = [
            'key' => $definition->key,
            'id' => StringUtility::getUniqueId('tenantconfig-'),
            'name' => $baseName . '[' . $definition->key . ']',
            'label' => $this->translate($definition->label),
            'description' => $this->translate((string)$definition->description),
            'overridden' => $hasValue,
            'control' => 'input',
            'inputType' => 'text',
            'step' => '',
            'value' => '',
            'placeholder' => '',
            'options' => [],
        ];

        if ($definition->type === 'bool') {
            $field['control'] = 'select';
            $field['options'] = [
                $this->option('', sprintf($this->translate(self::LLL . 'inherit'), $this->formatBool($effectiveValue)), !$hasValue),
                $this->option('1', $this->translate(self::LLL . 'bool.yes'), $hasValue && (bool)$value),
                $this->option('0', $this->translate(self::LLL . 'bool.no'), $hasValue && !(bool)$value),
            ];
            return $field;
        }

        if ($definition->enum !== []) {
            $field['control'] = 'select';
            $effectiveLabel = $definition->enum[(string)$effectiveValue] ?? (string)$effectiveValue;
            $field['options'][] = $this->option('', sprintf($this->translate(self::LLL . 'inherit'), $this->translate((string)$effectiveLabel)), !$hasValue);
            foreach ($definition->enum as $enumValue => $enumLabel) {
                $field['options'][] = $this->option((string)$enumValue, $this->translate((string)$enumLabel), $hasValue && (string)$value === (string)$enumValue);
            }
            return $field;
        }

        if (in_array($definition->type, ['stringlist', 'text'], true)) {
            $field['control'] = 'textarea';
            $field['value'] = is_array($value) ? implode("\n", $value) : (string)$value;
            $field['placeholder'] = is_array($effectiveValue) ? implode("\n", $effectiveValue) : (string)$effectiveValue;
            $field['rows'] = $definition->type === 'stringlist' ? 4 : 5;
            return $field;
        }

        if (in_array($definition->type, ['int', 'number', 'page'], true)) {
            $field['inputType'] = 'number';
            $field['step'] = $definition->type === 'number' ? 'any' : '1';
        }
        if ($definition->type === 'url') {
            $field['inputType'] = 'url';
        }
        if ($definition->type === 'color') {
            $field['control'] = 'color';
            $field['swatch'] = $hasValue ? (string)$value : (string)$effectiveValue;
        }
        $field['value'] = is_scalar($value) ? (string)$value : '';
        $field['placeholder'] = is_scalar($effectiveValue) ? (string)$effectiveValue : '';
        return $field;
    }

    private function option(string $value, string $label, bool $selected): array
    {
        return ['value' => $value, 'label' => $label, 'selected' => $selected];
    }

    private function formatBool(mixed $value): string
    {
        return $this->translate(self::LLL . ($value ? 'bool.yes' : 'bool.no'));
    }

    private function translate(string $label): string
    {
        if ($label === '') {
            return '';
        }
        if (!str_starts_with($label, 'LLL:') && !preg_match('/^[a-z][a-z0-9_]*(\.[a-z0-9_]+)+:[A-Za-z0-9_.\-]+$/', $label)) {
            return $label;
        }
        return $this->getLanguageService()->sL($label) ?: $label;
    }

    private function findSite(int $pageId): ?Site
    {
        if ($pageId <= 0) {
            return null;
        }
        try {
            return $this->siteFinder->getSiteByPageId($pageId);
        } catch (SiteNotFoundException) {
            return null;
        }
    }

    private function renderView(array $resultArray, array $variables): array
    {
        $view = $this->viewFactory->create(new ViewFactoryData(
            templatePathAndFilename: 'EXT:sitepackage/Resources/Private/Templates/Form/TenantSiteSettings.html',
        ));
        $view->assignMultiple($variables);
        $resultArray['html'] = $view->render();
        return $resultArray;
    }
}
