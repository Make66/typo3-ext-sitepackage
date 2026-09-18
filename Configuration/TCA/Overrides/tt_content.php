<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

if (!defined('TYPO3')) {
    die('Access denied.');
}

call_user_func(function () {
    $ll = 'LLL:EXT:sitepackage/Resources/Private/Language/locallang_db.xlf:';

    // Hero content element: header/subheader/bodytext/CTA over a selectable
    // background (image or color), with independent width (bootstrap_package's
    // own frame_layout: full-bleed background vs. boxed/contained) and height
    // (flat/normal/big, reusing bootstrap_package's frame-height-* mechanism)
    // options. See bk2k:frame usage in Resources/Private/Templates/ContentElements/Hero.html.
    ExtensionManagementUtility::addTcaSelectItemGroup(
        'tt_content',
        'CType',
        'sitepackage',
        $ll . 'group.sitepackage',
        'after:bootstrap_package'
    );

    ExtensionManagementUtility::addTcaSelectItem(
        'tt_content',
        'CType',
        [
            'label' => $ll . 'content_element.hero',
            'description' => $ll . 'content_element.hero.description',
            'value' => 'sitepackage_hero',
            'icon' => 'content-sitepackage-hero',
            'group' => 'sitepackage',
        ],
        'textmedia',
        'after'
    );

    $GLOBALS['TCA']['tt_content']['ctrl']['typeicon_classes']['sitepackage_hero'] = 'content-sitepackage-hero';

    $GLOBALS['TCA']['tt_content']['columns']['tx_sitepackage_hero_height'] = [
        'exclude' => true,
        'label' => $ll . 'field.hero_height',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'items' => [
                ['label' => $ll . 'field.hero_height.flat', 'value' => 'flat'],
                ['label' => $ll . 'field.hero_height.normal', 'value' => 'normal'],
                ['label' => $ll . 'field.hero_height.big', 'value' => 'big'],
            ],
            'default' => 'normal',
        ],
        'l10n_mode' => 'exclude',
    ];

    $GLOBALS['TCA']['tt_content']['palettes']['sitepackage_heroAppearance'] = [
        'label' => $ll . 'palette.hero_appearance',
        'showitem' => '
            frame_layout, tx_sitepackage_hero_height,
            --linebreak--,
            background_color_class,
            --linebreak--,
            background_image, background_image_options
        ',
    ];

    $GLOBALS['TCA']['tt_content']['types']['sitepackage_hero'] = [
        'showitem' => '
            --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,
                header, --linebreak--, header_layout, header_position, --linebreak--, subheader,
                --linebreak--, header_link, readmore_label,
                bodytext,
            --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:tabs.appearance,
                --palette--;' . $ll . 'palette.hero_appearance;sitepackage_heroAppearance,
                space_before_class, space_after_class,
            --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:language,
                --palette--;;language,
            --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
                --palette--;;hidden,
                --palette--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:palette.access;access,
            --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:categories,
                categories,
            --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:notes,
                rowDescription,
            --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:extended,
        ',
        'columnsOverrides' => [
            'bodytext' => [
                'label' => 'LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:bodytext_formlabel',
                'config' => [
                    'enableRichtext' => true,
                ],
            ],
            'header_link' => [
                'label' => $ll . 'field.hero_cta_link',
            ],
            'readmore_label' => [
                'label' => $ll . 'field.hero_cta_label',
            ],
            // Reuse bootstrap_package's frame_layout field as the hero's width
            // switch instead of introducing a second field: 'default' already
            // renders the background edge-to-edge with the text column boxed
            // (see Frame/Index.html - .frame-group-container is unconstrained
            // unless frame-layout-embedded), 'embedded' already boxes the whole
            // frame including the background into the site's content width.
            // Same underlying values/CSS as every other CE's "Rahmen" field,
            // just relabelled here for the hero's own vocabulary.
            'frame_layout' => [
                'label' => $ll . 'field.hero_width',
                'config' => [
                    'items' => [
                        ['label' => $ll . 'field.hero_width.full', 'value' => 'default'],
                        ['label' => $ll . 'field.hero_width.content', 'value' => 'embedded'],
                    ],
                ],
            ],
        ],
    ];
});
