begin;

alter table ttrss_enclosures add column height integer not null default 0;
alter table ttrss_enclosures add column width integer not null default 0;

update ttrss_version set schema_version = 126;

commit;
