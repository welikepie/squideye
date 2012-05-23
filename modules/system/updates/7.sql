CREATE TABLE `system_login_log` (
  `id` int(11) NOT NULL auto_increment,
  `user_id` int(11) default NULL,
  `created_at` datetime default NULL,
  `ip` varchar(15) default NULL,
  PRIMARY KEY  (`id`),
  KEY `user_id` (`user_id`),
  KEY `created_at` (`created_at`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;