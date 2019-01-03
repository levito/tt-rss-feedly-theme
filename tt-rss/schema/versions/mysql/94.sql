begin;

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('_DEFAULT_INCLUDE_CHILDREN', 1, 'false', '', 1);

update ttrss_version set schema_version = 94;

commit;
