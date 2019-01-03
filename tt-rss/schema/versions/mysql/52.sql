alter table ttrss_feedbrowser_cache add column title text;
update ttrss_feedbrowser_cache set title = '';
alter table ttrss_feedbrowser_cache change title title text not null;

update ttrss_version set schema_version = 52;
