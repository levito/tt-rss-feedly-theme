begin;

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('USER_STYLESHEET', 2, '', 'Customize stylesheet', 2, 'Customize CSS stylesheet to your liking');

update ttrss_version set schema_version = 77;

commit;
