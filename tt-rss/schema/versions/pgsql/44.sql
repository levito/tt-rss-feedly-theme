create table ttrss_counters_cache (
	feed_id integer not null references ttrss_feeds(id) ON DELETE CASCADE,
	owner_uid integer not null references ttrss_users(id) ON DELETE CASCADE,
	value integer not null default 0);

update ttrss_version set schema_version = 44;
