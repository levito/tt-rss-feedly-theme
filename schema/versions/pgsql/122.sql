begin;

alter table ttrss_entries add column lang varchar(2);

update ttrss_version set schema_version = 122;

commit;
