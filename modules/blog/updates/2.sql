CREATE TABLE `blog_comment_statuses` (
  `id` int(11) NOT NULL auto_increment,
  `code` varchar(30) default NULL,
  `name` varchar(50) default NULL,
  PRIMARY KEY  (`id`),
  KEY `code` (`code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

insert into blog_comment_statuses(code, name) values ('new', 'New');
insert into blog_comment_statuses(code, name) values ('approved', 'Approved');
insert into blog_comment_statuses(code, name) values ('deleted', 'Deleted');