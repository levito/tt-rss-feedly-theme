<?php
	/* WARNING!
	 *
	 * If you modify this file, you are ON YOUR OWN!
	 *
	 * Believe it or not, all of the checks below are required to succeed for
	 * tt-rss to actually function properly.
	 *
	 * If you think you have a better idea about what is or isn't required, feel
	 * free to modify the file, note though that you are therefore automatically
	 * disqualified from any further support by official channels, e.g. tt-rss.org
	 * issue tracker or the forums.
	 *
	 * If you come crying when stuff inevitably breaks, you will be mocked and told
	 * to get out. */

	function make_self_url_path() {
		$proto = is_server_https() ? 'https' : 'http';
		$url_path = $proto . '://' . $_SERVER["HTTP_HOST"] . parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

		return $url_path;
	}

	/**
	 * @SuppressWarnings(PHPMD.UnusedLocalVariable)
	 */
	function initial_sanity_check() {

		$errors = array();

		if (!file_exists("config.php")) {
			array_push($errors, "Configuration file not found. Looks like you forgot to copy config.php-dist to config.php and edit it.");
		} else {

			require_once "sanity_config.php";

			if (file_exists("install") && !file_exists("config.php")) {
				array_push($errors, "Please copy config.php-dist to config.php or run the installer in install/");
			}

			if (strpos(PLUGINS, "auth_") === FALSE) {
				array_push($errors, "Please enable at least one authentication module via PLUGINS constant in config.php");
			}

			if (function_exists('posix_getuid') && posix_getuid() == 0) {
				array_push($errors, "Please don't run this script as root.");
			}

			if (version_compare(PHP_VERSION, '5.6.0', '<')) {
				array_push($errors, "PHP version 5.6.0 or newer required. You're using " . PHP_VERSION . ".");
			}

			if (CONFIG_VERSION != EXPECTED_CONFIG_VERSION) {
				array_push($errors, "Configuration file (config.php) has incorrect version. Update it with new options from config.php-dist and set CONFIG_VERSION to the correct value.");
			}

			if (!is_writable(CACHE_DIR . "/images")) {
				array_push($errors, "Image cache is not writable (chmod -R 777 ".CACHE_DIR."/images)");
			}

			if (!is_writable(CACHE_DIR . "/upload")) {
				array_push($errors, "Upload cache is not writable (chmod -R 777 ".CACHE_DIR."/upload)");
			}

			if (!is_writable(CACHE_DIR . "/export")) {
				array_push($errors, "Data export cache is not writable (chmod -R 777 ".CACHE_DIR."/export)");
			}

			if (GENERATED_CONFIG_CHECK != EXPECTED_CONFIG_VERSION) {
				array_push($errors,
					"Configuration option checker sanity_config.php is outdated, please recreate it using ./utils/regen_config_checks.sh");
			}

			foreach ($required_defines as $d) {
				if (!defined($d)) {
					array_push($errors,
						"Required configuration file parameter $d is not defined in config.php. You might need to copy it from config.php-dist.");
				}
			}

			if (SINGLE_USER_MODE && class_exists("PDO")) {
			    $pdo = DB::pdo();

				$res = $pdo->query("SELECT id FROM ttrss_users WHERE id = 1");

				if (!$res->fetch()) {
					array_push($errors, "SINGLE_USER_MODE is enabled in config.php but default admin account is not found.");
				}
			}

			$ref_self_url_path = make_self_url_path();
			$ref_self_url_path = preg_replace("/\w+\.php$/", "", $ref_self_url_path);

			if (SELF_URL_PATH == "http://example.org/tt-rss/") {
				array_push($errors,
						"Please set SELF_URL_PATH to the correct value for your server (possible value: <b>$ref_self_url_path</b>)");
			}

			if (isset($_SERVER["HTTP_HOST"]) &&
				(!defined('_SKIP_SELF_URL_PATH_CHECKS') || !_SKIP_SELF_URL_PATH_CHECKS) &&
				SELF_URL_PATH != $ref_self_url_path && SELF_URL_PATH != mb_substr($ref_self_url_path, 0, mb_strlen($ref_self_url_path)-1)) {
				array_push($errors,
					"Please set SELF_URL_PATH to the correct value detected for your server: <b>$ref_self_url_path</b>");
			}

			if (!is_writable(ICONS_DIR)) {
				array_push($errors, "ICONS_DIR defined in config.php is not writable (chmod -R 777 ".ICONS_DIR.").\n");
			}

			if (!is_writable(LOCK_DIRECTORY)) {
				array_push($errors, "LOCK_DIRECTORY defined in config.php is not writable (chmod -R 777 ".LOCK_DIRECTORY.").\n");
			}

			if (!function_exists("curl_init") && !ini_get("allow_url_fopen")) {
				array_push($errors, "PHP configuration option allow_url_fopen is disabled, and CURL functions are not present. Either enable allow_url_fopen or install PHP extension for CURL.");
			}

			if (!function_exists("json_encode")) {
				array_push($errors, "PHP support for JSON is required, but was not found.");
			}

			if (DB_TYPE == "mysql" && !function_exists("mysqli_connect")) {
				array_push($errors, "PHP support for MySQL is required for configured DB_TYPE in config.php.");
			}

			if (DB_TYPE == "pgsql" && !function_exists("pg_connect")) {
				array_push($errors, "PHP support for PostgreSQL is required for configured DB_TYPE in config.php");
			}

			if (!class_exists("PDO")) {
				array_push($errors, "PHP support for PDO is required but was not found.");
			}

			if (!function_exists("mb_strlen")) {
				array_push($errors, "PHP support for mbstring functions is required but was not found.");
			}

			if (!function_exists("hash")) {
				array_push($errors, "PHP support for hash() function is required but was not found.");
			}

			if (ini_get("safe_mode")) {
				array_push($errors, "PHP safe mode setting is obsolete and not supported by tt-rss.");
			}

			if (!function_exists("mime_content_type")) {
				array_push($errors, "PHP function mime_content_type() is missing, try enabling fileinfo module.");
			}

			if (!class_exists("DOMDocument")) {
				array_push($errors, "PHP support for DOMDocument is required, but was not found.");
			}

			if (DB_TYPE == "mysql") {
				$bad_tables = check_mysql_tables();

				if (count($bad_tables) > 0) {
					$bad_tables_fmt = [];

					foreach ($bad_tables as $bt) {
						array_push($bad_tables_fmt, sprintf("%s (%s)", $bt['table_name'], $bt['engine']));
					}

					$msg = "<p>The following tables use an unsupported MySQL engine: <b>" .
						implode(", ", $bad_tables_fmt) . "</b>.</p>";

					$msg .= "<p>The only supported engine on MySQL is InnoDB. MyISAM lacks functionality to run
						tt-rss.
						Please backup your data (via OPML) and re-import the schema before continuing.</p>
						<p><b>WARNING: importing the schema would mean LOSS OF ALL YOUR DATA.</b></p>";


					array_push($errors, $msg);
				}
			}
		}

		if (count($errors) > 0 && $_SERVER['REQUEST_URI']) { ?>
			<html>
			<head>
			<title>Startup failed</title>
				<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
				<link rel="stylesheet" type="text/css" href="css/default.css">
			</head>
		<body class='sanity_failed claro ttrss_utility'>
		<div class="floatingLogo"><img src="images/logo_small.png"></div>
			<div class="content">

			<h1>Startup failed</h1>

			<p>Tiny Tiny RSS was unable to start properly. This usually means a misconfiguration or an incomplete upgrade. Please fix
			errors indicated by the following messages:</p>

			<?php foreach ($errors as $error) { echo format_error($error); } ?>

			<p>You might want to check tt-rss <a href="http://tt-rss.org/wiki">wiki</a> or the
				<a href="http://tt-rss.org/forum">forums</a> for more information. Please search the forums before creating new topic
				for your question.</p>

		</div>
		</body>
		</html>

		<?php
			die;
		} else if (count($errors) > 0) {
			echo "Tiny Tiny RSS was unable to start properly. This usually means a misconfiguration or an incomplete upgrade.\n";
			echo "Please fix errors indicated by the following messages:\n\n";

			foreach ($errors as $error) {
				echo " * $error\n";
			}

			echo "\nYou might want to check tt-rss wiki or the forums for more information.\n";
			echo "Please search the forums before creating new topic for your question.\n";

			exit(-1);
		}
	}

	initial_sanity_check();

?>
