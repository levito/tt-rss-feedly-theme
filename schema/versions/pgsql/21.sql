insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('FRESH_ARTICLE_MAX_AGE', 3, '24', 'Maximum age of fresh articles (in hours)',2);

update ttrss_version set schema_version = 21;
