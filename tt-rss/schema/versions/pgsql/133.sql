begin;

alter table ttrss_feeds add column last_unconditional timestamp null;

UPDATE ttrss_version SET schema_version = 133;

commit;
