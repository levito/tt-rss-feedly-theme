alter table ttrss_user_entries add column score int;
update ttrss_user_entries set score = 0;
alter table ttrss_user_entries alter column score set not null;
alter table ttrss_user_entries alter column score set default 0;

insert into ttrss_filter_actions (id,name,description) values (6, 'score', 
	'Modify score');

update ttrss_version set schema_version = 36;
