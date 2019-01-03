alter table ttrss_entries add column author varchar(250);

update ttrss_entries set author = '';

alter table ttrss_entries change author author varchar(250) not null;
alter table ttrss_entries alter column author set default '';

create table ttrss_sessions (id varchar(250) not null primary key,
	data text,
	expire integer not null,
	ip_address varchar(15) not null default '',
	index (id),
	index (expire)) ENGINE=InnoDB;

delete from ttrss_prefs where pref_name = 'ENABLE_SPLASH';

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('OPEN_LINKS_IN_NEW_WINDOW', 1, 'true', 'Open article links in new browser window',2);

update ttrss_version set schema_version = 6;

