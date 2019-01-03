begin;

create table ttrss_labels2 (id serial not null primary key, 
	owner_uid integer not null references ttrss_users(id) ON DELETE CASCADE,
	caption varchar(250) not null
);

create table ttrss_user_labels2 (
	label_id integer not null references ttrss_labels2(id) ON DELETE CASCADE,
	article_id integer not null references ttrss_entries(id) ON DELETE CASCADE
);

insert into ttrss_filter_actions (id,name,description) values (7, 'label', 
	'Assign label');

update ttrss_version set schema_version = 51;

commit;
