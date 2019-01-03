begin;

create table ttrss_scheduled_updates (id integer not null primary key auto_increment,
	owner_uid integer not null,
	feed_id integer default null,
	entered datetime not null,
	foreign key (owner_uid) references ttrss_users(id) ON DELETE CASCADE,
	foreign key (feed_id) references ttrss_feeds(id) ON DELETE CASCADE) ENGINE=InnoDB;

update ttrss_version set schema_version = 5;

commit;
