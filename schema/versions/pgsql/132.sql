begin;

alter table ttrss_feeds add column last_modified text;
update ttrss_feeds set last_modified = '';
alter table ttrss_feeds alter column last_modified set not null;
alter table ttrss_feeds alter column last_modified set default '';

UPDATE ttrss_version SET schema_version = 132;

commit;
