begin;

alter table ttrss_labels2 add column fg_color varchar(15);
update ttrss_labels2 set fg_color = '';
alter table ttrss_labels2 alter column fg_color set not null;
alter table ttrss_labels2 alter column fg_color set default '';

alter table ttrss_labels2 add column bg_color varchar(15);
update ttrss_labels2 set bg_color = '';
alter table ttrss_labels2 alter column bg_color set not null;
alter table ttrss_labels2 alter column bg_color set default '';

update ttrss_version set schema_version = 53;

commit;
