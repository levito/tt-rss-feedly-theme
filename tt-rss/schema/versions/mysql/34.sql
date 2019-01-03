alter table ttrss_feeds change feed_url feed_url text not null;

update ttrss_version set schema_version = 34;
