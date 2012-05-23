CREATE TABLE `core_applied_updates` (
  `id` int(11) NOT NULL auto_increment,
  `module_id` varchar(255) default NULL,
  `update_id` varchar(50) default NULL,
  `created_at` datetime default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;