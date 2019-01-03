begin;

create table ttrss_error_log(
	id serial not null primary key,
	owner_uid integer references ttrss_users(id) ON DELETE SET NULL,
	errno integer not null,
	errstr text not null,
	filename text not null,
	lineno integer not null,	
	context text not null,
	created_at timestamp not null);

update ttrss_version set schema_version = 118;

commit;
