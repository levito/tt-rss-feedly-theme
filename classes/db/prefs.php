<?php
class Db_Prefs {
	private $pdo;
	private static $instance;
	private $cache;

	function __construct() {
		$this->pdo = Db::pdo();
		$this->cache = array();

		if ($_SESSION["uid"]) $this->cache();
	}

	private function __clone() {
		//
	}

	public static function get() {
		if (self::$instance == null)
			self::$instance = new self();

		return self::$instance;
	}

	function cache() {
		$user_id = $_SESSION["uid"];
		@$profile = $_SESSION["profile"];

		if (!is_numeric($profile) || !$profile || get_schema_version() < 63) $profile = null;

		$sth = $this->pdo->prepare("SELECT
			value,ttrss_prefs_types.type_name as type_name,ttrss_prefs.pref_name AS pref_name
			FROM
				ttrss_user_prefs,ttrss_prefs,ttrss_prefs_types
			WHERE
				(profile = :profile OR (:profile IS NULL AND profile IS NULL)) AND
				ttrss_prefs.pref_name NOT LIKE '_MOBILE%' AND
				ttrss_prefs_types.id = type_id AND
				owner_uid = :uid AND
				ttrss_user_prefs.pref_name = ttrss_prefs.pref_name");

		$sth->execute([":profile" => $profile, ":uid" => $user_id]);

		while ($line = $sth->fetch()) {
			if ($user_id == $_SESSION["uid"]) {
				$pref_name = $line["pref_name"];

				$this->cache[$pref_name]["type"] = $line["type_name"];
				$this->cache[$pref_name]["value"] = $line["value"];
			}
		}
	}

	function read($pref_name, $user_id = false, $die_on_error = false) {

		if (!$user_id) {
			$user_id = $_SESSION["uid"];
			@$profile = $_SESSION["profile"];
		} else {
			$profile = false;
		}

		if ($user_id == $_SESSION['uid'] && isset($this->cache[$pref_name])) {
			$tuple = $this->cache[$pref_name];
			return $this->convert($tuple["value"], $tuple["type"]);
		}

		if (!is_numeric($profile) || !$profile || get_schema_version() < 63) $profile = null;

		$sth = $this->pdo->prepare("SELECT
			value,ttrss_prefs_types.type_name as type_name
			FROM
				ttrss_user_prefs,ttrss_prefs,ttrss_prefs_types
			WHERE
				(profile = :profile OR (:profile IS NULL AND profile IS NULL)) AND
				ttrss_user_prefs.pref_name = :pref_name AND
				ttrss_prefs_types.id = type_id AND
				owner_uid = :uid AND
				ttrss_user_prefs.pref_name = ttrss_prefs.pref_name");
		$sth->execute([":uid" => $user_id, ":profile" => $profile, ":pref_name" => $pref_name]);

		if ($row = $sth->fetch()) {
			$value = $row["value"];
			$type_name = $row["type_name"];

			if ($user_id == $_SESSION["uid"]) {
				$this->cache[$pref_name]["type"] = $type_name;
				$this->cache[$pref_name]["value"] = $value;
			}

			return $this->convert($value, $type_name);

		} else {
			user_error("Fatal error, unknown preferences key: $pref_name (owner: $user_id)", $die_on_error ? E_USER_ERROR : E_USER_WARNING);
			return null;
		}
	}

	function convert($value, $type_name) {
		if ($type_name == "bool") {
			return $value == "true";
		} else if ($type_name == "integer") {
			return (int)$value;
		} else {
			return $value;
		}
	}

	function write($pref_name, $value, $user_id = false, $strip_tags = true) {
		if ($strip_tags) $value = strip_tags($value);

		if (!$user_id) {
			$user_id = $_SESSION["uid"];
			@$profile = $_SESSION["profile"];
		} else {
			$profile = null;
		}

		if (!is_numeric($profile) || !$profile || get_schema_version() < 63) $profile = null;

		$type_name = "";
		$current_value = "";

		if (isset($this->cache[$pref_name])) {
			$type_name = $this->cache[$pref_name]["type"];
			$current_value = $this->cache[$pref_name]["value"];
		}

		if (!$type_name) {
			$sth = $this->pdo->prepare("SELECT type_name
				FROM ttrss_prefs,ttrss_prefs_types
				WHERE pref_name = ? AND type_id = ttrss_prefs_types.id");
			$sth->execute([$pref_name]);

			if ($row = $sth->fetch())
				$type_name = $row["type_name"];

		} else if ($current_value == $value) {
			return;
		}

		if ($type_name) {
			if ($type_name == "bool") {
				if ($value == "1" || $value == "true") {
					$value = "true";
				} else {
					$value = "false";
				}
			} else if ($type_name == "integer") {
				$value = (int)$value;
			}

			if ($pref_name == 'USER_TIMEZONE' && $value == '') {
				$value = 'UTC';
			}

			$sth = $this->pdo->prepare("UPDATE ttrss_user_prefs SET
				value = :value WHERE pref_name = :pref_name
					AND (profile = :profile OR (:profile IS NULL AND profile IS NULL))
					AND owner_uid = :uid");

			$sth->execute([":pref_name" => $pref_name, ":value" => $value, ":uid" => $user_id, ":profile" => $profile]);

			if ($user_id == $_SESSION["uid"]) {
				$this->cache[$pref_name]["type"] = $type_name;
				$this->cache[$pref_name]["value"] = $value;
			}
		}
	}

}
