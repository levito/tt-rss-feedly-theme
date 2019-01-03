begin;

create table ttrss_plugin_storage (
	id serial not null primary key,
	name varchar(100) not null,
	owner_uid integer not null references ttrss_users(id) ON DELETE CASCADE,
	content text not null);

update ttrss_version set schema_version = 101;

commit;
