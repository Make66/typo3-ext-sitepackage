<?php

use Taketool\Sitepackage\TenantConfig\TenantConfigLabel;

return [
    'ctrl' => [
        'title' => 'LLL:EXT:sitepackage/Resources/Private/Language/locallang_tenantconfig.xlf:table',
        'label' => 'uid',
        'label_userFunc' => TenantConfigLabel::class . '->getTitle',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'rootLevel' => 0,
        'hideAtCopy' => true,
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
        'typeicon_classes' => [
            'default' => 'module-install-settings',
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => 'settings',
        ],
    ],
    'columns' => [
        'settings' => [
            'label' => 'LLL:EXT:sitepackage/Resources/Private/Language/locallang_tenantconfig.xlf:field.settings',
            'config' => [
                'type' => 'json',
                'renderType' => 'tenantSiteSettings',
            ],
        ],
    ],
];
