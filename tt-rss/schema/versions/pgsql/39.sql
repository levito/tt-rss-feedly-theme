insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('ENABLE_FLASH_PLAYER', 1, 'true', 'Enable inline MP3 player', 3, 'Enable the Flash-based XSPF Player to play MP3-format podcast enclosures.');

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('STRIP_IMAGES', 1, 'false', 'Do not show images in articles', 2);

update ttrss_version set schema_version = 39;
