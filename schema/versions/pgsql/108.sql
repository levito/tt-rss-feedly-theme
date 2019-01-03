begin;

update ttrss_prefs set def_value = 'false' where pref_name = 'SORT_HEADLINES_BY_FEED_DATE';

update ttrss_version set schema_version = 108;

commit;
