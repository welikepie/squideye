CREATE TABLE `groups` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `groups_users` (
  `user_id` int(11) default NULL,
  `group_id` int(11) default NULL,
  KEY `group_id` (`group_id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `users` (
  `id` int(11) NOT NULL auto_increment,
  `login` varchar(30) NOT NULL default '',
  `firstName` varchar(255) default NULL,
  `lastName` varchar(255) default NULL,
  `password` varchar(255) default NULL,
  `email` varchar(50) default NULL,
  `timeZone` varchar(255) default NULL,
  `middleName` varchar(255) default NULL,
  `status` int(11) default NULL,
  `created_user_id` int(11) default NULL,
  `updated_user_id` int(11) default NULL,
  `created_at` datetime default NULL,
  `updated_at` datetime default NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `login` (`login`),
  KEY `firstName` (`firstName`),
  KEY `lastName` (`lastName`),
  KEY `email` (`email`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `rights` (
  `group_id` int(11) NOT NULL default '0',
  `module` varchar(50) NOT NULL default '',
  `resource` varchar(50) NOT NULL default '',
  `object` varchar(50) NOT NULL default '',
  `value` int(11) default NULL,
  PRIMARY KEY  (`group_id`,`module`,`resource`,`object`),
  KEY `module` (`module`),
  KEY `group_id` (`group_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;