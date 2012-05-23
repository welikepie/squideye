CREATE TABLE `userparams` (
  `user_id` int(11) NOT NULL default '0',
  `name` varchar(100) NOT NULL default '',
  `value` text,
  PRIMARY KEY  (`user_id`,`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;