BEGIN;

ALTER table ttrss_filters2_rules ADD COLUMN match_on TEXT;

UPDATE ttrss_version SET schema_version = 131;

COMMIT;
