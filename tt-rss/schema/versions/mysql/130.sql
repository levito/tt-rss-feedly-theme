BEGIN;

alter table ttrss_feeds alter column last_updated set default null;

UPDATE ttrss_version SET schema_version = 130;

COMMIT;
