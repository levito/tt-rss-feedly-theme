BEGIN;

ALTER TABLE ttrss_filters2_rules ALTER COLUMN reg_exp TYPE text;

UPDATE ttrss_version SET schema_version = 134;

COMMIT;
