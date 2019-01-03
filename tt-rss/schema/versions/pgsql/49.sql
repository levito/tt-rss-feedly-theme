begin;

alter table ttrss_counters_cache add column updated timestamp;
update ttrss_counters_cache set updated = NOW();
alter table ttrss_counters_cache alter column updated set not null;

update ttrss_version set schema_version = 49;

commit;
