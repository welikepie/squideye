CREATE TABLE `system_colortheme_settings` (
  `id` int(11) NOT NULL auto_increment,
  `logo_border` tinyint(4) default NULL,
  `header_text` varchar(100) default NULL,
  `theme_id` varchar(30) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

insert into system_colortheme_settings(theme_id) values ('blue');