begin;

alter table ttrss_feeds add column order_id integer;
update ttrss_feeds set order_id = 0;
alter table ttrss_feeds change order_id order_id integer not null;
alter table ttrss_feeds alter column order_id set default 0;

update ttrss_version set schema_version = 75;

commit;
