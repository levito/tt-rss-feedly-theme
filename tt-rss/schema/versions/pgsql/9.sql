begin;

alter table ttrss_feeds add column hidden boolean;
update ttrss_feeds set hidden = false;
alter table ttrss_feeds alter column hidden set not null;
alter table ttrss_feeds alter column hidden set default false;

alter table ttrss_users add column email_digest boolean;
update ttrss_users set email_digest = false;
alter table ttrss_users alter column email_digest set not null;
alter table ttrss_users alter column email_digest set default false;

alter table ttrss_users add column last_digest_sent timestamp;
update ttrss_users set last_digest_sent = NULL;
alter table ttrss_users alter column last_digest_sent set default NULL;

alter table ttrss_filters add column enabled boolean;
update ttrss_filters set enabled = true;
alter table ttrss_filters alter column enabled set not null;
alter table ttrss_filters alter column enabled set default true;

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('MARK_UNREAD_ON_UPDATE', 1, 'false', 'Set articles as unread on update',3);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('REVERSE_HEADLINES', 1, 'false', 'Reverse headline order (oldest first)',2);

update ttrss_prefs SET section_id = 3 WHERE pref_name = 'ENABLE_SEARCH_TOOLBAR';
update ttrss_prefs SET section_id = 3 WHERE pref_name = 'ENABLE_FEED_ICONS';
update ttrss_prefs SET section_id = 3 WHERE pref_name = 'EXTENDED_FEEDLIST';

update ttrss_version set schema_version = 9;

commit;

