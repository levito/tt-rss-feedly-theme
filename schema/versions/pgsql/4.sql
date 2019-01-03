begin;

alter table ttrss_feeds add column parent_feed integer;
alter table ttrss_feeds add foreign key (parent_feed) references ttrss_feeds(id) on delete set null;

alter table ttrss_feeds add column private boolean;

update ttrss_feeds set private = false;

alter table ttrss_feeds alter column private set not null;
alter table ttrss_feeds alter column private set default false;

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('HIDE_READ_FEEDS', 1, 'false', 'Hide feeds with no unread messages',2);

update ttrss_version set schema_version = 4;

commit;
