insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('VFEED_GROUP_BY_FEED', 1, 'false', 'Group headlines in virtual feeds',2,
	'When this option is enabled, headlines in Special feeds and Labels are grouped by feeds');

update ttrss_version set schema_version = 37;
