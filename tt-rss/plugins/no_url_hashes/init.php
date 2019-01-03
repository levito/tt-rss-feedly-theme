<?php
class No_URL_Hashes extends Plugin {
	private $host;

	function about() {
		return array(1.0,
			"Disable URL hash usage (e.g. #f=10, etc)",
			"fox");
	}

	function init($host) {
		$this->host = $host;

	}

	function get_js() {
		return file_get_contents(__DIR__ . "/init.js");
	}

	function api_version() {
		return 2;
	}

}