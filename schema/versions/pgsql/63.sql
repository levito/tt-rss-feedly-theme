begin;

create table ttrss_settings_profiles(id serial not null primary key,
	title varchar(250) not null,
	owner_uid integer not null references ttrss_users(id) on delete cascade);

alter table ttrss_user_prefs add column profile integer;
update ttrss_user_prefs set profile = NULL;

alter table ttrss_user_prefs add constraint "$3" FOREIGN KEY (profile) REFERENCES ttrss_settings_profiles(id) ON DELETE CASCADE;

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('_THEME_ID', 3, '0', '', 1);

update ttrss_version set schema_version = 63;

commit;
