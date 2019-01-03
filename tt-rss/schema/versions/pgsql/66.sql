begin;

insert into ttrss_filter_types (id, name, description) values (6, 'author', 'Author');

update ttrss_version set schema_version = 66;

commit;
