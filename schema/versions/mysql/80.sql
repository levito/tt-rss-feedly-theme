begin;

update ttrss_prefs set section_id = 3, 
	short_desc = 'Automatically mark articles as read',
	help_text = 'Mark articles as read automatically while you scroll article list.' where pref_name = 'CDM_AUTO_CATCHUP';

update ttrss_version set schema_version = 80;

commit;
