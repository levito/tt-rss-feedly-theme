<?php
	error_reporting(E_ERROR | E_PARSE);

	require_once "../config.php";

	set_include_path(dirname(__FILE__) . PATH_SEPARATOR .
		dirname(dirname(__FILE__)) . PATH_SEPARATOR .
		dirname(dirname(__FILE__)) . "/include" . PATH_SEPARATOR .
  		get_include_path());

	chdir("..");

	define('TTRSS_SESSION_NAME', 'ttrss_api_sid');
	define('NO_SESSION_AUTOSTART', true);

	require_once "autoload.php";
	require_once "db.php";
	require_once "db-prefs.php";
	require_once "functions.php";
	require_once "sessions.php";

	ini_set('session.use_cookies', 0);
	ini_set("session.gc_maxlifetime", 86400);

	define('AUTH_DISABLE_OTP', true);

	if (defined('ENABLE_GZIP_OUTPUT') && ENABLE_GZIP_OUTPUT &&
			function_exists("ob_gzhandler")) {

		ob_start("ob_gzhandler");
	} else {
		ob_start();
	}

	$input = file_get_contents("php://input");

	if (defined('_API_DEBUG_HTTP_ENABLED') && _API_DEBUG_HTTP_ENABLED) {
		// Override $_REQUEST with JSON-encoded data if available
		// fallback on HTTP parameters
		if ($input) {
			$input = json_decode($input, true);
			if ($input) $_REQUEST = $input;
		}
	} else {
		// Accept JSON only
		$input = json_decode($input, true);
		$_REQUEST = $input;
	}

	if ($_REQUEST["sid"]) {
		session_id($_REQUEST["sid"]);
		@session_start();
	} else if (defined('_API_DEBUG_HTTP_ENABLED')) {
		@session_start();
	}

	startup_gettext();

	if (!init_plugins()) return;

	if ($_SESSION["uid"]) {
		if (!validate_session()) {
			header("Content-Type: text/json");

			print json_encode(array("seq" => -1,
				"status" => 1,
				"content" => array("error" => "NOT_LOGGED_IN")));

			return;
		}

		load_user_plugins( $_SESSION["uid"]);
	}

	$method = strtolower($_REQUEST["op"]);

	$handler = new API($_REQUEST);

	if ($handler->before($method)) {
		if ($method && method_exists($handler, $method)) {
			$handler->$method();
		} else if (method_exists($handler, 'index')) {
			$handler->index($method);
		}
		$handler->after();
	}

	header("Api-Content-Length: " . ob_get_length());

	ob_end_flush();

