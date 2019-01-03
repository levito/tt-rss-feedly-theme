<?php
class Auth_Remote extends Plugin implements IAuthModule {

	private $host;
	/* @var Auth_Base $base */
	private $base;

	function about() {
		return array(1.0,
			"Authenticates against remote password (e.g. supplied by Apache)",
			"fox",
			true);
	}

	/* @var PluginHost $host */
	function init($host ) {
		$this->host = $host;
		$this->base = new Auth_Base();

		$host->add_hook($host::HOOK_AUTH_USER, $this);
	}

	function get_login_by_ssl_certificate() {
		$cert_serial = get_ssl_certificate_id();

		if ($cert_serial) {
			$sth = $this->pdo->prepare("SELECT login FROM ttrss_user_prefs, ttrss_users
				WHERE pref_name = 'SSL_CERT_SERIAL' AND value = ? AND
				owner_uid = ttrss_users.id");
			$sth->execute([$cert_serial]);

			if ($row = $sth->fetch()) {
				return $row['login'];
			}
		}

		return "";
	}

	/**
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	function authenticate($login, $password) {
		$try_login = $_SERVER["REMOTE_USER"];

		// php-cgi
		if (!$try_login) $try_login = $_SERVER["REDIRECT_REMOTE_USER"];
		if (!$try_login) $try_login = $_SERVER["PHP_AUTH_USER"];

		if (!$try_login) $try_login = $this->get_login_by_ssl_certificate();

		if ($try_login) {
			$user_id = $this->base->auto_create_user($try_login, $password);

			if ($user_id) {
				$_SESSION["fake_login"] = $try_login;
				$_SESSION["fake_password"] = "******";
				$_SESSION["hide_hello"] = true;
				$_SESSION["hide_logout"] = true;

				// LemonLDAP can send user informations via HTTP HEADER
				if (defined('AUTH_AUTO_CREATE') && AUTH_AUTO_CREATE){
					// update user name
					$fullname = $_SERVER['HTTP_USER_NAME'] ? $_SERVER['HTTP_USER_NAME'] : $_SERVER['AUTHENTICATE_CN'];
					if ($fullname){
						$sth = $this->pdo->prepare("UPDATE ttrss_users SET full_name = ? WHERE id = ?");
						$sth->execute([$fullname, $user_id]);
					}
					// update user mail
					$email = $_SERVER['HTTP_USER_MAIL'] ? $_SERVER['HTTP_USER_MAIL'] : $_SERVER['AUTHENTICATE_MAIL'];
					if ($email){
						$sth = $this->pdo->prepare("UPDATE ttrss_users SET email = ? WHERE id = ?");
						$sth->execute([$email, $user_id]);
					}
				}

				return $user_id;
			}
		}

		return false;
	}

	function api_version() {
		return 2;
	}

}
