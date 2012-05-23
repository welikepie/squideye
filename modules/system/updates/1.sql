CREATE TABLE `system_mail_settings` (
  `id` int(11) NOT NULL auto_increment,
  `smtp_address` varchar(255) default NULL,
  `smtp_authorization` tinyint(4) default NULL,
  `smtp_user` varchar(50) default NULL,
  `smtp_password` varchar(255) default NULL,
  `sender_name` varchar(50) default NULL,
  `sender_email` varchar(50) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

insert into system_mail_settings(sender_name) values ('LemonStand');