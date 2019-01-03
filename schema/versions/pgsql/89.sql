begin;

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('DIGEST_PREFERRED_TIME', 2, '00:00', 'Try to send digests around specified time', 1, 'Uses UTC timezone');

update ttrss_version set schema_version = 89;

commit;
