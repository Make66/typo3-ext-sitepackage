<?php

declare(strict_types=1);

namespace Taketool\Sitepackage\Domain\Repository;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\Repository;

class ContactRepository extends Repository
{
    protected $defaultOrderings = ['lastName' => 'ASC', 'firstName' => 'ASC'];

    public function initializeObject(): void
    {
        $querySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);
        $querySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * Returns the matching records in the given uid order (the editor's own
     * pick order in the FlexForm picker), not the query's default ordering -
     * `IN()` does not preserve argument order, so the result is re-sorted in
     * PHP afterwards.
     *
     * @param list<int> $uids
     * @return list<\Taketool\Sitepackage\Domain\Model\Contact>
     */
    public function findByUidsInOrder(array $uids): array
    {
        if ($uids === []) {
            return [];
        }

        $query = $this->createQuery();
        $query->matching($query->in('uid', $uids));
        $byUid = [];
        foreach ($query->execute() as $contact) {
            $byUid[$contact->getUid()] = $contact;
        }

        $ordered = [];
        foreach ($uids as $uid) {
            if (isset($byUid[$uid])) {
                $ordered[] = $byUid[$uid];
            }
        }

        return $ordered;
    }
}
