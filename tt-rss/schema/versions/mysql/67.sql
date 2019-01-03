alter table ttrss_entries add column date_updated datetime;
update ttrss_entries set date_updated = date_entered;
alter table ttrss_entries change date_updated date_updated datetime not null;

update ttrss_version set schema_version = 67;
