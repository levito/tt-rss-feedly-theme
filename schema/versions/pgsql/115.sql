begin;

alter table ttrss_prefs_sections drop column section_name;
alter table ttrss_prefs drop column short_desc;
alter table ttrss_prefs drop column help_text;

update ttrss_version set schema_version = 115;

commit;
