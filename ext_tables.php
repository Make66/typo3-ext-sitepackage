<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

ExtensionManagementUtility::addStaticFile('sitepackage', 'Configuration/TypoScript', 'Taketool Sitepackage');
