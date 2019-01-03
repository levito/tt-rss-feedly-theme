begin;

insert into ttrss_filter_actions (id,name,description) values (8, 'stop',
	'Stop / Do nothing');

update ttrss_version set schema_version = 113;

commit;
