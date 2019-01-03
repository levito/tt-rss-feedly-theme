alter table ttrss_feeds add column last_viewed datetime;
alter table ttrss_feeds alter column last_viewed set default null;

update ttrss_version set schema_version = 27;
