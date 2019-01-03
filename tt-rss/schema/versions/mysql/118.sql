begin;

create table ttrss_error_log(
	id integer not null auto_increment primary key,
	owner_uid integer,
	errno integer not null,
	errstr text not null,
	filename text not null,
	lineno integer not null,	
	context text not null,
	created_at datetime not null,
	foreign key (owner_uid) references ttrss_users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

update ttrss_version set schema_version = 118;

commit;
