<?php

defined('TYPO3') or die();

foreach (['nacht', 'salbei', 'orange'] as $apicodoTheme) {
    $GLOBALS['TCA']['be_users']['columns']['user_settings']['columns']['theme']['config']['items'][] = [
        'label' => 'LLL:EXT:sitepackage/Resources/Private/Language/locallang_be.xlf:theme.apicodo-' . $apicodoTheme,
        'value' => 'apicodo-' . $apicodoTheme,
    ];
}
unset($apicodoTheme);
