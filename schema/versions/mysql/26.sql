insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('PURGE_UNREAD_ARTICLES', 1, 'true', 'Purge unread articles',3);

alter table ttrss_users add column created datetime;
alter table ttrss_users alter column created set default null;

create table ttrss_enclosures (id serial not null primary key,
   content_url text not null,
   content_type varchar(250) not null,
   post_id integer not null, 
	title text not null,
	duration text not null,
   index (post_id),
   foreign key (post_id) references ttrss_entries(id) ON DELETE cascade);

update ttrss_version set schema_version = 26;
