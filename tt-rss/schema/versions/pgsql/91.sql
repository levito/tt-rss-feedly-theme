begin;

update ttrss_prefs set help_text = 'Automatically open next feed with unread articles after marking one as read' where pref_name = 'ON_CATCHUP_SHOW_NEXT_FEED';

update ttrss_version set schema_version = 91;

commit;
