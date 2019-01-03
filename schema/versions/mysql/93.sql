begin;

alter table ttrss_feed_categories add column parent_cat integer;
update ttrss_feed_categories set parent_cat = NULL;

alter table ttrss_feed_categories add FOREIGN KEY (parent_cat) REFERENCES ttrss_feed_categories(id) ON DELETE SET NULL;

update ttrss_version set schema_version = 93;

commit;
