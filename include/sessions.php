<?php
	// Original from http://www.daniweb.com/code/snippet43.html

	require_once "config.php";
	require_once "classes/db.php";
	require_once "autoload.php";
	require_once "errorhandler.php";
	require_once "lib/accept-to-gettext.php";
	require_once "lib/gettext/gettext.inc";
	require_once "version.php";

	$session_expire = min(2147483647 - time() - 1, max(SESSION_COOKIE_LIFETIME, 86400));
	$session_name = (!defined('TTRSS_SESSION_NAME')) ? "ttrss_sid" : TTRSS_SESSION_NAME;

	if (is_server_https()) {
		ini_set("session.cookie_secure", true);
	}

	ini_set("session.gc_probability", 75);
	ini_set("session.name", $session_name);
	ini_set("session.use_only_cookies", true);
	ini_set("session.gc_maxlifetime", $session_expire);
	ini_set("session.cookie_lifetime", min(0, SESSION_COOKIE_LIFETIME));

	function session_get_schema_version() {
		global $schema_version;

		if (!$schema_version) {
			$row = Db::pdo()->query("SELECT schema_version FROM ttrss_version")->fetch();

			$version = $row["schema_version"];

			$schema_version = $version;
			return $version;
		} else {
			return $schema_version;
		}
	}

	function validate_session() {
		if (SINGLE_USER_MODE) return true;

		if (isset($_SESSION["ref_schema_version"]) && $_SESSION["ref_schema_version"] != session_get_schema_version()) {
			$_SESSION["login_error_msg"] =
				__("Session failed to validate (schema version changed)");
			return false;
		}
		  $pdo = Db::pdo();

		if ($_SESSION["uid"]) {

			if ($_SESSION["user_agent"] != sha1($_SERVER['HTTP_USER_AGENT'])) {
				$_SESSION["login_error_msg"] = __("Session failed to validate (UA changed).");
				return false;
			}

			$sth = $pdo->prepare("SELECT pwd_hash FROM ttrss_users WHERE id = ?");
			$sth->execute([$_SESSION['uid']]);

			// user not found
			if ($row = $sth->fetch()) {
					 $pwd_hash = $row["pwd_hash"];

					 if ($pwd_hash != $_SESSION["pwd_hash"]) {

						  $_SESSION["login_error_msg"] =
								__("Session failed to validate (password changed)");

						  return false;
					 }
			} else {

					 $_SESSION["login_error_msg"] =
						  __("Session failed to validate (user not found)");

					 return false;

			}
		}

		return true;
	}

	/**
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	function ttrss_open ($s, $n) {
		return true;
	}

	function ttrss_read ($id){
		global $session_expire;

		$sth = Db::pdo()->prepare("SELECT data FROM ttrss_sessions WHERE id=?");
		$sth->execute([$id]);

		if ($row = $sth->fetch()) {
				return base64_decode($row["data"]);

		} else {
				$expire = time() + $session_expire;

				$sth = Db::pdo()->prepare("INSERT INTO ttrss_sessions (id, data, expire)
					VALUES (?, '', ?)");
				$sth->execute([$id, $expire]);

				return "";

		}

	}

	function ttrss_write ($id, $data) {
		global $session_expire;

		$data = base64_encode($data);
		$expire = time() + $session_expire;

		$sth = Db::pdo()->prepare("SELECT id FROM ttrss_sessions WHERE id=?");
		$sth->execute([$id]);

		if ($row = $sth->fetch()) {
			$sth = Db::pdo()->prepare("UPDATE ttrss_sessions SET data=?, expire=? WHERE id=?");
			$sth->execute([$data, $expire, $id]);
		} else {
			$sth = Db::pdo()->prepare("INSERT INTO ttrss_sessions (id, data, expire)
				VALUES (?, ?, ?)");
			$sth->execute([$id, $data, $expire]);
		}

		return true;
	}

	function ttrss_close () {
		return true;
	}

	function ttrss_destroy($id) {
		$sth = Db::pdo()->prepare("DELETE FROM ttrss_sessions WHERE id = ?");
		$sth->execute([$id]);

		return true;
	}

	/**
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	function ttrss_gc ($expire) {
		Db::pdo()->query("DELETE FROM ttrss_sessions WHERE expire < " . time());

		return true;
	}

	if (!SINGLE_USER_MODE /* && DB_TYPE == "pgsql" */) {
		session_set_save_handler("ttrss_open",
			"ttrss_close", "ttrss_read", "ttrss_write",
			"ttrss_destroy", "ttrss_gc");
		register_shutdown_function('session_write_close');
	}

	if (!defined('NO_SESSION_AUTOSTART')) {
		if (isset($_COOKIE[session_name()])) {
			@session_start();
		}
	}
