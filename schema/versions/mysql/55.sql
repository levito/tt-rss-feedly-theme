begin;

alter table ttrss_user_entries add column note text;

update ttrss_version set schema_version = 55;

commit;
