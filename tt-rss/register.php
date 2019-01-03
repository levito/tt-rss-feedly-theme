<?php
	// This file uses two additional include files:
	//
	// 1) templates/register_notice.txt - displayed above the registration form
	// 2) register_expire_do.php - contains user expiration queries when necessary

	set_include_path(dirname(__FILE__) ."/include" . PATH_SEPARATOR .
		get_include_path());

	require_once "autoload.php";
	require_once "functions.php";
	require_once "sessions.php";
	require_once "sanity_check.php";
	require_once "config.php";
	require_once "db.php";

	startup_gettext();

	$action = $_REQUEST["action"];

	if (!init_plugins()) return;

	if ($_REQUEST["format"] == "feed") {
		header("Content-Type: text/xml");

		print '<?xml version="1.0" encoding="utf-8"?>';
		print "<feed xmlns=\"http://www.w3.org/2005/Atom\">
			<id>".htmlspecialchars(SELF_URL_PATH . "/register.php")."</id>
			<title>Tiny Tiny RSS registration slots</title>
			<link rel=\"self\" href=\"".htmlspecialchars(SELF_URL_PATH . "/register.php?format=feed")."\"/>
			<link rel=\"alternate\" href=\"".htmlspecialchars(SELF_URL_PATH)."\"/>";

		if (ENABLE_REGISTRATION) {
			$result = db_query( "SELECT COUNT(*) AS cu FROM ttrss_users");
			$num_users = db_fetch_result($result, 0, "cu");

			$num_users = REG_MAX_USERS - $num_users;
			if ($num_users < 0) $num_users = 0;
			$reg_suffix = "enabled";
		} else {
			$num_users = 0;
			$reg_suffix = "disabled";
		}

		print "<entry>
			<id>".htmlspecialchars(SELF_URL_PATH)."/register.php?$num_users"."</id>
			<link rel=\"alternate\" href=\"".htmlspecialchars(SELF_URL_PATH . "/register.php")."\"/>";

		print "<title>$num_users slots are currently available, registration $reg_suffix</title>";
		print "<summary>$num_users slots are currently available, registration $reg_suffix</summary>";

		print "</entry>";

		print "</feed>";

		return;
	}

	/* Remove users which didn't login after receiving their registration information */

	if (DB_TYPE == "pgsql") {
		db_query( "DELETE FROM ttrss_users WHERE last_login IS NULL
				AND created < NOW() - INTERVAL '1 day' AND access_level = 0");
	} else {
		db_query( "DELETE FROM ttrss_users WHERE last_login IS NULL
				AND created < DATE_SUB(NOW(), INTERVAL 1 DAY) AND access_level = 0");
	}

	if (file_exists("register_expire_do.php")) {
		require_once "register_expire_do.php";
	}

	if ($action == "check") {
		header("Content-Type: application/xml");

		$login = trim(db_escape_string( $_REQUEST['login']));

		$result = db_query( "SELECT id FROM ttrss_users WHERE
			LOWER(login) = LOWER('$login')");

		$is_registered = db_num_rows($result) > 0;

		print "<result>";

		printf("%d", $is_registered);

		print "</result>";

		return;
	}
?>

<html>
<head>
<title>Create new account</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<?php echo stylesheet_tag("css/default.css") ?>
<?php echo javascript_tag("js/common.js") ?>
<?php echo javascript_tag("lib/prototype.js") ?>
<?php echo javascript_tag("lib/scriptaculous/scriptaculous.js?load=effects,controls") ?>
</head>

<script type="text/javascript">

	function checkUsername() {

		try {
			var f = document.forms['register_form'];
			var login = f.login.value;

			if (login == "") {
				new Effect.Highlight(f.login);
				f.sub_btn.disabled = true;
				return false;
			}

			var query = "register.php?action=check&login=" +
					encodeURIComponent(login);

			new Ajax.Request(query, {
				onComplete: function(transport) {

					try {

						var reply = transport.responseXML;

						var result = reply.getElementsByTagName('result')[0];
						var result_code = result.firstChild.nodeValue;

						if (result_code == 0) {
							new Effect.Highlight(f.login, {startcolor : '#00ff00'});
							f.sub_btn.disabled = false;
						} else {
							new Effect.Highlight(f.login, {startcolor : '#ff0000'});
							f.sub_btn.disabled = true;
						}
					} catch (e) {
						App.Error.report(e);
					}

				} });

		} catch (e) {
			App.Error.report(e);
		}

		return false;

	}

	function validateRegForm() {
		try {

			var f = document.forms['register_form'];

			if (f.login.value.length == 0) {
				new Effect.Highlight(f.login);
				return false;
			}

			if (f.email.value.length == 0) {
				new Effect.Highlight(f.email);
				return false;
			}

			if (f.turing_test.value.length == 0) {
				new Effect.Highlight(f.turing_test);
				return false;
			}

			return true;

		} catch (e) {
			alert(e.stack);
			return false;
		}
	}

</script>

<body class="claro ttrss_utility">

<div class="floatingLogo"><img src="images/logo_small.png"></div>

<h1><?php echo __("Create new account") ?></h1>

<div class="content">

<?php
		if (!ENABLE_REGISTRATION) {
			print_error(__("New user registrations are administratively disabled."));

			print "<p><form method=\"GET\" action=\"backend.php\">
				<input type=\"hidden\" name=\"op\" value=\"logout\">
				<input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
				</form>";
			return;
		}
?>

<?php if (REG_MAX_USERS > 0) {
		$result = db_query( "SELECT COUNT(*) AS cu FROM ttrss_users");
		$num_users = db_fetch_result($result, 0, "cu");
} ?>

<?php if (!REG_MAX_USERS || $num_users < REG_MAX_USERS) { ?>

	<!-- If you have any rules or ToS you'd like to display, enter them here -->

	<?php	if (file_exists("templates/register_notice.txt")) {
			require_once "templates/register_notice.txt";
	} ?>

	<?php if (!$action) { ?>

	<p><?php echo __('Your temporary password will be sent to the specified email. Accounts, which were not logged in once, are erased automatically 24 hours after temporary password is sent.') ?></p>

	<form action="register.php" method="POST" name="register_form">
	<input type="hidden" name="action" value="do_register">
	<table>
	<tr>
	<td><?php echo __('Desired login:') ?></td><td>
		<input name="login" required>
	</td><td>
		<input type="submit" value="<?php echo __('Check availability') ?>" onclick='return checkUsername()'>
	</td></tr>
	<tr><td><?php echo __('Email:') ?></td><td>
		<input name="email" type="email" required>
	</td></tr>
	<tr><td><?php echo __('How much is two plus two:') ?></td><td>
		<input name="turing_test" required></td></tr>
	<tr><td colspan="2" align="right">
	<input type="submit" name="sub_btn" value="<?php echo __('Submit registration') ?>"
			disabled="disabled" onclick='return validateRegForm()'>
	</td></tr>
	</table>
	</form>

	<?php print "<p><form method=\"GET\" action=\"index.php\">
				<input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
				</form>"; ?>

	<?php } else if ($action == "do_register") { ?>

	<?php
		$login = mb_strtolower(trim(db_escape_string( $_REQUEST["login"])));
		$email = trim(db_escape_string( $_REQUEST["email"]));
		$test = trim(db_escape_string( $_REQUEST["turing_test"]));

		if (!$login || !$email || !$test) {
			print_error(__("Your registration information is incomplete."));
			print "<p><form method=\"GET\" action=\"index.php\">
				<input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
				</form>";
			return;
		}

		if ($test == "four" || $test == "4") {

			$result = db_query( "SELECT id FROM ttrss_users WHERE
				login = '$login'");

			$is_registered = db_num_rows($result) > 0;

			if ($is_registered) {
				print_error(__('Sorry, this username is already taken.'));
				print "<p><form method=\"GET\" action=\"index.php\">
				<input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
				</form>";
			} else {

				$password = make_password();

				$salt = substr(bin2hex(get_random_bytes(125)), 0, 250);
				$pwd_hash = encrypt_password($password, $salt, true);

				db_query( "INSERT INTO ttrss_users
					(login,pwd_hash,access_level,last_login, email, created, salt)
					VALUES ('$login', '$pwd_hash', 0, null, '$email', NOW(), '$salt')");

				$result = db_query( "SELECT id FROM ttrss_users WHERE
					login = '$login' AND pwd_hash = '$pwd_hash'");

				if (db_num_rows($result) != 1) {
					print_error(__('Registration failed.'));
					print "<p><form method=\"GET\" action=\"index.php\">
					<input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
					</form>";
				} else {

					$new_uid = db_fetch_result($result, 0, "id");

					initialize_user( $new_uid);

					$reg_text = "Hi!\n".
						"\n".
						"You are receiving this message, because you (or somebody else) have opened\n".
						"an account at Tiny Tiny RSS.\n".
						"\n".
						"Your login information is as follows:\n".
						"\n".
						"Login: $login\n".
						"Password: $password\n".
						"\n".
						"Don't forget to login at least once to your new account, otherwise\n".
						"it will be deleted in 24 hours.\n".
						"\n".
						"If that wasn't you, just ignore this message. Thanks.";

					$mailer = new Mailer();
					$rc = $mailer->mail(["to_address" => $email,
						"subject" => "Registration information for Tiny Tiny RSS",
						"message" => $reg_text]);

					if (!$rc) print_error($mailer->error());

					$reg_text = "Hi!\n".
						"\n".
						"New user had registered at your Tiny Tiny RSS installation.\n".
						"\n".
						"Login: $login\n".
						"Email: $email\n";

					$mailer = new Mailer();
					$rc = $mailer->mail(["to_address" => REG_NOTIFY_ADDRESS,
						"subject" => "Registration notice for Tiny Tiny RSS",
						"message" => $reg_text]);

					if (!$rc) print_error($mailer->error());

					print_notice(__("Account created successfully."));

					print "<p><form method=\"GET\" action=\"index.php\">
					<input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
					</form>";

				}

			}

			} else {
				print_error('Plese check the form again, you have failed the robot test.');
				print "<p><form method=\"GET\" action=\"index.php\">
				<input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
				</form>";

			}
		}
	?>

<?php } else { ?>

	<?php print_notice(__('New user registrations are currently closed.')) ?>

	<?php print "<p><form method=\"GET\" action=\"index.php\">
				<input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
				</form>"; ?>

<?php } ?>

	</div>

</body>
</html>
