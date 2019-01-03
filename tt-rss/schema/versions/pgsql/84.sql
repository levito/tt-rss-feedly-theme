begin;

create table ttrss_linked_instances (id serial not null primary key,
	last_connected timestamp not null,
	last_status_in integer not null,
	last_status_out integer not null,
	access_key varchar(250) not null unique,
	access_url text not null);

create table ttrss_linked_feeds (
	feed_url text not null,
	title text not null,
	created timestamp not null,
	updated timestamp not null,
	instance_id integer not null references ttrss_linked_instances(id) ON DELETE CASCADE,
	subscribers integer not null);

drop table ttrss_scheduled_updates;

update ttrss_version set schema_version = 84;

commit;
