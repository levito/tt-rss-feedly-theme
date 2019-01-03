alter table ttrss_labels change sql_exp sql_exp text not null;

update ttrss_version set schema_version = 43;
