drop table if exists ttrss_counters_cache;

create table ttrss_counters_cache (
	feed_id integer not null,
	owner_uid integer not null,
	value integer not null default 0,
	updated datetime not null,
	foreign key (owner_uid) references ttrss_users(id) ON DELETE CASCADE
);

create table ttrss_cat_counters_cache (
	feed_id integer not null,
	owner_uid integer not null,
	value integer not null default 0,
	updated datetime not null,
	foreign key (owner_uid) references ttrss_users(id) ON DELETE CASCADE
);

update ttrss_version set schema_version = 50;
