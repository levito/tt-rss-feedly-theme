begin;

alter table ttrss_feeds add column favicon_last_checked datetime;
alter table ttrss_feeds alter column favicon_last_checked set default null;

update ttrss_version set schema_version = 92;

commit;
