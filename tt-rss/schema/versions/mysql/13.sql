alter table ttrss_filters add column inverse bool;
update ttrss_filters set inverse = false;
alter table ttrss_filters change inverse inverse bool not null;
alter table ttrss_filters alter column inverse set default false;

update ttrss_version set schema_version = 13;
