CREATE TABLE `system_email_templates` (
  `id` int(11) NOT NULL auto_increment,
  `code` varchar(100) default NULL,
  `subject` varchar(255) default NULL,
  `content` text,
  `description` text,
  PRIMARY KEY  (`id`),
  KEY `code` (`code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

insert into system_email_templates(code, subject, content, description) values('shop:registration_confirmation', 'Confirmation', '<p>Dear {customer_name}!</p>
<p>Thank you for registering. Please use the following email and password to login:<br /> email:&nbsp;{customer_email}<br /> password: {customer_password}</p>', 'This message is sent to a customer after successful registration.');