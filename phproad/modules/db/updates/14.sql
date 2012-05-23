CREATE TABLE `db_saved_tickets` (
  `ticket_id` varchar(50) NOT NULL default '',
  `ticket_data` text,
  `created_at` datetime default NULL,
  PRIMARY KEY  (`ticket_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;