UPDATE ttrss_prefs SET help_text = 'This option is useful when you are reading several planet-type aggregators with partially colliding userbase. When disabled, it forces same posts from different feeds to appear only once.' WHERE pref_name = 'ALLOW_DUPLICATE_POSTS';

update ttrss_version set schema_version = 20;
