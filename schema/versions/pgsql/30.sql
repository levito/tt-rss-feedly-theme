alter table ttrss_feeds add column last_update_started timestamp;
alter table ttrss_feeds alter column last_update_started set default null;

update ttrss_version set schema_version = 30;
