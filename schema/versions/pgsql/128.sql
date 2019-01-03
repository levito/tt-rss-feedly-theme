BEGIN;

alter table ttrss_entries add column tsvector_combined tsvector;
create index ttrss_entries_tsvector_combined_idx on ttrss_entries using gin(tsvector_combined);

alter table ttrss_feeds add column feed_language varchar(100);
update ttrss_feeds set feed_language = '';
alter table ttrss_feeds alter column feed_language set not null;
alter table ttrss_feeds alter column feed_language set default '';

UPDATE ttrss_version SET schema_version = 128;

COMMIT;
