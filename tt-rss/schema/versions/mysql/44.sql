create table ttrss_counters_cache (
	feed_id integer not null,
	owner_uid integer not null,
	value integer not null default 0,
	foreign key (feed_id) references ttrss_feeds(id) ON DELETE CASCADE,
	foreign key (owner_uid) references ttrss_users(id) ON DELETE CASCADE
);

update ttrss_version set schema_version = 44;
