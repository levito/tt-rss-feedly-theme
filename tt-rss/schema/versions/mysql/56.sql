begin;

drop table ttrss_enclosures;

create table ttrss_enclosures (id serial not null primary key,
	content_url text not null,
	content_type varchar(250) not null,
	post_id integer not null,
	title text not null,
	duration text not null,
	index (post_id),
	foreign key (post_id) references ttrss_entries(id) ON DELETE cascade) ENGINE=InnoDB;

update ttrss_version set schema_version = 56;

commit;
