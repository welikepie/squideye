CREATE TABLE `db_record_locks` (
  `id` int(11) NOT NULL auto_increment,
  `created_at` datetime default NULL,
  `created_user_id` int(11) default NULL,
  `record_id` int(11) default NULL,
  `record_class` varchar(100) default NULL,
  PRIMARY KEY  (`id`),
  KEY `record_id, record_class` (`record_id`,`record_class`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;