begin;

ALTER TABLE ttrss_feeds ADD COLUMN favicon_avg_color VARCHAR(11);
alter table ttrss_feeds alter column favicon_avg_color set default null;

update ttrss_version set schema_version = 117;

commit;
