<?php

declare(strict_types=1);

namespace Taketool\Sitepackage\Controller;

use Psr\Http\Message\ResponseInterface;
use Taketool\Sitepackage\Domain\Model\Contact;
use Taketool\Sitepackage\Domain\Repository\ContactRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class ContactController extends ActionController
{
    public function __construct(
        private readonly ContactRepository $contactRepository,
    ) {}

    public function indexAction(): ResponseInterface
    {
        $contacts = $this->listSource() === 'categories'
            ? $this->contactRepository->findByAnyCategory($this->uidList('categories'))
            : $this->contactRepository->findByUidsInOrder($this->uidList('persons'));
        $displayMode = ($this->settings['displayMode'] ?? 'single') === 'multiple' ? 'multiple' : 'single';

        $this->view->assignMultiple([
            'contacts' => $contacts,
            'displayMode' => $displayMode,
            'displayItems' => $this->displayItems(),
            'singleViewPage' => (int)($this->settings['singleViewPage'] ?? 0),
        ]);

        return $this->htmlResponse();
    }

    public function showAction(?Contact $contact = null): ResponseInterface
    {
        $pinnedList = GeneralUtility::trimExplode(',', (string)($this->settings['contact'] ?? ''), true);
        $pinnedUid = (int)($pinnedList[0] ?? 0);
        if ($pinnedUid > 0) {
            $contact = $this->contactRepository->findByUid($pinnedUid);
        }

        if ($contact === null) {
            throw new \RuntimeException('Kein Ansprechpartner angegeben.', 1759600100);
        }

        $this->view->assignMultiple([
            'contact' => $contact,
            'displayItems' => $this->displayItems(),
            'backToListPage' => (int)($this->settings['backToListPage'] ?? 0),
        ]);

        return $this->htmlResponse();
    }

    /**
     * @return 'persons'|'categories'
     */
    private function listSource(): string
    {
        $source = (string)($this->settings['listSource'] ?? '');
        if ($source === 'persons' || $source === 'categories') {
            return $source;
        }

        return $this->uidList('categories') !== [] ? 'categories' : 'persons';
    }

    /**
     * @return list<int>
     */
    private function uidList(string $settingName): array
    {
        $uids = array_map('intval', GeneralUtility::trimExplode(',', (string)($this->settings[$settingName] ?? ''), true));

        return array_values(array_filter($uids, static fn(int $uid): bool => $uid > 0));
    }

    /**
     * @return array<string, bool>
     */
    private function displayItems(): array
    {
        $keys = ['salutation', 'position', 'department', 'room', 'address', 'phone', 'email'];
        $selected = GeneralUtility::trimExplode(',', (string)($this->settings['displayItems'] ?? ''), true);

        $items = [];
        foreach ($keys as $key) {
            $items[$key] = $selected === [] || in_array($key, $selected, true);
        }

        return $items;
    }
}
