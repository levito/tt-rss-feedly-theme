begin;

create table ttrss_linked_instances (id integer not null primary key auto_increment,
	last_connected timestamp not null,
	last_status_in integer not null,
	last_status_out integer not null,
	access_key varchar(250) not null unique,
	access_url text not null) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

create table ttrss_linked_feeds (
	feed_url text not null,
	title text not null,
	created datetime not null,
	updated datetime not null,
	instance_id integer not null,
	subscribers integer not null,
 	foreign key (instance_id) references ttrss_linked_instances(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

drop table ttrss_scheduled_updates;

update ttrss_version set schema_version = 84;

commit;
