begin;

alter table ttrss_entries add column plugin_data text;

update ttrss_version set schema_version = 103;

commit;
