CREATE TABLE `db_deferred_bindings` (
  `id` int(11) NOT NULL auto_increment,
  `master_class_name` varchar(100) default NULL,
  `detail_class_name` varchar(100) default NULL,
  `master_relation_name` varchar(100) default NULL,
  `is_bind` int(11) default NULL,
  `detail_key_value` int(11) default NULL,
  `created_at` datetime default NULL,
  `session_key` varchar(255) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;