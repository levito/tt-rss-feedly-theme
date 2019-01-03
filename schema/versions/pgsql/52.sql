begin;

alter table ttrss_feedbrowser_cache add column title text;
update ttrss_feedbrowser_cache set title = '';
alter table ttrss_feedbrowser_cache alter column title set not null;

update ttrss_version set schema_version = 52;

commit;
