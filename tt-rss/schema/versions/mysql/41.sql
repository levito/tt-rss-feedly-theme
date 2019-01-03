alter table ttrss_feed_categories add column order_id integer;
update ttrss_feed_categories set order_id = 0;
alter table ttrss_feed_categories change order_id order_id integer not null;
alter table ttrss_feed_categories alter column order_id set default 0;

update ttrss_version set schema_version = 41;
