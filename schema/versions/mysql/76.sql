begin;

alter table ttrss_users add column twitter_oauth longtext;
alter table ttrss_users alter column twitter_oauth set default null;

update ttrss_version set schema_version = 76;

commit;
