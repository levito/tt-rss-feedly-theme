UPDATE ttrss_prefs SET def_value = 'main, generic, misc, uncategorized, blog, blogroll, general, news' WHERE pref_name = 'BLACKLISTED_TAGS';

update ttrss_version set schema_version = 28;
