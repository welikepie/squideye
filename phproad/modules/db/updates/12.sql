alter table db_files add column sort_order int;
update db_files set sort_order = id;