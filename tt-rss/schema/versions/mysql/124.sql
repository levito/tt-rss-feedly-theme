begin;

alter table ttrss_users add column resetpass_token varchar(250);
alter table ttrss_users alter column resetpass_token set default null;

update ttrss_version set schema_version = 124;

commit;
