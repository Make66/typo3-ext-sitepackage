<?php

use Taketool\Sitepackage\Controller\TenantConfigModuleController;

return [
    'content_tenantconfig' => [
        'parent' => 'content',
        'access' => 'user',
        'path' => '/module/content/tenant-config',
        'iconIdentifier' => 'module-install-settings',
        'labels' => [
            'title' => 'LLL:EXT:sitepackage/Resources/Private/Language/locallang_mod_tenantconfig.xlf:title',
            'description' => 'LLL:EXT:sitepackage/Resources/Private/Language/locallang_mod_tenantconfig.xlf:description',
            'shortDescription' => 'LLL:EXT:sitepackage/Resources/Private/Language/locallang_mod_tenantconfig.xlf:short_description',
        ],
        'navigationComponent' => '@typo3/backend/tree/page-tree-element',
        'routes' => [
            '_default' => [
                'target' => TenantConfigModuleController::class . '::handleRequest',
            ],
        ],
    ],
];
