alter table ttrss_feeds add column rtl_content bool;

update ttrss_feeds set rtl_content = false;

alter table ttrss_feeds change rtl_content rtl_content bool not null;
alter table ttrss_feeds alter column rtl_content set default false;

delete from ttrss_user_prefs where pref_name = 'DISPLAY_FEEDLIST_ACTIONS';
delete from ttrss_prefs where pref_name = 'DISPLAY_FEEDLIST_ACTIONS';

delete from ttrss_user_prefs where pref_name = 'ENABLE_PREFS_CATCHUP_UNCATCHUP';
delete from ttrss_prefs where pref_name = 'ENABLE_PREFS_CATCHUP_UNCATCHUP';

alter table ttrss_sessions drop column ip_address;

alter table ttrss_filters drop column description;
 
update ttrss_version set schema_version = 7;

