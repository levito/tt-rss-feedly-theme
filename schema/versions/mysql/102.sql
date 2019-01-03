begin;

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('_MOBILE_REVERSE_HEADLINES', 1, 'false', '', 1);

update ttrss_version set schema_version = 102;

commit;
