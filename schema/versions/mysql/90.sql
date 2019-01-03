begin;

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('_PREFS_SHOW_EMPTY_CATS', 1, 'false', '', 1);

update ttrss_version set schema_version = 90;

commit;
