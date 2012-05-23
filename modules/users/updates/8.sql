CREATE TABLE `user_permissions` (
  `id` int(11) NOT NULL auto_increment,
  `user_id` int(11) default NULL,
  `module_id` varchar(50) default NULL,
  `permission_name` varchar(100) default NULL,
  `value` varchar(255) default NULL,
  PRIMARY KEY  (`id`),
  KEY `user_id` (`user_id`),
  KEY `user_module_permission` (`user_id`,`module_id`,`permission_name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

alter table users add column shop_role_id int;