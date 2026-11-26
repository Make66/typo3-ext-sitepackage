<?php

declare(strict_types=1);

namespace Taketool\Sitepackage\Upgrades;

use TYPO3\CMS\Core\Attribute\UpgradeWizard;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Upgrades\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Core\Upgrades\RepeatableInterface;
use TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface;

#[UpgradeWizard('sitepackage_contactPhotoCropVariants')]
final class ContactPhotoCropVariantsWizard implements UpgradeWizardInterface, RepeatableInterface
{
    private const TABLE = 'sys_file_reference';
    private const FOREIGN_TABLE = 'tx_sitepackage_domain_model_contact';
    private const FIELD = 'photo';
    private const VARIANTS = ['card', 'detail'];
    private const WHOLE_IMAGE = [
        'cropArea' => ['x' => 0.0, 'y' => 0.0, 'width' => 1.0, 'height' => 1.0],
        'selectedRatio' => 'NaN',
        'focusArea' => null,
    ];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    public function getTitle(): string
    {
        return 'Sitepackage: crop variants for contact photos';
    }

    public function getDescription(): string
    {
        return 'Adds the "card" and "detail" crop variants to contact photos that have none yet, '
            . 'as the whole image with a free ratio, so the frontend output stays unchanged.';
    }

    public function getPrerequisites(): array
    {
        return [
            DatabaseUpdatedPrerequisite::class,
        ];
    }

    public function updateNecessary(): bool
    {
        return $this->pendingRows() !== [];
    }

    public function executeUpdate(): bool
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        foreach ($this->pendingRows() as $uid => $crop) {
            foreach (self::VARIANTS as $variant) {
                $crop[$variant] ??= self::WHOLE_IMAGE;
            }
            $connection->update(
                self::TABLE,
                ['crop' => json_encode($crop, JSON_THROW_ON_ERROR)],
                ['uid' => $uid],
            );
        }
        return true;
    }

    /**
     * @return array<int, array<string, mixed>> crop data keyed by reference uid
     */
    private function pendingRows(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());
        $rows = $queryBuilder
            ->select('uid', 'crop')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter(self::FOREIGN_TABLE)),
                $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter(self::FIELD)),
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $pending = [];
        foreach ($rows as $row) {
            $crop = json_decode((string)$row['crop'], true);
            $crop = is_array($crop) ? $crop : [];
            foreach (self::VARIANTS as $variant) {
                if (!isset($crop[$variant])) {
                    $pending[(int)$row['uid']] = $crop;
                    break;
                }
            }
        }
        return $pending;
    }
}
