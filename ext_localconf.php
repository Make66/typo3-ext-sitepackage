<?php

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

if (!defined('TYPO3')) {
    die('Access denied.');
}

$GLOBALS['TYPO3_CONF_VARS']['SYS']['textfile_ext'] = 'txt,ts,typoscript,html,htm,css,scss,tmpl,js,sql,xml,csv,xlf,yaml,yml';

$versionInformation = GeneralUtility::makeInstance(Typo3Version::class);
// Only include page.tsconfig if TYPO3 version is below 12 so that it is not imported twice.
if ($versionInformation->getMajorVersion() < 12) {
    ExtensionManagementUtility::addTypoScriptConstants('
      @import "EXT:sitepackage/Configuration/TypoScript/constants.typoscript"
   ');
    ExtensionManagementUtility::addTypoScriptSetup('
      @import "EXT:sitepackage/Configuration/TypoScript/setup.typoscript"
   ');
    ExtensionManagementUtility::addPageTSConfig('
      @import "EXT:sitepackage/Configuration/page.tsconfig"
   ');
}

// Override bootstrap_package's "bootstrap" RTE preset (same preset name, own
// file) to remove toolbar buttons with no backing CKEditor5 plugin - see
// Configuration/RTE/Default.yaml for details. Requires sitepackage to load
// after bootstrap_package, enforced via the 'bootstrap_package' dependency
// in ext_emconf.php.
$GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets']['bootstrap'] = 'EXT:sitepackage/Configuration/RTE/Default.yaml';