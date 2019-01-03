begin;

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('_MOBILE_BROWSE_CATS', 1, 'true', '', 1);

update ttrss_version set schema_version = 79;

commit;
