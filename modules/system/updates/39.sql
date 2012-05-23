CREATE TABLE `system_compound_email_vars` (
  `id` int(11) NOT NULL auto_increment,
  `code` varchar(50) default NULL,
  `content` text,
  `scope` varchar(50) default NULL,
  `description` varchar(255) default NULL,
  PRIMARY KEY  (`id`),
  KEY `code` (`code`),
  KEY `scope` (`scope`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;