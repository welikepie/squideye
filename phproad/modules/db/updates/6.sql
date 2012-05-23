alter table db_files add column field varchar (100);
create index file_filed on db_files(field);