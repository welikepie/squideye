CREATE TABLE `db_files` (
  `id` int(11) NOT NULL auto_increment,
  `mime_type` varchar(255) default NULL,
  `size` int(11) default NULL,
  `description` varchar(255) default NULL,
  `master_object_class` varchar(255) default NULL,
  `master_object_id` int(11) default NULL,
  `created_at` datetime default NULL,
  `created_user_id` int(11) default NULL,
  `name` varchar(255) default NULL,
  `disk_name` varchar(255) default NULL,
  PRIMARY KEY  (`id`),
  KEY `master_index` (`master_object_class`,`master_object_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;