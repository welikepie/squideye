alter table users add column password_restore_hash varchar(150);
create index password_restore_hash on users(password_restore_hash);