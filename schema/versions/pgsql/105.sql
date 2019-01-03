begin;

alter table ttrss_user_entries add column last_marked timestamp;
alter table ttrss_user_entries add column last_published timestamp;

update ttrss_user_entries set last_published = last_read where published = true;
update ttrss_user_entries set last_marked = last_read where marked = true;

update ttrss_version set schema_version = 105;

commit;
