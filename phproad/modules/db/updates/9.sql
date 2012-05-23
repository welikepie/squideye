alter table db_record_locks add column non_db_hash varchar(100);
create index non_db_hash on db_record_locks(non_db_hash);