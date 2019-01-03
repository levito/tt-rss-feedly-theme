begin;

create table ttrss_scheduled_updates (id serial not null primary key,
	owner_uid integer not null references ttrss_users(id) ON DELETE CASCADE,
	feed_id integer default null references ttrss_feeds(id) ON DELETE CASCADE,
	entered timestamp not null default NOW());

update ttrss_version set schema_version = 5;

commit;
