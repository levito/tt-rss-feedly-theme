BEGIN;

DROP INDEX ttrss_entries_guid_index;
DROP INDEX ttrss_prefs_pref_name_idx;

UPDATE ttrss_version SET schema_version = 127;

COMMIT;
