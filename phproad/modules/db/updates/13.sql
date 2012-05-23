CREATE TABLE `db_session_data` (
  `id` int(11) NOT NULL auto_increment,
  `session_id` varchar(100) default NULL,
  `session_data` text,
  `created_at` datetime default NULL,
  `client_ip` varchar(15) default NULL,
  PRIMARY KEY  (`id`),
  KEY `session_id` (`session_id`,`client_ip`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;