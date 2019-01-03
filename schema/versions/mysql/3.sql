begin;

alter table ttrss_entries add column num_comments integer;

update ttrss_entries set num_comments = 0;

alter table ttrss_entries change num_comments num_comments integer not null;
alter table ttrss_entries alter column num_comments set default 0;

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('COMBINED_DISPLAY_MODE', 1, 'false', 'Combined feed display',2,
	'Display expanded list of feed articles, instead of separate displays for headlines and article content');

alter table ttrss_feed_categories add column collapsed bool;

update ttrss_feed_categories set collapsed = false;

alter table ttrss_feed_categories change collapsed collapsed bool not null;
alter table ttrss_feed_categories alter column collapsed set default 0;

alter table ttrss_feeds add column auth_login varchar(250);
alter table ttrss_feeds add column auth_pass varchar(250);

update ttrss_feeds set auth_login = '';
update ttrss_feeds set auth_pass = '';

alter table ttrss_feeds change auth_login auth_login varchar(250) not null;
alter table ttrss_feeds alter column auth_login set default '';

alter table ttrss_feeds change auth_pass auth_pass varchar(250) not null;
alter table ttrss_feeds alter column auth_pass set default '';

alter table ttrss_users add column email varchar(250);

update ttrss_users set email = '';

alter table ttrss_users change email email varchar(250) not null;
alter table ttrss_users alter column email set default '';

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('ENABLE_SEARCH_TOOLBAR', 1, 'false', 'Enable search toolbar',2);

update ttrss_version set schema_version = 3;

commit;
