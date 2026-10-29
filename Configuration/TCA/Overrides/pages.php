<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

if (!defined('TYPO3')) {
    die('Access denied.');
}

// "Contains plugin" value for Configuration/Routes/Contact.yaml, see
// Documentation/RouteEnhancers.md (calendarize and news ship their own)
ExtensionManagementUtility::addTcaSelectItem('pages', 'module', [
    'label' => 'LLL:EXT:sitepackage/Resources/Private/Language/locallang_db.xlf:pages.module.contact',
    'value' => 'contact',
    'icon' => 'sitepackage-contact',
]);
