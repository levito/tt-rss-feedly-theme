begin;

update ttrss_prefs set def_value = 'false' where pref_name = 'AUTO_ASSIGN_LABELS';

update ttrss_version set schema_version = 121;

commit;
