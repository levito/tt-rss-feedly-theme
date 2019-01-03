begin;

alter table ttrss_feeds add column view_settings varchar(250);
update ttrss_feeds set view_settings = '';
alter table ttrss_feeds alter column view_settings set not null;
alter table ttrss_feeds alter column view_settings set default '';

alter table ttrss_feed_categories add column view_settings varchar(250);
update ttrss_feed_categories set view_settings = '';
alter table ttrss_feed_categories alter column view_settings set not null;
alter table ttrss_feed_categories alter column view_settings set default '';

update ttrss_version set schema_version = 114;

commit;
