alter table ttrss_filters add column action_param varchar(200);

update ttrss_filters set action_param = '';

alter table ttrss_filters change action_param action_param varchar(200) not null;
alter table ttrss_filters alter column action_param set default '';

insert into ttrss_filter_actions (id,name,description) values (4, 'tag', 
		'Assign tags');

update ttrss_version set schema_version = 12;

