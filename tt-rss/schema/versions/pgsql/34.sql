alter table ttrss_feeds rename column feed_url to feed_url_old;
alter table ttrss_feeds add column feed_url text;
update ttrss_feeds set feed_url = feed_url_old;
alter table ttrss_feeds alter column feed_url set not null;
alter table ttrss_feeds drop column feed_url_old;

update ttrss_version set schema_version = 34;
