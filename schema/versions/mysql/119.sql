begin;

update ttrss_prefs set def_value = 'Automatic' where pref_name = 'USER_TIMEZONE';

update ttrss_version set schema_version = 119;

commit;
