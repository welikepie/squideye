CREATE TABLE `trace_log` (
  `id` int(11) NOT NULL auto_increment,
  `log` varchar(255) default NULL,
  `message` text,
  `record_datetime` datetime default NULL,
  PRIMARY KEY  (`id`),
  KEY `log` (`log`)
) ENGINE=MyISAM AUTO_INCREMENT=16 DEFAULT CHARSET=utf8;