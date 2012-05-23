ALTER TABLE groups
ADD COLUMN code varchar(100);

insert into groups(code, name, description) values ('administrator', 'Administrator', 'Administrators can manage user accounts, system settings and any other objects in the system.');