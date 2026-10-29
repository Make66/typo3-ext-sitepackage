<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

if (!defined('TYPO3')) {
    die('Access denied.');
}

call_user_func(function () {
    $ll = 'LLL:EXT:sitepackage/Resources/Private/Language/locallang_db.xlf:';

    // Category color, used by the calendarize month view (event sublines and
    // category filter buttons), see Documentation/Calendarize.md.
    // The DB column is generated from the TCA type "color".
    $GLOBALS['TCA']['sys_category']['columns']['tx_sitepackage_color'] = [
        'exclude' => true,
        'label' => $ll . 'field.category_color',
        'description' => $ll . 'field.category_color.description',
        'config' => [
            'type' => 'color',
            'size' => 10,
        ],
        'l10n_mode' => 'exclude',
    ];

    ExtensionManagementUtility::addToAllTCAtypes('sys_category', 'tx_sitepackage_color', '', 'after:title');
});
