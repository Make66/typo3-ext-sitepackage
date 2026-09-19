CREATE TABLE tt_content (
    tx_sitepackage_hero_height VARCHAR(16) DEFAULT 'normal' NOT NULL,
    tx_sitepackage_linkbox_item int(11) unsigned DEFAULT '0'
);

CREATE TABLE tx_sitepackage_linkbox_item (
    tt_content int(11) unsigned DEFAULT '0',
    header varchar(255) DEFAULT '' NOT NULL,
    link varchar(1024) DEFAULT '' NOT NULL,
    icon_set varchar(255) DEFAULT '' NOT NULL,
    icon_identifier varchar(255) DEFAULT '' NOT NULL,
    icon_file int(11) unsigned DEFAULT '0'
);
