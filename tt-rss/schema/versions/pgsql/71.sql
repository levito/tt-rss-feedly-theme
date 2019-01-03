begin;

insert into ttrss_filter_types (id,name,description) values (7, 'tag', 'Article Tags');

update ttrss_version set schema_version = 71;

commit;
