begin;

alter table ttrss_feedbrowser_cache add column site_url text;
update ttrss_feedbrowser_cache set site_url = '';
alter table ttrss_feedbrowser_cache change site_url site_url text not null;

alter table ttrss_linked_feeds add column site_url text;
update ttrss_linked_feeds set site_url = '';
alter table ttrss_linked_feeds change site_url site_url text not null;

update ttrss_version set schema_version = 85;

commit;
