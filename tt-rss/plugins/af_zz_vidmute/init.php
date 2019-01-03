<?php
class Af_Zz_VidMute extends Plugin {
	private $host;

	function about() {
		return array(1.0,
			"Mute audio in HTML5 videos",
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