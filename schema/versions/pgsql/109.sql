begin;

update ttrss_prefs set short_desc = 'Hide feeds with no unread articles' where pref_name = 'HIDE_READ_FEEDS';

update ttrss_version set schema_version = 109;

commit;
