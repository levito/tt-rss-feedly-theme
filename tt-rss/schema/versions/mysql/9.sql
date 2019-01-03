alter table ttrss_feeds add column hidden bool;
update ttrss_feeds set hidden = false;
alter table ttrss_feeds change hidden hidden bool not null;
alter table ttrss_feeds alter column hidden set default false;

alter table ttrss_users add column email_digest bool;
update ttrss_users set email_digest = false;
alter table ttrss_users change email_digest email_digest bool not null;
alter table ttrss_users alter column email_digest set default false;

alter table ttrss_users add column last_digest_sent datetime;
update ttrss_users set last_digest_sent = false;
alter table ttrss_users alter column last_digest_sent set default null;

alter table ttrss_filters add column enabled bool;
update ttrss_filters set enabled = true;
alter table ttrss_filters change enabled enabled bool not null;
alter table ttrss_filters alter column enabled set default true;

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('MARK_UNREAD_ON_UPDATE', 1, 'false', 'Set articles as unread on update',3);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('REVERSE_HEADLINES', 1, 'false', 'Reverse headline order (oldest first)',2);

update ttrss_prefs SET section_id = 3 WHERE pref_name = 'ENABLE_SEARCH_TOOLBAR';
update ttrss_prefs SET section_id = 3 WHERE pref_name = 'ENABLE_FEED_ICONS';
update ttrss_prefs SET section_id = 3 WHERE pref_name = 'EXTENDED_FEEDLIST';

update ttrss_version set schema_version = 9;

