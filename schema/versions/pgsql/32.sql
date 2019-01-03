alter table ttrss_prefs add column access_level integer;
update ttrss_prefs set access_level = 0;
alter table ttrss_prefs alter column access_level set not null;
alter table ttrss_prefs alter column access_level set default 0;

update ttrss_version set schema_version = 32;
