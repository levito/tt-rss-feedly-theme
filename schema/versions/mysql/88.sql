begin;

alter table ttrss_users add column salt varchar(250);
update ttrss_users set salt = '';
alter table ttrss_users change salt salt varchar(250) not null;
alter table ttrss_users alter column salt set default '';

update ttrss_version set schema_version = 88;

commit;
