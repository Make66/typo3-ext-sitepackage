<?php

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

$versionInformation = GeneralUtility::makeInstance(Typo3Version::class);
// Only include page.tsconfig if TYPO3 version is below 12 so that it is not imported twice.
if ($versionInformation->getMajorVersion() < 12) {
    ExtensionManagementUtility::addPageTSConfig('
      @import "EXT:sitepackage/Configuration/TSConfig/page.tsconfig"
   ');
}
if ($versionInformation->getMajorVersion() < 12) {
    ExtensionManagementUtility::addTypoScriptSetup('
      @import "EXT:sitepackage/Configuration/TypoScript/setup.typoscript"
   ');
}