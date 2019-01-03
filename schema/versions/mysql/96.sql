begin;

create table ttrss_filters2(id integer primary key auto_increment,
	owner_uid integer not null,
	match_any_rule boolean not null default false,
	enabled boolean not null default true,
	index(owner_uid),
	foreign key (owner_uid) references ttrss_users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=UTF8;
	
create table ttrss_filters2_rules(id integer primary key auto_increment,
	filter_id integer not null references ttrss_filters2(id) on delete cascade,
	reg_exp varchar(250) not null,
	filter_type integer not null,
	feed_id integer default null,
	cat_id integer default null,
	cat_filter boolean not null default false,
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

update ttrss_version set schema_version = 96;

commit;

