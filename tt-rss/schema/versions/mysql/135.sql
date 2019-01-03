begin;

alter table ttrss_filters2 add column last_triggered datetime;
alter table ttrss_filters2 alter column last_triggered set default null;

update ttrss_version set schema_version = 135;

commit;
