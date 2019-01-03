<?php
	// *******************************************
	// *** Database configuration (important!) ***
	// *******************************************

	define('DB_TYPE', "pgsql"); // or mysql
	define('DB_HOST', "localhost");
	define('DB_USER', "test");
	define('DB_NAME', "test");
	define('DB_PASS', "test");
	define('DB_PORT', ''); // usually 5432 for PostgreSQL, 3306 for MySQL

	define('MYSQL_CHARSET', 'UTF8');
	// Connection charset for MySQL. If you have a legacy database and/or experience
	// garbage unicode characters with this option, try setting it to a blank string.

	// ***********************************
	// *** Basic settings (important!) ***
	// ***********************************

	define('SELF_URL_PATH', 'http://localhost/tt-rss/');
	// Full URL of your tt-rss installation. This should be set to the
	// location of tt-rss directory, e.g. http://example.org/tt-rss/
	// You need to set this option correctly otherwise several features
	// including PUSH, bookmarklets and browser integration will not work properly.

	define('FEED_CRYPT_KEY', '');
	// WARNING: mcrypt is deprecated in php 7.1. This directive exists for backwards
	// compatibility with existing installs, new passwords are NOT going to be encrypted.
	// Use update.php --decrypt-feeds to decrypt existing passwords in the database while
	// mcrypt is still available.

	// Key used for encryption of passwords for password-protected feeds
	// in the database. A string of 24 random characters. If left blank, encryption
	// is not used. Requires mcrypt functions.
	// Warning: changing this key will make your stored feed passwords impossible
	// to decrypt.

	define('SINGLE_USER_MODE', false);
	// Operate in single user mode, disables all functionality related to
	// multiple users and authentication. Enabling this assumes you have
	// your tt-rss directory protected by other means (e.g. http auth).

	define('SIMPLE_UPDATE_MODE', false);
	// Enables fallback update mode where tt-rss tries to update feeds in
	// background while tt-rss is open in your browser.
	// If you don't have a lot of feeds and don't want to or can't run
	// background processes while not running tt-rss, this method is generally
	// viable to keep your feeds up to date.
	// Still, there are more robust (and recommended) updating methods
	// available, you can read about them here: http://tt-rss.org/wiki/UpdatingFeeds

	// *****************************
	// *** Files and directories ***
	// *****************************

	define('PHP_EXECUTABLE', '/usr/bin/php');
	// Path to PHP *COMMAND LINE* executable, used for various command-line tt-rss
	// programs and update daemon. Do not try to use CGI binary here, it won't work.
	// If you see HTTP headers being displayed while running tt-rss scripts,
	// then most probably you are using the CGI binary. If you are unsure what to
	// put in here, ask your hosting provider.

	define('LOCK_DIRECTORY', 'lock');
	// Directory for lockfiles, must be writable to the user you run
	// daemon process or cronjobs under.

	define('CACHE_DIR', 'cache');
	// Local cache directory for RSS feed content.

	define('ICONS_DIR', "feed-icons");
	define('ICONS_URL', "feed-icons");
	// Local and URL path to the directory, where feed favicons are stored.
	// Unless you really know what you're doing, please keep those relative
	// to tt-rss main directory.

	// **********************
	// *** Authentication ***
	// **********************

	// Please see PLUGINS below to configure various authentication modules.

	define('AUTH_AUTO_CREATE', true);
	// Allow authentication modules to auto-create users in tt-rss internal
	// database when authenticated successfully.

	define('AUTH_AUTO_LOGIN', true);
	// Automatically login user on remote or other kind of externally supplied
	// authentication, otherwise redirect to login form as normal.
	// If set to true, users won't be able to set application language
	// and settings profile.

	// *********************
	// *** Feed settings ***
	// *********************

	define('FORCE_ARTICLE_PURGE', 0);
	// When this option is not 0, users ability to control feed purging
	// intervals is disabled and all articles (which are not starred)
	// older than this amount of days are purged.

	// *** PubSubHubbub settings ***

	define('PUBSUBHUBBUB_HUB', '');
	// URL to a PubSubHubbub-compatible hub server. If defined, "Published
	// articles" generated feed would automatically become PUSH-enabled.

	define('PUBSUBHUBBUB_ENABLED', false);
	// Enable client PubSubHubbub support in tt-rss. When disabled, tt-rss
	// won't try to subscribe to PUSH feed updates.

	// ****************************
	// *** Sphinx search plugin ***
	// ****************************

	define('SPHINX_SERVER', 'localhost:9312');
	// Hostname:port combination for the Sphinx server.

	define('SPHINX_INDEX', 'ttrss, delta');
	// Index name in Sphinx configuration. You can specify multiple indexes
	// as a comma-separated string.
	// Example configuration files are available on tt-rss wiki.

	// ***********************************
	// *** Self-registrations by users ***
	// ***********************************

	define('ENABLE_REGISTRATION', false);
	// Allow users to register themselves. Please be aware that allowing
	// random people to access your tt-rss installation is a security risk
	// and potentially might lead to data loss or server exploit. Disabled
	// by default.

	define('REG_NOTIFY_ADDRESS', 'user@your.domain.dom');
	// Email address to send new user notifications to.

	define('REG_MAX_USERS', 10);
	// Maximum amount of users which will be allowed to register on this
	// system. 0 - no limit.

	// **********************************
	// *** Cookies and login sessions ***
	// **********************************

	define('SESSION_COOKIE_LIFETIME', 86400);
	// Default lifetime of a session (e.g. login) cookie. In seconds,
	// 0 means cookie will be deleted when browser closes.

	// *********************************
	// *** Email and digest settings ***
	// *********************************

	define('SMTP_FROM_NAME', 'Tiny Tiny RSS');
	define('SMTP_FROM_ADDRESS', 'noreply@your.domain.dom');
	// Name, address and subject for sending outgoing mail. This applies
	// to password reset notifications, digest emails and any other mail.

	define('DIGEST_SUBJECT', '[tt-rss] New headlines for last 24 hours');
	// Subject line for email digests

	define('SMTP_SERVER', '');
	// Hostname:port combination to send outgoing mail (i.e. localhost:25).
	// Blank - use system MTA.

	define('SMTP_LOGIN', '');
	define('SMTP_PASSWORD', '');
	// These two options enable SMTP authentication when sending
	// outgoing mail. Only used with SMTP_SERVER.

	define('SMTP_SECURE', '');
	// Used to select a secure SMTP connection. Allowed values: ssl, tls,
	// or empty.

	// ***************************************
	// *** Other settings (less important) ***
	// ***************************************

	define('CHECK_FOR_UPDATES', true);
	// Check for updates automatically if running Git version

	define('ENABLE_GZIP_OUTPUT', false);
	// Selectively gzip output to improve wire performance. This requires
	// PHP Zlib extension on the server.
	// Enabling this can break tt-rss in several httpd/php configurations,
	// if you experience weird errors and tt-rss failing to start, blank pages
	// after login, or content encoding errors, disable it.

	define('PLUGINS', 'auth_internal, note');
	// Comma-separated list of plugins to load automatically for all users.
	// System plugins have to be specified here. Please enable at least one
	// authentication plugin here (auth_*).
	// Users may enable other user plugins from Preferences/Plugins but may not
	// disable plugins specified in this list.
	// Disabling auth_internal in this list would automatically disable
	// reset password link on the login form.

	define('LOG_DESTINATION', 'sql');
	// Log destination to use. Possible values: sql (uses internal logging
	// you can read in Preferences -> System), syslog - logs to system log.
	// Setting this to blank uses PHP logging (usually to http server
	// error.log).

	define('CONFIG_VERSION', 26);
	// Expected config version. Please update this option in config.php
	// if necessary (after migrating all new options from this file).

	// vim:ft=php
