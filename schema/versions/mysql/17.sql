insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('_PREFS_ACTIVE_TAB', 2, '', '', 1);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('_INFOBOX_DISABLE_OVERLAY', 1, 'false', '', 1);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('STRIP_UNSAFE_TAGS', 1, 'true', 'Strip unsafe tags from articles', 3,
'Strip all but most common HTML tags when reading articles.');

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('BLACKLISTED_TAGS', 2, 'main, generic, misc', 'Blacklisted tags', 3,
'When auto-detecting tags in articles these tags will not be applied (comma-separated list).');

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('ENABLE_SEARCH_TOOLBAR', 1, 'false', 'Enable search toolbar',2);

update ttrss_version set schema_version = 17;
