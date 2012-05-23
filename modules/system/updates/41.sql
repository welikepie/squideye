CREATE TABLE `system_email_layouts` (
  `id` int(11) NOT NULL auto_increment,
  `code` varchar(100) default NULL,
  `content` text,
  `css` text,
  `name` varchar(255) default NULL,
  PRIMARY KEY  (`id`),
  KEY `code` (`code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;