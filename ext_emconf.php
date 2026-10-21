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
    'description' => 'Sitepackage for t3v13+14 on Bootstrap-Package by Benjamin Kott',
    'category' => 'backend',
    'author' => 'Martin Keller',
    'author_email' => 'martin.keller@taketool.de',
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 1,
    'version' => '13.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.3.0-13.4.99',
            'container' => '4.1.0-4.1.99',
            'bootstrap_package' => '16.0.0-16.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
