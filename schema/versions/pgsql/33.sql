insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('HIDE_FEEDLIST', 1, 'false', 'Hide feedlist',2, 'This option hides feedlist and allows it to be toggled on the fly, useful for small screens.');

update ttrss_version set schema_version = 33;
