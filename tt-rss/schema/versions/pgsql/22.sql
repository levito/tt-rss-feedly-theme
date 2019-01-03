create index ttrss_entries_date_entered_index on ttrss_entries(date_entered);

alter table ttrss_feeds add column cache_images boolean;
update ttrss_feeds set cache_images = false;
alter table ttrss_feeds alter column cache_images set not null;
alter table ttrss_feeds alter column cache_images set default false;

update ttrss_version set schema_version = 22;
