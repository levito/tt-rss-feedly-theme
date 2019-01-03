begin;

insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('USER_LANGUAGE', 2, '', 2);

update ttrss_version set schema_version = 120;

commit;
