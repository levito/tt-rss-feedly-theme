<?php
function format_backtrace($trace) {
	$rv = "";
	$idx = 1;

	if (is_array($trace)) {
		foreach ($trace as $e) {
			if (isset($e["file"]) && isset($e["line"])) {
				$fmt_args = [];

				if (is_array($e["args"])) {
					foreach ($e["args"] as $a) {
						if (!is_object($a)) {
							array_push($fmt_args, $a);
						} else {
							array_push($fmt_args, "[" . get_class($a) . "]");
						}
					}
				}

				$filename = str_replace(dirname(__DIR__) . "/", "", $e["file"]);

				$rv .= sprintf("%d. %s(%s): %s(%s)\n",
					$idx, $filename, $e["line"], $e["function"], implode(", ", $fmt_args));

				$idx++;
			}
		}
	}

	return $rv;
}

function ttrss_error_handler($errno, $errstr, $file, $line, $context) {
	if (error_reporting() == 0 || !$errno) return false;

	$file = substr(str_replace(dirname(dirname(__FILE__)), "", $file), 1);

	$context = format_backtrace(debug_backtrace());
	$errstr = truncate_middle($errstr, 16384, " (...) ");

	if (class_exists("Logger"))
		return Logger::get()->log_error($errno, $errstr, $file, $line, $context);
}

function ttrss_fatal_handler() {
	global $last_query;

	$error = error_get_last();

	if ($error !== NULL) {
		$errno = $error["type"];
		$file = $error["file"];
		$line = $error["line"];
		$errstr  = $error["message"];

		if (!$errno) return false;

		$context = format_backtrace(debug_backtrace());

		$file = substr(str_replace(dirname(dirname(__FILE__)), "", $file), 1);

		if ($last_query) $errstr .= " [Last query: $last_query]";

		if (class_exists("Logger"))
			return Logger::get()->log_error($errno, $errstr, $file, $line, $context);
	}

	return false;
}

register_shutdown_function('ttrss_fatal_handler');
set_error_handler('ttrss_error_handler');

