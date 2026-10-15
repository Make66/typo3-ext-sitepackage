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
        $uids = array_map('intval', GeneralUtility::trimExplode(',', (string)($this->settings['persons'] ?? ''), true));
        $displayMode = ($this->settings['displayMode'] ?? 'single') === 'multiple' ? 'multiple' : 'single';

        $this->view->assignMultiple([
            'contacts' => $this->contactRepository->findByUidsInOrder($uids),
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
     * @return array<string, bool>
     */
    private function displayItems(): array
    {
        $keys = ['salutation', 'position', 'department', 'room', 'phone', 'email'];
        $selected = GeneralUtility::trimExplode(',', (string)($this->settings['displayItems'] ?? ''), true);

        $items = [];
        foreach ($keys as $key) {
            $items[$key] = $selected === [] || in_array($key, $selected, true);
        }

        return $items;
    }
}
