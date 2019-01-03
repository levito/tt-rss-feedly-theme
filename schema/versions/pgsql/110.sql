begin;

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('USER_CSS_THEME', 2, '', 'Select theme', 2, 'Select one of the available CSS themes');

update ttrss_version set schema_version = 110;

commit;
