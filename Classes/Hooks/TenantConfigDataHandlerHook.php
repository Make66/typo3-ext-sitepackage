<?php

declare(strict_types=1);

namespace Taketool\Sitepackage\Hooks;

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Taketool\Sitepackage\TenantConfig\TenantConfigRepository;
use Taketool\Sitepackage\TenantConfig\TenantSettingsDefinitionProvider;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Configuration\Event\SiteConfigurationChangedEvent;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Settings\SettingDefinition;
use TYPO3\CMS\Core\Settings\SettingsTypeRegistry;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\SysLog\Action\Database as SystemLogDatabaseAction;
use TYPO3\CMS\Core\SysLog\Error as SystemLogErrorClassification;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

#[Autoconfigure(public: true)]
final class TenantConfigDataHandlerHook
{
    private const TABLE = TenantConfigRepository::TABLE;
    private const LLL = 'LLL:EXT:sitepackage/Resources/Private/Language/locallang_tenantconfig.xlf:';

    /** @var array<string, string> */
    private array $changedSites = [];

    public function __construct(
        private readonly SiteFinder $siteFinder,
        private readonly TenantConfigRepository $repository,
        private readonly TenantSettingsDefinitionProvider $definitionProvider,
        private readonly SettingsTypeRegistry $settingsTypeRegistry,
        private readonly CacheManager $cacheManager,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function processDatamap_preProcessFieldArray(&$incomingFieldArray, $table, $id, DataHandler $dataHandler): void
    {
        if ($table !== self::TABLE || !is_array($incomingFieldArray)) {
            return;
        }
        $isNew = !MathUtility::canBeInterpretedAsInteger($id);
        $existingRecord = $isNew ? null : BackendUtility::getRecord(self::TABLE, (int)$id);
        if (!$isNew && $existingRecord === null) {
            return;
        }

        $pageId = $isNew ? $this->resolveNewRecordPageId($incomingFieldArray['pid'] ?? 0) : (int)$existingRecord['pid'];
        $site = $this->findSite($pageId);
        if ($site === null) {
            $this->error($dataHandler, $id, 'error.noSite', [$pageId]);
            $incomingFieldArray = null;
            return;
        }
        $rootPageId = $site->getRootPageId();

        $other = $this->repository->findByRootPageId($rootPageId, $isNew ? 0 : (int)$id);
        if ($other !== null) {
            $this->error($dataHandler, $id, 'error.duplicate', [$site->getIdentifier(), (int)$other['uid']]);
            $incomingFieldArray = null;
            return;
        }
        if ($isNew) {
            $incomingFieldArray['pid'] = $rootPageId;
        } else {
            unset($incomingFieldArray['pid']);
        }

        if (array_key_exists('settings', $incomingFieldArray)) {
            $previous = TenantConfigRepository::decodeSettings($existingRecord['settings'] ?? null);
            $incomingFieldArray['settings'] = $this->sanitizeSettings(
                TenantConfigRepository::decodeSettings($incomingFieldArray['settings']),
                $previous,
                $this->definitionProvider->getDefinitions($site->getConfiguration()['dependencies'] ?? []),
                $dataHandler,
                $id
            );
        }
        $this->changedSites[$site->getIdentifier()] = $site->getIdentifier();
    }

    public function processDatamap_afterAllOperations(DataHandler $dataHandler): void
    {
        $this->flushIfChanged();
    }

    public function processCmdmap($command, $table, $id, $value, &$commandIsProcessed, DataHandler $dataHandler, $pasteUpdate): void
    {
        if ($table !== self::TABLE || $commandIsProcessed) {
            return;
        }
        if (in_array($command, ['move', 'copy'], true)) {
            $this->error($dataHandler, $id, 'error.moveCopy', []);
            $commandIsProcessed = true;
            return;
        }
        if ($command === 'undelete') {
            $record = BackendUtility::getRecord(self::TABLE, (int)$id, '*', '', false);
            if ($record !== null && $this->repository->findByRootPageId((int)$record['pid'], (int)$id) !== null) {
                $this->error($dataHandler, $id, 'error.undeleteDuplicate', [(int)$record['pid']]);
                $commandIsProcessed = true;
            }
        }
    }

    public function processCmdmap_postProcess($command, $table, $id, $value, DataHandler $dataHandler, $pasteUpdate, $pasteDatamap): void
    {
        if ($table !== self::TABLE || !in_array($command, ['delete', 'undelete'], true)) {
            return;
        }
        $record = BackendUtility::getRecord(self::TABLE, (int)$id, '*', '', false);
        $site = $this->findSite((int)($record['pid'] ?? 0));
        if ($site !== null) {
            $this->changedSites[$site->getIdentifier()] = $site->getIdentifier();
        }
    }

    public function processCmdmap_afterFinish(DataHandler $dataHandler): void
    {
        $this->flushIfChanged();
    }

    /**
     * @param array<string, mixed> $posted
     * @param array<string, mixed> $previous
     * @param array<string, SettingDefinition> $definitions
     * @return array<string, mixed>
     */
    private function sanitizeSettings(array $posted, array $previous, array $definitions, DataHandler $dataHandler, string|int $id): array
    {
        $result = array_intersect_key($previous, $definitions);
        foreach ($posted as $key => $rawValue) {
            $key = (string)$key;
            $definition = $definitions[$key] ?? null;
            if ($definition === null) {
                $this->error($dataHandler, $id, 'error.notAllowed', [$key]);
                continue;
            }
            unset($result[$key]);
            if ($rawValue === null || $rawValue === '' || $rawValue === []) {
                continue;
            }
            $value = $this->normalize($rawValue, $definition);
            if ($value === null || !$this->isValid($value, $definition)) {
                $this->error($dataHandler, $id, 'error.invalid', [$definition->label, $key]);
                if (array_key_exists($key, $previous)) {
                    $result[$key] = $previous[$key];
                }
                continue;
            }
            $result[$key] = $this->settingsTypeRegistry->get($definition->type)->transformValue($value, $definition);
        }
        return $result;
    }

    private function normalize(mixed $value, SettingDefinition $definition): mixed
    {
        if ($definition->type === 'stringlist') {
            if (is_string($value)) {
                $value = GeneralUtility::trimExplode("\n", str_replace("\r", '', $value), true);
            }
            return is_array($value) ? array_values(array_map('strval', $value)) : null;
        }
        if (is_array($value)) {
            return null;
        }
        if ($definition->type === 'bool') {
            return match ((string)$value) {
                '1', 'true' => true,
                '0', 'false' => false,
                default => null,
            };
        }
        if (in_array($definition->type, ['int', 'page'], true)) {
            return MathUtility::canBeInterpretedAsInteger($value) ? (int)$value : null;
        }
        if ($definition->type === 'number') {
            return is_numeric($value) ? $value + 0 : null;
        }
        return trim((string)$value);
    }

    private function isValid(mixed $value, SettingDefinition $definition): bool
    {
        if ($definition->enum !== [] && !array_key_exists((string)$value, $definition->enum)) {
            return false;
        }
        if (!$this->settingsTypeRegistry->has($definition->type)) {
            return false;
        }
        return $this->settingsTypeRegistry->get($definition->type)->validate($value, $definition);
    }

    private function resolveNewRecordPageId(mixed $pid): int
    {
        $pid = (int)$pid;
        if ($pid >= 0) {
            return $pid;
        }
        $record = BackendUtility::getRecord(self::TABLE, abs($pid), 'pid');
        return (int)($record['pid'] ?? 0);
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

    private function flushIfChanged(): void
    {
        if ($this->changedSites === []) {
            return;
        }
        $this->cacheManager->getCache('core')->flush();
        foreach ($this->changedSites as $identifier) {
            $this->eventDispatcher->dispatch(new SiteConfigurationChangedEvent($identifier));
        }
        $this->cacheManager->flushCachesInGroup('pages');
        $this->changedSites = [];
    }

    private function error(DataHandler $dataHandler, string|int $id, string $labelKey, array $arguments): void
    {
        $message = $this->getLanguageService()?->sL(self::LLL . $labelKey) ?: $labelKey;
        if ($arguments !== []) {
            $message = vsprintf($message, $arguments);
        }
        $dataHandler->log(
            self::TABLE,
            MathUtility::canBeInterpretedAsInteger($id) ? (int)$id : 0,
            SystemLogDatabaseAction::UPDATE,
            null,
            SystemLogErrorClassification::USER_ERROR,
            $message
        );
    }

    private function getLanguageService(): ?LanguageService
    {
        return $GLOBALS['LANG'] ?? null;
    }
}
