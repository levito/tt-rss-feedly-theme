insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('SYNC_COUNTERS', 1, 'false', 'Prefer more accurate feedlist counters to UI speed',3);

update ttrss_version set schema_version = 38;
