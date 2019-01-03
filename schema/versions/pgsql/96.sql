begin;

create table ttrss_filters2(id serial not null primary key,
	owner_uid integer not null references ttrss_users(id) on delete cascade,
	match_any_rule boolean not null default false,
	enabled boolean not null default true);

create table ttrss_filters2_rules(id serial not null primary key,
	filter_id integer not null references ttrss_filters2(id) on delete cascade,
	reg_exp varchar(250) not null,
	filter_type integer not null references ttrss_filter_types(id),
	feed_id integer references ttrss_feeds(id) on delete cascade default null,
	cat_id integer references ttrss_feed_categories(id) on delete cascade default null,
	cat_filter boolean not null default false);

create table ttrss_filters2_actions(id serial not null primary key,
	filter_id integer not null references ttrss_filters2(id) on delete cascade,
	action_id integer not null default 1 references ttrss_filter_actions(id) on delete cascade,
	action_param varchar(250) not null default '');

update ttrss_version set schema_version = 96;

commit;
