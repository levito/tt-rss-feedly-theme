begin;

update ttrss_prefs set short_desc = 'Hide images in articles' where pref_name = 'STRIP_IMAGES';

update ttrss_version set schema_version = 104;

commit;
