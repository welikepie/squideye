CREATE TABLE `blog_comment_subscribers` (
  `id` int(11) NOT NULL auto_increment,
  `post_id` int(11) default NULL,
  `email` varchar(100) default NULL,
  `subscriber_name` varchar(255) default NULL,
  `email_hash` varchar(100) default NULL,
  PRIMARY KEY  (`id`),
  KEY `post_id` (`post_id`),
  KEY `email` (`email`),
  KEY `email_hash` (`email_hash`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;