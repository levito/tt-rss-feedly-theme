begin;

alter table ttrss_user_entries add column tag_cache text;
update ttrss_user_entries set tag_cache = '';
alter table ttrss_user_entries change tag_cache tag_cache text not null;

update ttrss_version set schema_version = 72;

commit;
