begin;

update ttrss_prefs set short_desc = 'Default feed update interval' where pref_name = 'DEFAULT_UPDATE_INTERVAL';

update ttrss_version set schema_version = 65;

commit;
