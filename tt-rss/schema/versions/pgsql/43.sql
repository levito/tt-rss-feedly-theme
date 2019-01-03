alter table ttrss_labels rename column sql_exp to sql_exp_old;
alter table ttrss_labels add column sql_exp text;
update ttrss_labels set sql_exp = sql_exp_old;
alter table ttrss_labels alter column sql_exp set not null;
alter table ttrss_labels drop column sql_exp_old;

update ttrss_version set schema_version = 43;
