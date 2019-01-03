begin;

create table ttrss_plugin_storage (
	id integer not null auto_increment primary key,
	name varchar(100) not null,
	owner_uid integer not null,
	content longtext not null,
  	foreign key (owner_uid) references ttrss_users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=UTF8;

update ttrss_version set schema_version = 101;

commit;
