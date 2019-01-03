begin;

alter table ttrss_user_entries add column uuid varchar(200);
update ttrss_user_entries set uuid = '';
alter table ttrss_user_entries change uuid uuid varchar(200) not null;

update ttrss_version set schema_version = 86;

commit;
