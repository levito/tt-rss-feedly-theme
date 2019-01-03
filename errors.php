<?php
	set_include_path(dirname(__FILE__) ."/include" . PATH_SEPARATOR .
		get_include_path());

	require_once "functions.php";

	$ERRORS[0] = "";

	$ERRORS[1] = __("This program requires XmlHttpRequest " .
			"to function properly. Your browser doesn't seem to support it.");

	$ERRORS[2] = __("This program requires cookies " .
			"to function properly. Your browser doesn't seem to support them.");

	$ERRORS[3] = __("Backend sanity check failed.");

	$ERRORS[4] = __("Frontend sanity check failed.");

	$ERRORS[5] = __("Incorrect database schema version. &lt;a href='db-updater.php'&gt;Please update&lt;/a&gt;.");

	$ERRORS[6] = __("Request not authorized.");

	$ERRORS[7] = __("No operation to perform.");

	$ERRORS[8] = __("Could not display feed: query failed. Please check label match syntax or local configuration.");

	$ERRORS[8] = __("Denied. Your access level is insufficient to access this page.");

	$ERRORS[9] = __("Configuration check failed");

	$ERRORS[10] = __("Your version of MySQL is not currently supported. Please see official site for more information.");

	$ERRORS[11] = "[This error is not returned by server]";

	$ERRORS[12] = __("SQL escaping test failed, check your database and PHP configuration");

	$ERRORS[13] = __("Method not found");

	$ERRORS[14] = __("Plugin not found");

	$ERRORS[15] = __("Encoding data as JSON failed");

	if ($_REQUEST['mode'] == 'js') {
		header("Content-Type: text/javascript; charset=UTF-8");

		print "var ERRORS = [];\n";

		foreach ($ERRORS as $id => $error) {

			$error = preg_replace("/\n/", "", $error);
			$error = preg_replace("/\"/", "\\\"", $error);

			print "ERRORS[$id] = \"$error\";\n";
		}
	}
?>
