begin;

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('USER_TIMEZONE', 2, 'UTC', 'User timezone', 1);

update ttrss_version set schema_version = 68;

commit;
