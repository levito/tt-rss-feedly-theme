begin;

update ttrss_prefs set def_value = 'false' where pref_name = 'ALLOW_DUPLICATE_POSTS';

update ttrss_version set schema_version = 116;

commit;
