alter table ttrss_feeds add column auth_pass_encrypted bool;
update ttrss_feeds set auth_pass_encrypted = false;
alter table ttrss_feeds change auth_pass_encrypted auth_pass_encrypted bool not null;
alter table ttrss_feeds alter column auth_pass_encrypted set default false;

update ttrss_version set schema_version = 16;
