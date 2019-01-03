begin;

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('_COLLAPSED_SPECIAL', 1, 'false', '', 1);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('_COLLAPSED_LABELS', 1, 'false', '', 1);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('_COLLAPSED_FEEDLIST', 1, 'false', '', 1);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('_COLLAPSED_UNCAT', 1, 'false', '', 1);

update ttrss_version set schema_version = 59;

commit;
