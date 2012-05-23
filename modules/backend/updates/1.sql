CREATE TABLE `report_dates` (
  `report_date` date NOT NULL,
  `year` int(11) default NULL,
  `month` int(11) default NULL,
  `day` int(11) default NULL,
  `month_start` date default NULL,
  `month_code` char(10) default NULL,
  `month_end` varchar(30) default NULL,
  `year_start` varchar(30) default NULL,
  `year_end` varchar(30) default NULL,
  PRIMARY KEY  (`report_date`),
  KEY `month_code` (`month_code`),
  KEY `year` (`year`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;