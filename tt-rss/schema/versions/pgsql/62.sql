begin;

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('_MOBILE_ENABLE_CATS', 1, 'false', '', 1);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('_MOBILE_SHOW_IMAGES', 1, 'false', '', 1);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('_MOBILE_HIDE_READ', 1, 'false', '', 1);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('_MOBILE_SORT_FEEDS_UNREAD', 1, 'false', '', 1);

update ttrss_version set schema_version = 62;

commit;
