begin;

alter table ttrss_filters2 add column inverse bool;
update ttrss_filters2 set inverse = false;
alter table ttrss_filters2 change inverse inverse bool not null;
alter table ttrss_filters2 alter column inverse set default false;

alter table ttrss_filters2_rules add column inverse bool;
update ttrss_filters2_rules set inverse = false;
alter table ttrss_filters2_rules change inverse inverse bool not null;
alter table ttrss_filters2_rules alter column inverse set default false;

update ttrss_version set schema_version = 107;

commit;
