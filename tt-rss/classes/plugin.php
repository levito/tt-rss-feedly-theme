<?php
abstract class Plugin {
	const API_VERSION_COMPAT = 1;

	/** @var PDO */
	protected $pdo;

	/* @var PluginHost $host */
	abstract function init($host);

	abstract function about();
	// return array(1.0, "plugin", "No description", "No author", false);

	function __construct() {
		$this->pdo = Db::pdo();
	}

	function flags() {
		/* associative array, possible keys:
			needs_curl = boolean
		*/
		return array();
	}

	/**
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	function is_public_method($method) {
		return false;
	}

	function get_js() {
		return "";
	}

	function get_prefs_js() {
		return "";
	}

	function api_version() {
		return Plugin::API_VERSION_COMPAT;
	}
}