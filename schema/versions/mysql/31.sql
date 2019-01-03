alter table ttrss_feeds add column update_method integer;
update ttrss_feeds set update_method = 0;
alter table ttrss_feeds change update_method update_method integer not null;
alter table ttrss_feeds alter column update_method set default 0;

update ttrss_version set schema_version = 31;
