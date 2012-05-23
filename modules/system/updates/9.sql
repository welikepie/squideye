CREATE TABLE `system_htmleditor_config` (
  `id` int(11) NOT NULL auto_increment,
  `code` varchar(100) default NULL,
  `controls_row_1` text,
  `controls_row_2` text,
  `controls_row_3` text,
  `content_css` varchar(255) default NULL,
  `block_formats` text,
  `custom_styles` text,
  `font_sizes` text,
  `font_colors` text,
  `background_colors` text,
  `allow_more_colors` tinyint(4) default NULL,
  `module` varchar(100) default NULL,
  `description` text,
  PRIMARY KEY  (`id`),
  KEY `code_module` (`code`,`module`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;