begin;

create table ttrss_access_keys (id serial not null primary key,
	access_key varchar(250) not null,
	feed_id varchar(250) not null,
	is_cat bool not null default false,
	owner_uid integer not null,
  	foreign key (owner_uid) references ttrss_users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

update ttrss_version set schema_version = 69;

commit;
