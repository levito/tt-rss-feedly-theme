begin;

alter table ttrss_users add column otp_enabled boolean;
update ttrss_users set otp_enabled = false;
alter table ttrss_users alter column otp_enabled set not null;
alter table ttrss_users alter column otp_enabled set default false;

update ttrss_version set schema_version = 97;

commit;
