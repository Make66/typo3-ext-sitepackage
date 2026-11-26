<?php

declare(strict_types=1);

namespace Taketool\Sitepackage\TenantConfig;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;

#[Autoconfigure(public: true)]
final readonly class TenantConfigRepository
{
    public const TABLE = 'tx_sitepackage_tenantconfig';

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    public function findByRootPageId(int $rootPageId, int $excludeUid = 0): ?array
    {
        if ($rootPageId <= 0) {
            return null;
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());
        $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($rootPageId, Connection::PARAM_INT)))
            ->orderBy('uid')
            ->setMaxResults(1);
        if ($excludeUid > 0) {
            $queryBuilder->andWhere($queryBuilder->expr()->neq('uid', $queryBuilder->createNamedParameter($excludeUid, Connection::PARAM_INT)));
        }
        $row = $queryBuilder->executeQuery()->fetchAssociative();
        return $row === false ? null : $row;
    }

    /**
     * @return array<string, mixed>
     */
    public function findOverridesByRootPageId(int $rootPageId): array
    {
        $row = $this->findByRootPageId($rootPageId);
        return $row === null ? [] : self::decodeSettings($row['settings'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    public static function decodeSettings(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || $value === '') {
            return [];
        }
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
        return is_array($decoded) ? $decoded : [];
    }
}
