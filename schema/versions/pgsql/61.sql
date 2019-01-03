begin;

update ttrss_prefs set short_desc = 'Amount of articles to display at once', help_text = '', def_value = '30' where pref_name = 'DEFAULT_ARTICLE_LIMIT';

update ttrss_user_prefs set value = '30' where pref_name = 'DEFAULT_ARTICLE_LIMIT' and value = '0';

update ttrss_version set schema_version = 61;

commit;
