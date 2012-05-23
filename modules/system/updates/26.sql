alter table system_mail_settings 
add column send_mode varchar(20);

update system_mail_settings set send_mode=if(length(smtp_address) > 0, 'smtp', 'mail');