begin;

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('ENABLE_API_ACCESS', 1, 'false', 'Enable external API', 3);

update ttrss_version set schema_version = 58;

commit;
