insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('_PREFS_PUBLISH_KEY', 2, '', '', 1);

alter table ttrss_user_entries add column published bool;
update ttrss_user_entries set published = false;
alter table ttrss_user_entries change published published bool not null;
alter table ttrss_user_entries alter column published set default false;

insert into ttrss_filter_actions (id,name,description) values (5, 'publish', 
	'Publish article');

update ttrss_version set schema_version = 19;
