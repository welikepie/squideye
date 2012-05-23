CREATE TABLE `moduleparams` (
  `module_id` varchar(30) NOT NULL default '0',
  `name` varchar(100) NOT NULL default '',
  `value` text,
  PRIMARY KEY  (`module_id`,`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;