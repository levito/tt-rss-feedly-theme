BEGIN;

insert into ttrss_filter_actions (id,name,description) values (9, 'plugin',
	'Invoke plugin');

UPDATE ttrss_version SET schema_version = 129;

COMMIT;
