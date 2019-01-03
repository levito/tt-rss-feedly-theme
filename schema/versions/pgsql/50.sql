begin;

drop table ttrss_counters_cache;

create table ttrss_counters_cache (
	feed_id integer not null,
	owner_uid integer not null references ttrss_users(id) ON DELETE CASCADE,
	updated timestamp not null,
	value integer not null default 0);

create table ttrss_cat_counters_cache (
	feed_id integer not null,
	owner_uid integer not null references ttrss_users(id) ON DELETE CASCADE,
	updated timestamp not null,
	value integer not null default 0);

update ttrss_version set schema_version = 50;

commit;
