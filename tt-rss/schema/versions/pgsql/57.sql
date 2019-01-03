alter table ttrss_feeds add column always_display_enclosures boolean;
update ttrss_feeds set always_display_enclosures = false;
alter table ttrss_feeds alter column always_display_enclosures set not null;
alter table ttrss_feeds alter column always_display_enclosures set default false;

update ttrss_version set schema_version = 57;
