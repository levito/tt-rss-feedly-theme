begin;

alter table ttrss_user_entries add column label_cache text;
update ttrss_user_entries set label_cache = '';
alter table ttrss_user_entries alter column label_cache set not null;

update ttrss_version set schema_version = 74;

commit;
