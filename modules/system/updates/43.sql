alter table system_email_templates 
	add column reply_to_mode varchar(10) default 'default',
	add column reply_to_address varchar(100);
	
update system_email_templates set reply_to_mode='sender' where code='shop:order_note_internal';