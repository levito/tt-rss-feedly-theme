begin;

alter table ttrss_filters2 add column order_id integer;
update ttrss_filters2 set order_id = 0;
alter table ttrss_filters2 alter column order_id set not null;
alter table ttrss_filters2 alter column order_id set default 0;

alter table ttrss_filters2 add column title varchar(250);
update ttrss_filters2 set title = '';
alter table ttrss_filters2 alter column title set not null;
alter table ttrss_filters2 alter column title set default '';

update ttrss_version set schema_version = 112;

commit;
