<?php
	define('VERSION_STATIC', '18.12');

	function get_version() {
		date_default_timezone_set('UTC');
		$root_dir = dirname(dirname(__FILE__));

		if (is_dir("$root_dir/.git") && file_exists("$root_dir/.git/HEAD")) {
			$head = trim(file_get_contents("$root_dir/.git/HEAD"));

			if ($head) {
				$matches = array();

				if (preg_match("/^ref: (.*)/", $head, $matches)) {
					$ref = $matches[1];

					if (!file_exists("$root_dir/.git/$ref"))
						return VERSION_STATIC;
					$suffix = substr(trim(file_get_contents("$root_dir/.git/$ref")), 0, 7);
					$timestamp = filemtime("$root_dir/.git/$ref");

					define("GIT_VERSION_HEAD", $suffix);
					define("GIT_VERSION_TIMESTAMP", $timestamp);

					return VERSION_STATIC . " ($suffix)";

				} else {
					$suffix = substr(trim($head), 0, 7);
					$timestamp = filemtime("$root_dir/.git/HEAD");

					define("GIT_VERSION_HEAD", $suffix);
					define("GIT_VERSION_TIMESTAMP", $timestamp);

					return VERSION_STATIC . " ($suffix)";
				}
			}
		}

		return VERSION_STATIC;

	}

	define('VERSION', get_version());
