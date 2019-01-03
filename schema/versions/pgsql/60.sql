begin;

alter table ttrss_user_entries alter column feed_id drop not null;

create table ttrss_archived_feeds (id integer not null primary key,
	owner_uid integer not null references ttrss_users(id) on delete cascade,
	title varchar(200) not null, 
	feed_url text not null, 
	site_url varchar(250) not null default '');	

alter table ttrss_user_entries add column orig_feed_id integer;
update ttrss_user_entries set orig_feed_id = NULL;

alter table ttrss_user_entries add constraint "$4" FOREIGN KEY (orig_feed_id) REFERENCES ttrss_archived_feeds(id) ON DELETE SET NULL;

update ttrss_version set schema_version = 60;

commit;
