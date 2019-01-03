begin;

alter table ttrss_filters add column cat_filter boolean;
update ttrss_filters set cat_filter = false;
alter table ttrss_filters alter column cat_filter set not null;
alter table ttrss_filters alter column cat_filter set default false;

alter table ttrss_filters add column cat_id integer;

alter table ttrss_filters add constraint "$5" FOREIGN KEY (cat_id) REFERENCES ttrss_feed_categories(id) ON DELETE CASCADE;

update ttrss_version set schema_version = 87;

commit;
