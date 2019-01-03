begin;

update ttrss_prefs set def_value = 'true' where pref_name = 'COMBINED_DISPLAY_MODE';

update ttrss_version set schema_version = 111;

commit;
