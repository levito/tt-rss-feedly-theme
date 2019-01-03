begin;

update ttrss_prefs set short_desc = 'Do not embed images in articles' where pref_name = 'STRIP_IMAGES';

alter table ttrss_feeds add column hide_images boolean;
update ttrss_feeds set hide_images = false;
alter table ttrss_feeds alter column hide_images set not null;
alter table ttrss_feeds alter column hide_images set default false;

update ttrss_version set schema_version = 106;

commit;
