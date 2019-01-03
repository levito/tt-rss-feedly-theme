begin;

update ttrss_filter_actions set description = 'Delete article' where name = 'filter';

update ttrss_version set schema_version = 81;

commit;
