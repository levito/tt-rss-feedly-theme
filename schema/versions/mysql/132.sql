begin;

alter table ttrss_feeds add column last_modified varchar(250);
update ttrss_feeds set last_modified = '';
alter table ttrss_feeds change last_modified last_modified varchar(250) not null;
alter table ttrss_feeds alter column last_modified set default '';

UPDATE ttrss_version SET schema_version = 132;

commit;
