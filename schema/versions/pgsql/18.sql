insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('_PREFS_ENABLE_PAGINATION', 2, '', '', 1);

update ttrss_version set schema_version = 18;
