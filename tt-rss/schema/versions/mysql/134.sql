BEGIN;

ALTER TABLE ttrss_filters2_rules MODIFY reg_exp text not null;

UPDATE ttrss_version SET schema_version = 134;

COMMIT;
