insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('DIGEST_ENABLE', 1, 'false', 'Enable e-mail digest',1,
'This option enables sending daily digest of new (and unread) headlines on your configured e-mail address');

alter table ttrss_feeds add column include_in_digest bool;
update ttrss_feeds set include_in_digest = true;
alter table ttrss_feeds change include_in_digest include_in_digest bool not null;
alter table ttrss_feeds alter column include_in_digest set default true;

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('CONFIRM_FEED_CATCHUP', 1, 'true', 'Confirm marking feed as read',3);

update ttrss_version set schema_version = 10;

