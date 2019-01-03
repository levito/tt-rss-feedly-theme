begin;

alter table ttrss_user_entries change feed_id feed_id integer null;

create table ttrss_archived_feeds (id integer not null primary key,
	owner_uid integer not null,
	title varchar(200) not null, 
	feed_url text not null, 
	site_url varchar(250) not null default '',
	index(owner_uid),
	foreign key (owner_uid) references ttrss_users(id) ON DELETE CASCADE) ENGINE=InnoDB;

alter table ttrss_user_entries add column orig_feed_id integer;
update ttrss_user_entries set orig_feed_id = NULL;

alter table ttrss_user_entries add FOREIGN KEY (orig_feed_id) REFERENCES ttrss_archived_feeds(id) ON DELETE SET NULL;

update ttrss_version set schema_version = 60;

commit;
