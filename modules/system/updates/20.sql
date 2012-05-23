alter table system_mail_settings add column smtp_port int;
update system_mail_settings set smtp_port=25;
	
alter table system_mail_settings add column smtp_ssl tinyint;
