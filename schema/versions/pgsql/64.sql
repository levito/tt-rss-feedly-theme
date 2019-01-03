begin;

update ttrss_prefs set type_id = 2 where pref_name = '_THEME_ID';

update ttrss_version set schema_version = 64;

commit;
