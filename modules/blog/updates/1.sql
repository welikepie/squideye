CREATE TABLE `blog_posts` (
  `id` int(11) NOT NULL auto_increment,
  `created_at` datetime default NULL,
  `updated_at` datetime default NULL,
  `created_user_id` int(11) default NULL,
  `updated_user_id` int(11) default NULL,
  `title` varchar(255) default NULL,
  `description` text,
  `content` text,
  `published_date` datetime default NULL,
  `is_published` tinyint(4) default NULL,
  `category_id` int(11) default NULL,
  `url_title` varchar(255) default NULL,
  `comments_allowed` tinyint(4) default NULL,
  PRIMARY KEY  (`id`),
  KEY `url_title` (`url_title`),
  KEY `category_id` (`category_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `blog_categories` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) default NULL,
  `url_name` varchar(255) default NULL,
  `description` text,
  `created_at` datetime default NULL,
  `updated_at` datetime default NULL,
  `created_user_id` int(11) default NULL,
  `updated_user_id` int(11) default NULL,
  PRIMARY KEY  (`id`),
  KEY `url_name` (`url_name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `blog_comments` (
  `id` int(11) NOT NULL auto_increment,
  `created_at` datetime default NULL,
  `author_name` varchar(255) default NULL,
  `post_id` int(11) default NULL,
  `content` text,
  `author_email` varchar(50) default NULL,
  `status_id` int(11) default NULL,
  `content_html` text,
  `blog_owner_comment` tinyint(4) default NULL,
  `author_ip` varchar(15) default NULL,
  PRIMARY KEY  (`id`),
  KEY `post_id` (`post_id`),
  KEY `author_ip` (`author_ip`),
  KEY `status_id` (`status_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;