create table ttrss_labels2 (id integer not null primary key auto_increment, 
	owner_uid integer not null,
	caption varchar(250) not null,
	foreign key (owner_uid) references ttrss_users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

create table ttrss_user_labels2 (label_id integer not null,
	article_id integer not null,
	foreign key (label_id) references ttrss_labels2(id) ON DELETE CASCADE,
	foreign key (article_id) references ttrss_entries(id) ON DELETE CASCADE
) ENGINE=InnoDB;

insert into ttrss_filter_actions (id,name,description) values (7, 'label', 
	'Assign label');

update ttrss_version set schema_version = 51;
