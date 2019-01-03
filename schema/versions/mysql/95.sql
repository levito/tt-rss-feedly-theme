begin;

update ttrss_prefs set short_desc = 'Purge articles after this number of days (0 - disables)'
where pref_name = 'PURGE_OLD_DAYS';

update ttrss_prefs set section_id = 1 where pref_name = 'ENABLE_API_ACCESS';

update ttrss_prefs set section_id = 2 where pref_name = 'CONFIRM_FEED_CATCHUP';
update ttrss_prefs set section_id = 2 where pref_name = 'CDM_EXPANDED';
update ttrss_prefs set section_id = 2 where pref_name = 'CDM_AUTO_CATCHUP';
update ttrss_prefs set section_id = 2 where pref_name = 'SORT_HEADLINES_BY_FEED_DATE';
update ttrss_prefs set section_id = 2 where pref_name = 'HIDE_READ_SHOWS_SPECIAL';

insert into ttrss_prefs_sections (id, section_name) values (4, 'Digest');

update ttrss_prefs set section_id = 4 where pref_name = 'DIGEST_ENABLE';
update ttrss_prefs set section_id = 4 where pref_name = 'DIGEST_PREFERRED_TIME';
update ttrss_prefs set section_id = 4 where pref_name = 'DIGEST_CATCHUP';

alter table ttrss_prefs_sections add column order_id integer;
update ttrss_prefs_sections set order_id = 0;
alter table ttrss_prefs_sections change order_id order_id int not null;

update ttrss_prefs_sections set order_id = 0 where id = 1;
update ttrss_prefs_sections set order_id = 1 where id = 2;
update ttrss_prefs_sections set order_id = 2 where id = 4;
update ttrss_prefs_sections set order_id = 3 where id = 3;

update ttrss_prefs set access_level = 1 where pref_name in ('ON_CATCHUP_SHOW_NEXT_FEED',
	'SORT_HEADLINES_BY_FEED_DATE',
	'VFEED_GROUP_BY_FEED',
	'FRESH_ARTICLE_MAX_AGE',
	'CDM_EXPANDED',
	'SHOW_CONTENT_PREVIEW',
	'HIDE_READ_SHOWS_SPECIAL');

update ttrss_version set schema_version = 95;

commit;
