CREATE TABLE `core_versions` (
  `id` int(11) NOT NULL auto_increment,
  `moduleId` varchar(255) default NULL,
  `version` int(11) default NULL,
  `date` date default NULL,
  `version_str` varchar(50),
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `core_update_history` (
  `id` int(11) NOT NULL auto_increment,
  `date` date default NULL,
  `moduleId` varchar(255) default NULL,
  `version` int(11) default NULL,
  `description` text,
  `version_str` varchar(50),
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `core_install_history` (
  `id` int(11) NOT NULL auto_increment,
  `date` date default NULL,
  `moduleId` varchar(255) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;