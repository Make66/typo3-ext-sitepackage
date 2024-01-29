<?php

/***************************************************************
 * Extension Manager/Repository config file for ext: "sitepackage"
 *
 *
 * Manual updates:
 * Only the data in the array - anything else is removed by next write.
 * "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = [
    'title' => 'Sitepackage',
    'description' => 'Container elements and other stuff',
    'category' => 'backend',
    'author' => 'Martin Keller',
    'author_email' => 'martin.keller@taketool.de',
    'state' => 'beta',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 1,
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-11.5.99',
            'container' => '1.0.0-2.9.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
