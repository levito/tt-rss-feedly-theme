begin;

alter table ttrss_entries add column date_updated timestamp;
update ttrss_entries set date_updated = date_entered;
alter table ttrss_entries alter column date_updated set not null;

update ttrss_version set schema_version = 67;

commit;
