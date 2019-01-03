insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('CDM_EXPANDED', 1, 'true', 'Automatically expand articles in combined mode',3);

update ttrss_version set schema_version = 24;
