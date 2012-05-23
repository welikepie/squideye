alter table system_backup_settings add column backup_on_login tinyint(4);
update system_backup_settings set backup_on_login = if (backup_interval <> 0, 1, 0);
alter table system_backup_settings add column archive_uploaded_dir tinyint(4) default 1;