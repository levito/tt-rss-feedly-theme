insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('_DEFAULT_VIEW_ORDER_BY', 2, 'default', '', 1);

update ttrss_version set schema_version = 42;
