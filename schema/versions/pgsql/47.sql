begin;

alter table ttrss_filters add column filter_param varchar(200);

update ttrss_filters set filter_param = '';

alter table ttrss_filters alter column filter_param set not null;
alter table ttrss_filters alter column filter_param set default '';

update ttrss_version set schema_version = 47;

commit;
