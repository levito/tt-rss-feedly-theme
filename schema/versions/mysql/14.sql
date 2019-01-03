insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('CDM_AUTO_CATCHUP', 1, 'false', 'Mark articles as read automatically',2,
'This option enables marking articles as read automatically in combined mode while you scroll article list.');

update ttrss_version set schema_version = 14;
