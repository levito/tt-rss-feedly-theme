begin;

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('ENABLE_OFFLINE_READING', 1, 'false', 'Enable offline reading',1,	'Synchronize new articles for offline reading using Google Gears.');

update ttrss_version set schema_version = 54;

commit;
