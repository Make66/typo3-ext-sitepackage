<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

ExtensionManagementUtility::addStaticFile('sitepackage', 'Configuration/TypoScript', 'Taketool Sitepackage');

// "Save + Close" hook
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['Backend\Template\Components\ButtonBar']['getButtonsHook'][] = 'Taketool\Sitepackage\Hooks\SaveCloseHook->addSaveCloseButton';
// "Save + View" hook
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['Backend\Template\Components\ButtonBar']['getButtonsHook'][] = 'Taketool\Sitepackage\Hooks\SaveShowHook->addSaveShowButton';
