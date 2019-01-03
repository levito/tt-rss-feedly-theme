begin;

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,access_level) values('AUTO_ASSIGN_LABELS', 1, 'true', 'Assign articles to labels automatically', 3, 1);

update ttrss_version set schema_version = 98;

commit;
