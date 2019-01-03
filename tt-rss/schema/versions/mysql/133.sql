begin;

alter table ttrss_feeds add column last_unconditional datetime null;

UPDATE ttrss_version SET schema_version = 133;

commit;
