CREATE TABLE `blog_posts_categories` (
  `blog_post_id` int(11) NOT NULL default '0',
  `blog_category_id` int(11) NOT NULL default '0',
  PRIMARY KEY  (`blog_post_id`,`blog_category_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

insert into blog_posts_categories(blog_post_id, blog_category_id) select id, category_id from blog_posts;