begin;

alter table ttrss_user_entries add column label_cache text;
update ttrss_user_entries set label_cache = '';
alter table ttrss_user_entries change label_cache label_cache text not null;

update ttrss_version set schema_version = 74;

commit;
