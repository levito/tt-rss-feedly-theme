begin;

create table ttrss_feedbrowser_cache (
	feed_url text not null primary key,
	subscribers integer not null);	

update ttrss_version set schema_version = 48;

commit;
