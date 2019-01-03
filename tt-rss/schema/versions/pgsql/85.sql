begin;

alter table ttrss_feedbrowser_cache add column site_url text;
update ttrss_feedbrowser_cache set site_url = '';
alter table ttrss_feedbrowser_cache alter column site_url set not null;

alter table ttrss_linked_feeds add column site_url text;
update ttrss_linked_feeds set site_url = '';
alter table ttrss_linked_feeds alter column site_url set not null;

update ttrss_version set schema_version = 85;

commit;
