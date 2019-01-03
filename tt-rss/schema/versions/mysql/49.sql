alter table ttrss_counters_cache add column updated datetime;
update ttrss_counters_cache set updated = NOW();
alter table ttrss_counters_cache change updated updated datetime not null;

update ttrss_version set schema_version = 49;
