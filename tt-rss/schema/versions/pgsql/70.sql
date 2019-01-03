alter table ttrss_users add column full_name varchar(250);
update ttrss_users set full_name = '';
alter table ttrss_users alter column full_name set not null;
alter table ttrss_users alter column full_name set default '';

update ttrss_version set schema_version = 70;
