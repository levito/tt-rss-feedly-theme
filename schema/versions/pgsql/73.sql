begin;

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('SORT_HEADLINES_BY_FEED_DATE', 1, 'true', 'Sort headlines by feed date',3,
	'Use feed-specified date to sort headlines instead of local import date.');

update ttrss_version set schema_version = 73;

commit;
