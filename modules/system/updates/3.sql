CREATE TABLE `system_backup_archives` (
  `id` int(11) NOT NULL auto_increment,
  `path` varchar(255) default NULL,
  `created_at` datetime default NULL,
  `created_user_id` int(11) default NULL,
  `status_id` int(11) default NULL,
  `comment` text,
  `error_message` text,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `system_backup_status` (
  `id` int(11) NOT NULL,
  `name` varchar(50) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

insert into system_backup_status(id, name) values (1, 'In progress...');
insert into system_backup_status(id, name) values (2, 'OK');
insert into system_backup_status(id, name) values (3, 'Error');