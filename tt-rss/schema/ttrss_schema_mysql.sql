SET NAMES utf8;
SET CHARACTER SET utf8;

drop table if exists ttrss_error_log;
drop table if exists ttrss_plugin_storage;
drop table if exists ttrss_linked_feeds;
drop table if exists ttrss_linked_instances;
drop table if exists ttrss_access_keys;
drop table if exists ttrss_user_labels2;
drop table if exists ttrss_labels2;
drop table if exists ttrss_feedbrowser_cache;
drop table if exists ttrss_version;
drop table if exists ttrss_labels;
drop table if exists ttrss_filters2_actions;
drop table if exists ttrss_filters2_rules;
drop table if exists ttrss_filters2;
drop table if exists ttrss_filters;
drop table if exists ttrss_filter_types;
drop table if exists ttrss_filter_actions;
drop table if exists ttrss_user_prefs;
drop table if exists ttrss_prefs;
drop table if exists ttrss_prefs_types;
drop table if exists ttrss_prefs_sections;
drop table if exists ttrss_tags;
drop table if exists ttrss_enclosures;
drop table if exists ttrss_settings_profiles;
drop table if exists ttrss_entry_comments;
drop table if exists ttrss_user_entries;
drop table if exists ttrss_entries;
drop table if exists ttrss_scheduled_updates;
drop table if exists ttrss_counters_cache;
drop table if exists ttrss_cat_counters_cache;
drop table if exists ttrss_feeds;
drop table if exists ttrss_archived_feeds;
drop table if exists ttrss_feed_categories;
drop table if exists ttrss_users;
drop table if exists ttrss_themes;
drop table if exists ttrss_sessions;

begin;

create table ttrss_users (id integer primary key not null auto_increment,
	login varchar(120) not null unique,
	pwd_hash varchar(250) not null,
	last_login datetime default null,
	access_level integer not null default 0,
	email varchar(250) not null default '',
	full_name varchar(250) not null default '',
	email_digest bool not null default false,
	last_digest_sent datetime default null,
	salt varchar(250) not null default '',
	created datetime default null,
	twitter_oauth longtext default null,
	otp_enabled boolean not null default false,
	resetpass_token varchar(250) default null) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

insert into ttrss_users (login,pwd_hash,access_level) values ('admin',
	'SHA1:5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8', 10);

create table ttrss_feed_categories(id integer not null primary key auto_increment,
	owner_uid integer not null,
	title varchar(200) not null,
	collapsed bool not null default false,
	order_id integer not null default 0,
	parent_cat integer,
	view_settings varchar(250) not null default '',
	foreign key (parent_cat) references ttrss_feed_categories(id) ON DELETE SET NULL,
	foreign key (owner_uid) references ttrss_users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

create table ttrss_archived_feeds (id integer not null primary key,
	owner_uid integer not null,
	title varchar(200) not null,
	feed_url text not null,
	site_url varchar(250) not null default '',
	foreign key (owner_uid) references ttrss_users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

create table ttrss_counters_cache (
	feed_id integer not null,
	owner_uid integer not null,
	value integer not null default 0,
	updated datetime not null,
	foreign key (owner_uid) references ttrss_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

create index ttrss_counters_cache_feed_id_idx on ttrss_counters_cache(feed_id);
create index ttrss_counters_cache_value_idx on ttrss_counters_cache(value);

create table ttrss_cat_counters_cache (
	feed_id integer not null,
	owner_uid integer not null,
	value integer not null default 0,
	updated datetime not null,
	foreign key (owner_uid) references ttrss_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

create table ttrss_feeds (id integer not null auto_increment primary key,
	owner_uid integer not null,
	title varchar(200) not null,
	cat_id integer default null,
	feed_url text not null,
	icon_url varchar(250) not null default '',
	update_interval integer not null default 0,
	purge_interval integer not null default 0,
	last_updated datetime default null,
	last_unconditional datetime default null,
	last_error varchar(250) not null default '',
	last_modified varchar(250) not null default '',
	favicon_avg_color varchar(11) default null,
	site_url varchar(250) not null default '',
	auth_login varchar(250) not null default '',
	auth_pass varchar(250) not null default '',
	parent_feed integer default null,
	private bool not null default false,
	rtl_content bool not null default false,
	hidden bool not null default false,
	include_in_digest boolean not null default true,
	cache_images boolean not null default false,
	hide_images boolean not null default false,
	cache_content boolean not null default false,
	auth_pass_encrypted boolean not null default false,
	last_viewed datetime default null,
	last_update_started datetime default null,
	always_display_enclosures boolean not null default false,
	update_method integer not null default 0,
	order_id integer not null default 0,
	mark_unread_on_update boolean not null default false,
	update_on_checksum_change boolean not null default false,
	strip_images boolean not null default false,
	view_settings varchar(250) not null default '',
	pubsub_state integer not null default 0,
	favicon_last_checked datetime default null,
	feed_language varchar(100) not null default '',
	foreign key (owner_uid) references ttrss_users(id) ON DELETE CASCADE,
	foreign key (cat_id) references ttrss_feed_categories(id) ON DELETE SET NULL,
	foreign key (parent_feed) references ttrss_feeds(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

insert into ttrss_feeds (owner_uid, title, feed_url) values
	(1, 'Tiny Tiny RSS: Forum', 'http://tt-rss.org/forum/rss.php');

create table ttrss_entries (id integer not null primary key auto_increment,
	title text not null,
	guid varchar(255) not null unique,
	link text not null,
	updated datetime not null,
	content longtext not null,
	content_hash varchar(250) not null,
	cached_content longtext,
	no_orig_date bool not null default 0,
	date_entered datetime not null,
	date_updated datetime not null,
	num_comments integer not null default 0,
	plugin_data longtext,
	lang varchar(2),
	comments varchar(250) not null default '',
	author varchar(250) not null default '') ENGINE=InnoDB DEFAULT CHARSET=UTF8;

create index ttrss_entries_date_entered_index on ttrss_entries(date_entered);
create index ttrss_entries_updated_idx on ttrss_entries(updated);

create table ttrss_user_entries (
	int_id integer not null primary key auto_increment,
	ref_id integer not null,
	uuid varchar(200) not null,
	feed_id int,
	orig_feed_id int,
	owner_uid integer not null,
	marked bool not null default 0,
	published bool not null default 0,
	tag_cache text not null,
	label_cache text not null,
	last_read datetime,
	score int not null default 0,
	note longtext,
	last_marked datetime,
	last_published datetime,
	unread bool not null default 1,
	foreign key (ref_id) references ttrss_entries(id) ON DELETE CASCADE,
	foreign key (feed_id) references ttrss_feeds(id) ON DELETE CASCADE,
	foreign key (orig_feed_id) references ttrss_archived_feeds(id) ON DELETE SET NULL,
	foreign key (owner_uid) references ttrss_users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

create index ttrss_user_entries_unread_idx on ttrss_user_entries(unread);

create table ttrss_entry_comments (id integer not null primary key,
	ref_id integer not null,
	owner_uid integer not null,
	private bool not null default 0,
	date_entered datetime not null,
	index (ref_id),
	foreign key (ref_id) references ttrss_entries(id) ON DELETE CASCADE,
	index (owner_uid),
	foreign key (owner_uid) references ttrss_users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

create table ttrss_filter_types (id integer primary key,
	name varchar(120) unique not null,
	description varchar(250) not null unique) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

insert into ttrss_filter_types (id,name,description) values (1, 'title', 'Title');
insert into ttrss_filter_types (id,name,description) values (2, 'content', 'Content');
insert into ttrss_filter_types (id,name,description) values (3, 'both',
	'Title or Content');
insert into ttrss_filter_types (id,name,description) values (4, 'link',
	'Link');
insert into ttrss_filter_types (id,name,description) values (5, 'date',
	'Article Date');
insert into ttrss_filter_types (id,name,description) values (6, 'author', 'Author');
insert into ttrss_filter_types (id,name,description) values (7, 'tag', 'Article Tags');

create table ttrss_filter_actions (id integer not null primary key,
	name varchar(120) unique not null,
	description varchar(250) not null unique) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

insert into ttrss_filter_actions (id,name,description) values (1, 'filter',
	'Delete article');

insert into ttrss_filter_actions (id,name,description) values (2, 'catchup',
	'Mark as read');

insert into ttrss_filter_actions (id,name,description) values (3, 'mark',
	'Set starred');

insert into ttrss_filter_actions (id,name,description) values (4, 'tag',
	'Assign tags');

insert into ttrss_filter_actions (id,name,description) values (5, 'publish',
	'Publish article');

insert into ttrss_filter_actions (id,name,description) values (6, 'score',
	'Modify score');

insert into ttrss_filter_actions (id,name,description) values (7, 'label',
	'Assign label');

insert into ttrss_filter_actions (id,name,description) values (8, 'stop',
	'Stop / Do nothing');

insert into ttrss_filter_actions (id,name,description) values (9, 'plugin',
	'Invoke plugin');

create table ttrss_filters2(id integer primary key auto_increment,
	owner_uid integer not null,
	match_any_rule boolean not null default false,
	enabled boolean not null default true,
	inverse bool not null default false,
	title varchar(250) not null default '',
	order_id integer not null default 0,
	last_triggered datetime default null,
	foreign key (owner_uid) references ttrss_users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

create table ttrss_filters2_rules(id integer primary key auto_increment,
	filter_id integer not null references ttrss_filters2(id) on delete cascade,
	reg_exp text not null,
	inverse bool not null default false,
	filter_type integer not null,
	feed_id integer default null,
	cat_id integer default null,
	cat_filter boolean not null default false,
	match_on text,
	index (filter_id),
	foreign key (filter_id) references ttrss_filters2(id) on delete cascade,
	index (filter_type),
	foreign key (filter_type) references ttrss_filter_types(id) ON DELETE CASCADE,
	index (feed_id),
	foreign key (feed_id) references ttrss_feeds(id) ON DELETE CASCADE,
	index (cat_id),
	foreign key (cat_id) references ttrss_feed_categories(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

create table ttrss_filters2_actions(id integer primary key auto_increment,
	filter_id integer not null,
	action_id integer not null default 1 references ttrss_filter_actions(id) on delete cascade,
	action_param varchar(250) not null default '',
	index (filter_id),
	foreign key (filter_id) references ttrss_filters2(id) on delete cascade,
	index (action_id),
	foreign key (action_id) references ttrss_filter_actions(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

create table ttrss_tags (id integer primary key auto_increment,
	owner_uid integer not null,
	tag_name varchar(250) not null,
	post_int_id integer not null,
	index (post_int_id),
	foreign key (post_int_id) references ttrss_user_entries(int_id) ON DELETE CASCADE,
	index (owner_uid),
	foreign key (owner_uid) references ttrss_users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

create table ttrss_version (schema_version int not null) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

insert into ttrss_version values (135);

create table ttrss_enclosures (id integer primary key auto_increment,
	content_url text not null,
	content_type varchar(250) not null,
	post_id integer not null,
	title text not null,
	duration text not null,
	width integer not null default 0,
	height integer not null default 0,
	foreign key (post_id) references ttrss_entries(id) ON DELETE cascade) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

create table ttrss_settings_profiles(id integer primary key auto_increment,
	title varchar(250) not null,
	owner_uid integer not null,
	index (owner_uid),
	foreign key (owner_uid) references ttrss_users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

create table ttrss_prefs_types (id integer not null primary key,
	type_name varchar(100) not null) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

insert into ttrss_prefs_types (id, type_name) values (1, 'bool');
insert into ttrss_prefs_types (id, type_name) values (2, 'string');
insert into ttrss_prefs_types (id, type_name) values (3, 'integer');

create table ttrss_prefs_sections (id integer not null primary key,
	order_id integer not null) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

insert into ttrss_prefs_sections (id, order_id) values (1, 0);
insert into ttrss_prefs_sections (id, order_id) values (2, 1);
insert into ttrss_prefs_sections (id, order_id) values (3, 3);
insert into ttrss_prefs_sections (id, order_id) values (4, 2);

create table ttrss_prefs (pref_name varchar(250) not null primary key,
	type_id integer not null,
	section_id integer not null default 1,
	access_level integer not null default 0,
	def_value text not null,
	foreign key (type_id) references ttrss_prefs_types(id),
	foreign key (section_id) references ttrss_prefs_sections(id)) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('PURGE_OLD_DAYS', 3, '60', 1);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('DEFAULT_UPDATE_INTERVAL', 3, '30', 1);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('DEFAULT_ARTICLE_LIMIT', 3, '30', 2);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('ALLOW_DUPLICATE_POSTS', 1, 'false', 1);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('ENABLE_FEED_CATS', 1, 'true', 2);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('SHOW_CONTENT_PREVIEW', 1, 'true', 2);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('SHORT_DATE_FORMAT', 2, 'M d, G:i', 3);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('LONG_DATE_FORMAT', 2, 'D, M d Y - G:i', 3);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('COMBINED_DISPLAY_MODE', 1, 'true', 2);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('HIDE_READ_FEEDS', 1, 'false', 2);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('ON_CATCHUP_SHOW_NEXT_FEED', 1, 'false', 2);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('FEEDS_SORT_BY_UNREAD', 1, 'false', 2);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('REVERSE_HEADLINES', 1, 'false', 2);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('DIGEST_ENABLE', 1, 'false', 4);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('CONFIRM_FEED_CATCHUP', 1, 'true', 2);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('CDM_AUTO_CATCHUP', 1, 'false', 2);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('_DEFAULT_VIEW_MODE', 2, 'adaptive', 1);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('_DEFAULT_VIEW_LIMIT', 3, '30', 1);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('_PREFS_ACTIVE_TAB', 2, '', 1);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('STRIP_UNSAFE_TAGS', 1, 'true', 3);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('BLACKLISTED_TAGS', 2, 'main, generic, misc, uncategorized, blog, blogroll, general, news', 3);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('FRESH_ARTICLE_MAX_AGE', 3, '24', 2);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('DIGEST_CATCHUP', 1, 'false', 4);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('CDM_EXPANDED', 1, 'true', 2);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('PURGE_UNREAD_ARTICLES', 1, 'true', 3);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('HIDE_READ_SHOWS_SPECIAL', 1, 'true', 2);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('VFEED_GROUP_BY_FEED', 1, 'false', 2);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('STRIP_IMAGES', 1, 'false', 2);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('_DEFAULT_VIEW_ORDER_BY', 2, 'default', 1);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('ENABLE_API_ACCESS', 1, 'false', 1);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('_COLLAPSED_SPECIAL', 1, 'false', 1);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('_COLLAPSED_LABELS', 1, 'false', 1);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('_COLLAPSED_UNCAT', 1, 'false', 1);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('_COLLAPSED_FEEDLIST', 1, 'false', 1);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('_MOBILE_ENABLE_CATS', 1, 'false', 1);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('_MOBILE_SHOW_IMAGES', 1, 'false', 1);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('_MOBILE_HIDE_READ', 1, 'false', 1);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('_MOBILE_SORT_FEEDS_UNREAD', 1, 'false', 1);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('_THEME_ID', 2, '0', 1);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('USER_TIMEZONE', 2, 'Automatic', 1);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('USER_STYLESHEET', 2, '', 2);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('SORT_HEADLINES_BY_FEED_DATE', 1, 'false', 2);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('_MOBILE_BROWSE_CATS', 1, 'true', 1);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('SSL_CERT_SERIAL', 2, '', 3);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('DIGEST_PREFERRED_TIME', 2, '00:00', 4);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('_PREFS_SHOW_EMPTY_CATS', 1, 'false', 1);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('_DEFAULT_INCLUDE_CHILDREN', 1, 'false', 1);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('AUTO_ASSIGN_LABELS', 1, 'false', 3);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('_ENABLED_PLUGINS', 2, '', 1);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('_MOBILE_REVERSE_HEADLINES', 1, 'false', 1);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('USER_CSS_THEME', 2, '', 2);
insert into ttrss_prefs (pref_name,type_id,def_value,section_id) values('USER_LANGUAGE', 2, '', 2);

update ttrss_prefs set access_level = 1 where pref_name in ('ON_CATCHUP_SHOW_NEXT_FEED',
	'SORT_HEADLINES_BY_FEED_DATE',
	'VFEED_GROUP_BY_FEED',
	'FRESH_ARTICLE_MAX_AGE',
	'CDM_EXPANDED',
	'SHOW_CONTENT_PREVIEW',
	'AUTO_ASSIGN_LABELS',
	'HIDE_READ_SHOWS_SPECIAL');

create table ttrss_user_prefs (
   owner_uid integer not null,
   pref_name varchar(250),
   value longtext not null,
	profile integer,
	index (profile),
  	foreign key (profile) references ttrss_settings_profiles(id) ON DELETE CASCADE,
 	foreign key (owner_uid) references ttrss_users(id) ON DELETE CASCADE,
	foreign key (pref_name) references ttrss_prefs(pref_name) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

create table ttrss_sessions (id varchar(250) not null primary key,
	data text,
	expire integer not null,
	index (expire)) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

create table ttrss_feedbrowser_cache (
	feed_url text not null,
	site_url text not null,
	title text not null,
	subscribers integer not null) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

create table ttrss_labels2 (id integer not null primary key auto_increment,
	owner_uid integer not null,
	caption varchar(250) not null,
	fg_color varchar(15) not null default '',
	bg_color varchar(15) not null default '',
	foreign key (owner_uid) references ttrss_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

create table ttrss_user_labels2 (label_id integer not null,
	article_id integer not null,
	foreign key (label_id) references ttrss_labels2(id) ON DELETE CASCADE,
	foreign key (article_id) references ttrss_entries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

create table ttrss_access_keys (id integer not null primary key auto_increment,
	access_key varchar(250) not null,
	feed_id varchar(250) not null,
	is_cat bool not null default false,
	owner_uid integer not null,
  	foreign key (owner_uid) references ttrss_users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

create table ttrss_linked_instances (id integer not null primary key auto_increment,
	last_connected datetime not null,
	last_status_in integer not null,
	last_status_out integer not null,
	access_key varchar(250) not null unique,
	access_url text not null) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

create table ttrss_linked_feeds (
	feed_url text not null,
	site_url text not null,
	title text not null,
	created datetime not null,
	updated datetime not null,
	instance_id integer not null,
	subscribers integer not null,
 	foreign key (instance_id) references ttrss_linked_instances(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

create table ttrss_plugin_storage (
	id integer not null auto_increment primary key,
	name varchar(100) not null,
	owner_uid integer not null,
	content longtext not null,
  	foreign key (owner_uid) references ttrss_users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

create table ttrss_error_log(
	id integer not null auto_increment primary key,
	owner_uid integer,
	errno integer not null,
	errstr text not null,
	filename text not null,
	lineno integer not null,
	context text not null,
	created_at datetime not null,
	foreign key (owner_uid) references ttrss_users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

commit;
