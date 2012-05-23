CREATE TABLE `system_backup_settings` (
  `id` int(11) NOT NULL auto_increment,
  `backup_path` varchar(255) default NULL,
  `backup_interval` int(11) default NULL,
  `num_files_to_keep` int(11) default NULL,
  `notify_administrators` tinyint,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

insert into system_backup_settings(id) values (0);