update ttrss_prefs set help_text = 'This option enables marking articles as read automatically in combined mode (except for Fresh articles feed) while you scroll article list.' where pref_name = 'CDM_AUTO_CATCHUP';

update ttrss_version set schema_version = 45;
