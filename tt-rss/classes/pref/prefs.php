<?php

class Pref_Prefs extends Handler_Protected {

	private $pref_help = array();
	private $pref_sections = array();

	function csrf_ignore($method) {
		$csrf_ignored = array("index", "updateself", "customizecss", "editprefprofiles");

		return array_search($method, $csrf_ignored) !== false;
	}

	function __construct($args) {
		parent::__construct($args);

		$this->pref_sections = array(
			1 => __('General'),
			2 => __('Interface'),
			3 => __('Advanced'),
			4 => __('Digest')
		);

		$this->pref_help = array(
			"ALLOW_DUPLICATE_POSTS" => array(__("Allow duplicate articles"), ""),
			"BLACKLISTED_TAGS" => array(__("Blacklisted tags"), __("When auto-detecting tags in articles these tags will not be applied (comma-separated list).")),
			"CDM_AUTO_CATCHUP" => array(__("Automatically mark articles as read"), __("This option enables marking articles as read automatically while you scroll article list.")),
			"CDM_EXPANDED" => array(__("Automatically expand articles in combined mode"), ""),
			"COMBINED_DISPLAY_MODE" => array(__("Combined feed display"), __("Display expanded list of feed articles, instead of separate displays for headlines and article content")),
			"CONFIRM_FEED_CATCHUP" => array(__("Confirm marking feed as read"), ""),
			"DEFAULT_ARTICLE_LIMIT" => array(__("Amount of articles to display at once"), ""),
			"DEFAULT_UPDATE_INTERVAL" => array(__("Default feed update interval"), __("Shortest interval at which a feed will be checked for updates regardless of update method")),
			"DIGEST_CATCHUP" => array(__("Mark articles in e-mail digest as read"), ""),
			"DIGEST_ENABLE" => array(__("Enable e-mail digest"), __("This option enables sending daily digest of new (and unread) headlines on your configured e-mail address")),
			"DIGEST_PREFERRED_TIME" => array(__("Try to send digests around specified time"), __("Uses UTC timezone")),
			"ENABLE_API_ACCESS" => array(__("Enable API access"), __("Allows external clients to access this account through the API")),
			"ENABLE_FEED_CATS" => array(__("Enable feed categories"), ""),
			"FEEDS_SORT_BY_UNREAD" => array(__("Sort feeds by unread articles count"), ""),
			"FRESH_ARTICLE_MAX_AGE" => array(__("Maximum age of fresh articles (in hours)"), ""),
			"HIDE_READ_FEEDS" => array(__("Hide feeds with no unread articles"), ""),
			"HIDE_READ_SHOWS_SPECIAL" => array(__("Show special feeds when hiding read feeds"), ""),
			"LONG_DATE_FORMAT" => array(__("Long date format"), __("The syntax used is identical to the PHP <a href='http://php.net/manual/function.date.php'>date()</a> function.")),
			"ON_CATCHUP_SHOW_NEXT_FEED" => array(__("On catchup show next feed"), __("Automatically open next feed with unread articles after marking one as read")),
			"PURGE_OLD_DAYS" => array(__("Purge articles after this number of days (0 - disables)"), ""),
			"PURGE_UNREAD_ARTICLES" => array(__("Purge unread articles"), ""),
			"REVERSE_HEADLINES" => array(__("Reverse headline order (oldest first)"), ""),
			"SHORT_DATE_FORMAT" => array(__("Short date format"), ""),
			"SHOW_CONTENT_PREVIEW" => array(__("Show content preview in headlines list"), ""),
			"SORT_HEADLINES_BY_FEED_DATE" => array(__("Sort headlines by feed date"), __("Use feed-specified date to sort headlines instead of local import date.")),
			"SSL_CERT_SERIAL" => array(__("Login with an SSL certificate"), __("Click to register your SSL client certificate with tt-rss")),
			"STRIP_IMAGES" => array(__("Do not embed media in articles"), ""),
			"STRIP_UNSAFE_TAGS" => array(__("Strip unsafe tags from articles"), __("Strip all but most common HTML tags when reading articles.")),
			"USER_STYLESHEET" => array(__("Customize stylesheet"), __("Customize CSS stylesheet to your liking")),
			"USER_TIMEZONE" => array(__("Time zone"), ""),
			"VFEED_GROUP_BY_FEED" => array(__("Group headlines in virtual feeds"), __("Special feeds, labels, and categories are grouped by originating feeds")),
			"USER_LANGUAGE" => array(__("Language")),
			"USER_CSS_THEME" => array(__("Theme"), __("Select one of the available CSS themes"))
		);
	}

	function changepassword() {

		$old_pw = clean($_POST["old_password"]);
		$new_pw = clean($_POST["new_password"]);
		$con_pw = clean($_POST["confirm_password"]);

		if ($old_pw == "") {
			print "ERROR: ".format_error("Old password cannot be blank.");
			return;
		}

		if ($new_pw == "") {
			print "ERROR: ".format_error("New password cannot be blank.");
			return;
		}

		if ($new_pw != $con_pw) {
			print "ERROR: ".format_error("Entered passwords do not match.");
			return;
		}

		$authenticator = PluginHost::getInstance()->get_plugin($_SESSION["auth_module"]);

		if (method_exists($authenticator, "change_password")) {
			print format_notice($authenticator->change_password($_SESSION["uid"], $old_pw, $new_pw));
		} else {
			print "ERROR: ".format_error("Function not supported by authentication module.");
		}
	}

	function saveconfig() {
		$boolean_prefs = explode(",", clean($_POST["boolean_prefs"]));

		foreach ($boolean_prefs as $pref) {
			if (!isset($_POST[$pref])) $_POST[$pref] = 'false';
		}

		$need_reload = false;

		foreach (array_keys($_POST) as $pref_name) {

			$value = $_POST[$pref_name];

			switch ($pref_name) {
				case 'DIGEST_PREFERRED_TIME':
					if (get_pref('DIGEST_PREFERRED_TIME') != $value) {

						$sth = $this->pdo->prepare("UPDATE ttrss_users SET
						last_digest_sent = NULL WHERE id = ?");
						$sth->execute([$_SESSION['uid']]);

					}
					break;
				case 'USER_LANGUAGE':
					if (!$need_reload) $need_reload = $_SESSION["language"] != $value;
					break;

				case 'USER_CSS_THEME':
					if (!$need_reload) $need_reload = get_pref($pref_name) != $value;
					break;
			}

			set_pref($pref_name, $value);
		}

		if ($need_reload) {
			print "PREFS_NEED_RELOAD";
		} else {
			print __("The configuration was saved.");
		}
	}

	function changeemail() {

		$email = clean($_POST["email"]);
		$full_name = clean($_POST["full_name"]);
		$active_uid = $_SESSION["uid"];

		$sth = $this->pdo->prepare("UPDATE ttrss_users SET email = ?,
			full_name = ? WHERE id = ?");
		$sth->execute([$email, $full_name, $active_uid]);

		print __("Your personal data has been saved.");

		return;
	}

	function resetconfig() {

		$_SESSION["prefs_op_result"] = "reset-to-defaults";

		$sth = $this->pdo->prepare("DELETE FROM ttrss_user_prefs
			WHERE (profile = :profile OR (:profile IS NULL AND profile IS NULL))
				AND owner_uid = :uid");
		$sth->execute([":profile" => $_SESSION['profile'], ":uid" => $_SESSION['uid']]);

		initialize_user_prefs($_SESSION["uid"], $_SESSION["profile"]);

		echo __("Your preferences are now set to default values.");
	}

	function index() {

		global $access_level_names;

		$prefs_blacklist = array("ALLOW_DUPLICATE_POSTS", "STRIP_UNSAFE_TAGS", "REVERSE_HEADLINES",
			"SORT_HEADLINES_BY_FEED_DATE", "DEFAULT_ARTICLE_LIMIT",
			"FEEDS_SORT_BY_UNREAD", "CDM_EXPANDED");

		/* "FEEDS_SORT_BY_UNREAD", "HIDE_READ_FEEDS", "REVERSE_HEADLINES" */

		$profile_blacklist = array("ALLOW_DUPLICATE_POSTS", "PURGE_OLD_DAYS",
					"PURGE_UNREAD_ARTICLES", "DIGEST_ENABLE", "DIGEST_CATCHUP",
					"BLACKLISTED_TAGS", "ENABLE_API_ACCESS", "UPDATE_POST_ON_CHECKSUM_CHANGE",
					"DEFAULT_UPDATE_INTERVAL", "USER_TIMEZONE", "SORT_HEADLINES_BY_FEED_DATE",
					"SSL_CERT_SERIAL", "DIGEST_PREFERRED_TIME");


		$_SESSION["prefs_op_result"] = "";

		print "<div dojoType=\"dijit.layout.AccordionContainer\" region=\"center\">";
		print "<div dojoType=\"dijit.layout.AccordionPane\" 
			title=\"<i class='material-icons'>person</i> ".__('Personal data / Authentication')."\">";

		print "<form dojoType=\"dijit.form.Form\" id=\"changeUserdataForm\">";

		print "<script type=\"dojo/method\" event=\"onSubmit\" args=\"evt\">
		evt.preventDefault();
		if (this.validate()) {
			Notify.progress('Saving data...', true);

			new Ajax.Request('backend.php', {
				parameters: dojo.objectToQuery(this.getValues()),
				onComplete: function(transport) {
					notify_callback2(transport);
			} });

		}
		</script>";

		print "<table width=\"100%\" class=\"prefPrefsList\">";

		print "<h2>" . __("Personal data") . "</h2>";

		$sth = $this->pdo->prepare("SELECT email,full_name,otp_enabled,
			access_level FROM ttrss_users
			WHERE id = ?");
		$sth->execute([$_SESSION["uid"]]);
		$row = $sth->fetch();

		$email = htmlspecialchars($row["email"]);
		$full_name = htmlspecialchars($row["full_name"]);
		$otp_enabled = sql_bool_to_bool($row["otp_enabled"]);

		print "<tr><td width=\"40%\">".__('Full name')."</td>";
		print "<td class=\"prefValue\"><input dojoType=\"dijit.form.ValidationTextBox\" name=\"full_name\" required=\"1\"
			value=\"$full_name\"></td></tr>";

		print "<tr><td width=\"40%\">".__('E-mail')."</td>";
		print "<td class=\"prefValue\"><input dojoType=\"dijit.form.ValidationTextBox\" name=\"email\" required=\"1\" value=\"$email\"></td></tr>";

		if (!SINGLE_USER_MODE && !$_SESSION["hide_hello"]) {

			$access_level = $row["access_level"];
			print "<tr><td width=\"40%\">".__('Access level')."</td>";
			print "<td>" . $access_level_names[$access_level] . "</td></tr>";
		}

		print "</table>";

		print_hidden("op", "pref-prefs");
		print_hidden("method", "changeemail");

		print "<p><button dojoType=\"dijit.form.Button\" type=\"submit\" class=\"alt-primary\">".
			__("Save data")."</button>";

		print "</form>";

		if ($_SESSION["auth_module"]) {
			$authenticator = PluginHost::getInstance()->get_plugin($_SESSION["auth_module"]);
		} else {
			$authenticator = false;
		}

		if ($authenticator && method_exists($authenticator, "change_password")) {

			print "<h2>" . __("Password") . "</h2>";

			print "<div style='display : none' id='pwd_change_infobox'></div>";

			print "<form dojoType=\"dijit.form.Form\">";

			print "<script type=\"dojo/method\" event=\"onSubmit\" args=\"evt\">
			evt.preventDefault();
			if (this.validate()) {
				Notify.progress('Changing password...', true);

				new Ajax.Request('backend.php', {
					parameters: dojo.objectToQuery(this.getValues()),
					onComplete: function(transport) {
						Notify.close();
						if (transport.responseText.indexOf('ERROR: ') == 0) {

							$('pwd_change_infobox').innerHTML =
								transport.responseText.replace('ERROR: ', '');

						} else {
							$('pwd_change_infobox').innerHTML =
								transport.responseText.replace('ERROR: ', '');

							var warn = $('default_pass_warning');
							if (warn) Element.hide(warn);
						}

						new Effect.Appear('pwd_change_infobox');

				}});
				this.reset();
			}
			</script>";

			if ($otp_enabled) {
				print_notice(__("Changing your current password will disable OTP."));
			}

			print "<table width=\"100%\" class=\"prefPrefsList\">";

			print "<tr><td width=\"40%\">".__("Old password")."</td>";
			print "<td class=\"prefValue\"><input dojoType=\"dijit.form.ValidationTextBox\" type=\"password\" required=\"1\" name=\"old_password\"></td></tr>";

			print "<tr><td width=\"40%\">".__("New password")."</td>";

			print "<td class=\"prefValue\"><input dojoType=\"dijit.form.ValidationTextBox\" type=\"password\" required=\"1\"
				name=\"new_password\"></td></tr>";

			print "<tr><td width=\"40%\">".__("Confirm password")."</td>";

			print "<td class=\"prefValue\"><input dojoType=\"dijit.form.ValidationTextBox\" type=\"password\" required=\"1\" name=\"confirm_password\"></td></tr>";

			print "</table>";

			print_hidden("op", "pref-prefs");
			print_hidden("method", "changepassword");

			print "<p><button dojoType=\"dijit.form.Button\" type=\"submit\" class=\"alt-primary\">".
				__("Change password")."</button>";

			print "</form>";

			if ($_SESSION["auth_module"] == "auth_internal") {

				print "<h2>" . __("One time passwords / Authenticator") . "</h2>";

				if ($otp_enabled) {

					print_notice(__("One time passwords are currently enabled. Enter your current password below to disable."));

					print "<form dojoType=\"dijit.form.Form\">";

				print "<script type=\"dojo/method\" event=\"onSubmit\" args=\"evt\">
				evt.preventDefault();
				if (this.validate()) {
					Notify.progress('Disabling OTP', true);

					new Ajax.Request('backend.php', {
						parameters: dojo.objectToQuery(this.getValues()),
						onComplete: function(transport) {
							Notify.close();
							if (transport.responseText.indexOf('ERROR: ') == 0) {
								Notify.error(transport.responseText.replace('ERROR: ', ''));
							} else {
								window.location.reload();
							}
					}});
					this.reset();
				}
				</script>";

				print "<table width=\"100%\" class=\"prefPrefsList\">";

				print "<tr><td width=\"40%\">".__("Enter your password")."</td>";

				print "<td class=\"prefValue\"><input dojoType=\"dijit.form.ValidationTextBox\" type=\"password\" required=\"1\"
					name=\"password\"></td></tr>";

				print "</table>";

				print_hidden("op", "pref-prefs");
				print_hidden("method", "otpdisable");

				print "<p><button dojoType=\"dijit.form.Button\" type=\"submit\">".
					__("Disable OTP")."</button>";

				print "</form>";

				} else if (function_exists("imagecreatefromstring")) {

					print "<p>" . __("You will need a compatible Authenticator to use this. Changing your password would automatically disable OTP.") . "</p>";

					print "<p>".__("Scan the following code by the Authenticator application:")."</p>";

					$csrf_token = $_SESSION["csrf_token"];

					print "<img src=\"backend.php?op=pref-prefs&method=otpqrcode&csrf_token=$csrf_token\">";

					print "<form dojoType=\"dijit.form.Form\" id=\"changeOtpForm\">";

					print_hidden("op", "pref-prefs");
					print_hidden("method", "otpenable");

					print "<script type=\"dojo/method\" event=\"onSubmit\" args=\"evt\">
					evt.preventDefault();
					if (this.validate()) {
						Notify.progress('Saving data...', true);

						new Ajax.Request('backend.php', {
							parameters: dojo.objectToQuery(this.getValues()),
							onComplete: function(transport) {
								Notify.close();
								if (transport.responseText.indexOf('ERROR:') == 0) {
									Notify.error(transport.responseText.replace('ERROR:', ''));
								} else {
									window.location.reload();
								}
						} });

					}
					</script>";

					print "<table width=\"100%\" class=\"prefPrefsList\">";

					print "<tr><td width=\"40%\">".__("Enter your password")."</td>";

					print "<td class=\"prefValue\"><input dojoType=\"dijit.form.ValidationTextBox\" type=\"password\" required=\"1\"
						name=\"password\"></td></tr>";

					print "<tr><td width=\"40%\">".__("Enter the generated one time password")."</td>";

					print "<td class=\"prefValue\"><input dojoType=\"dijit.form.ValidationTextBox\" autocomplete=\"off\"
						required=\"1\"
						name=\"otp\"></td></tr>";

					print "<tr><td colspan=\"2\">";

					print "</td></tr><tr><td colspan=\"2\">";

					print "</td></tr>";
					print "</table>";

					print "<p><button dojoType=\"dijit.form.Button\" type=\"submit\" class=\"alt-primary\">".
						__("Enable OTP")."</button>";

					print "</form>";

				} else {

					print_notice(__("PHP GD functions are required for OTP support."));

				}

			}
		}

		PluginHost::getInstance()->run_hooks(PluginHost::HOOK_PREFS_TAB_SECTION,
			"hook_prefs_tab_section", "prefPrefsAuth");

		print "</div>"; #pane

		print "<div dojoType=\"dijit.layout.AccordionPane\" selected=\"true\" 
			title=\"<i class='material-icons'>settings</i> ".__('Preferences')."\">";

		print "<form dojoType=\"dijit.form.Form\" id=\"changeSettingsForm\">";

		print "<script type=\"dojo/method\" event=\"onSubmit\" args=\"evt, quit\">
		if (evt) evt.preventDefault();
		if (this.validate()) {
			console.log(dojo.objectToQuery(this.getValues()));

			new Ajax.Request('backend.php', {
				parameters: dojo.objectToQuery(this.getValues()),
				onComplete: function(transport) {
					var msg = transport.responseText;
					if (quit) {
						document.location.href = 'index.php';
					} else {
						if (msg == 'PREFS_NEED_RELOAD') {
							window.location.reload();
						} else {
							Notify.info(msg);
						}
					}
			} });
		}
		</script>";

		print '<div dojoType="dijit.layout.BorderContainer" gutters="false">';

		print '<div dojoType="dijit.layout.ContentPane" region="center" style="overflow-y : auto">';

		$profile = $_SESSION["profile"];

		if ($profile) {
			print_notice(__("Some preferences are only available in default profile."));

			initialize_user_prefs($_SESSION["uid"], $profile);
		} else {
			initialize_user_prefs($_SESSION["uid"]);
		}

		$sth = $this->pdo->prepare("SELECT DISTINCT
			ttrss_user_prefs.pref_name,value,type_name,
			ttrss_prefs_sections.order_id,
			def_value,section_id
			FROM ttrss_prefs,ttrss_prefs_types,ttrss_prefs_sections,ttrss_user_prefs
			WHERE type_id = ttrss_prefs_types.id AND
				(profile = :profile OR (:profile IS NULL AND profile IS NULL)) AND
				section_id = ttrss_prefs_sections.id AND
				ttrss_user_prefs.pref_name = ttrss_prefs.pref_name AND
				owner_uid = :uid
			ORDER BY ttrss_prefs_sections.order_id,pref_name");
		$sth->execute([":uid" => $_SESSION['uid'], ":profile" => $profile]);

		$lnum = 0;

		$active_section = "";

		$listed_boolean_prefs = array();

		while ($line = $sth->fetch()) {

			if (in_array($line["pref_name"], $prefs_blacklist)) {
				continue;
			}

			$type_name = $line["type_name"];
			$pref_name = $line["pref_name"];
			$section_name = $this->getSectionName($line["section_id"]);
			$value = $line["value"];

			$short_desc = $this->getShortDesc($pref_name);
			$help_text = $this->getHelpText($pref_name);

			if (!$short_desc) continue;

			if ($profile && in_array($line["pref_name"], $profile_blacklist)) {
				continue;
			}

			if ($active_section != $line["section_id"]) {

				if ($active_section != "") {
					print "</table>";
				}

				print "<table width=\"100%\" class=\"prefPrefsList\">";

				$active_section = $line["section_id"];

				print "<tr><td colspan=\"3\"><h2>".$section_name."</h2></td></tr>";

				$lnum = 0;
			}

			print "<tr>";

			print "<td width=\"40%\" class=\"prefName\" id=\"$pref_name\">";
			print "<label for='CB_$pref_name'>";
			print $short_desc;
			print "</label>";

			if ($help_text) print "<div class=\"prefHelp\">".__($help_text)."</div>";

			print "</td>";

			print "<td class=\"prefValue\">";

			if ($pref_name == "USER_LANGUAGE") {
				print_select_hash($pref_name, $value, get_translations(),
					"style='width : 220px; margin : 0px' dojoType='dijit.form.Select'");

			} else if ($pref_name == "USER_TIMEZONE") {

				$timezones = explode("\n", file_get_contents("lib/timezones.txt"));

				print_select($pref_name, $value, $timezones, 'dojoType="dijit.form.FilteringSelect"');
			} else if ($pref_name == "USER_STYLESHEET") {

				print "<button dojoType=\"dijit.form.Button\" class='alt-info'
					onclick=\"Helpers.customizeCSS()\">" . __('Customize') . "</button>";

			} else if ($pref_name == "USER_CSS_THEME") {

				$themes = array_merge(glob("themes/*.php"), glob("themes/*.css"), glob("themes.local/*.css"));
				$themes = array_map("basename", $themes);
				$themes = array_filter($themes, "theme_exists");
				asort($themes);

				if (!theme_exists($value)) $value = "default.php";

				print "<select name='$pref_name' id='$pref_name' dojoType='dijit.form.Select'>";

				$issel = $value == "default.php" ? "selected='selected'" : "";
				print "<option $issel value='default.php'>".__("default")."</option>";

				foreach ($themes as $theme) {
					$issel = $value == $theme ? "selected='selected'" : "";
					print "<option $issel value='$theme'>$theme</option>";
				}

				print "</select>";


			} else if ($pref_name == "DEFAULT_UPDATE_INTERVAL") {

				global $update_intervals_nodefault;

				print_select_hash($pref_name, $value, $update_intervals_nodefault,
					'dojoType="dijit.form.Select"');

			} else if ($type_name == "bool") {

				array_push($listed_boolean_prefs, $pref_name);

				$checked = ($value == "true") ? "checked=\"checked\"" : "";

				if ($pref_name == "PURGE_UNREAD_ARTICLES" && FORCE_ARTICLE_PURGE != 0) {
					$disabled = "disabled=\"1\"";
					$checked = "checked=\"checked\"";
				} else {
					$disabled = "";
				}

				print "<input type='checkbox' name='$pref_name' $checked $disabled
					dojoType='dijit.form.CheckBox' id='CB_$pref_name' value='1'>";

			} else if (array_search($pref_name, array('FRESH_ARTICLE_MAX_AGE',
					'PURGE_OLD_DAYS', 'LONG_DATE_FORMAT', 'SHORT_DATE_FORMAT')) !== false) {

				$regexp = ($type_name == 'integer') ? 'regexp="^\d*$"' : '';

				if ($pref_name == "PURGE_OLD_DAYS" && FORCE_ARTICLE_PURGE != 0) {
					$disabled = "disabled=\"1\"";
					$value = FORCE_ARTICLE_PURGE;
				} else {
					$disabled = "";
				}

				print "<input dojoType=\"dijit.form.ValidationTextBox\"
					required=\"1\" $regexp $disabled
					name=\"$pref_name\" value=\"$value\">";

			} else if ($pref_name == "SSL_CERT_SERIAL") {

				print "<input dojoType=\"dijit.form.ValidationTextBox\"
					id=\"SSL_CERT_SERIAL\" readonly=\"1\"
					name=\"$pref_name\" value=\"$value\">";

				$cert_serial = htmlspecialchars(get_ssl_certificate_id());
				$has_serial = ($cert_serial) ? "false" : "true";

				print "<br/>";

				print " <button dojoType=\"dijit.form.Button\" disabled=\"$has_serial\"
					onclick=\"dijit.byId('SSL_CERT_SERIAL').attr('value', '$cert_serial')\">" .
					__('Register') . "</button>";

				print " <button dojoType=\"dijit.form.Button\"
					onclick=\"dijit.byId('SSL_CERT_SERIAL').attr('value', '')\">" .
					__('Clear') . "</button>";

			} else if ($pref_name == 'DIGEST_PREFERRED_TIME') {
				print "<input dojoType=\"dijit.form.ValidationTextBox\"
					id=\"$pref_name\" regexp=\"[012]?\d:\d\d\" placeHolder=\"12:00\"
					name=\"$pref_name\" value=\"$value\"><div class=\"insensitive\">".
					T_sprintf("Current server time: %s (UTC)", date("H:i")) . "</div>";
			} else {
				$regexp = ($type_name == 'integer') ? 'regexp="^\d*$"' : '';

				print "<input dojoType=\"dijit.form.ValidationTextBox\"
					$regexp
					name=\"$pref_name\" value=\"$value\">";
			}

			print "</td>";

			print "</tr>";

			$lnum++;
		}

		print "</table>";

		$listed_boolean_prefs = htmlspecialchars(join(",", $listed_boolean_prefs));

		print_hidden("boolean_prefs", "$listed_boolean_prefs");

		PluginHost::getInstance()->run_hooks(PluginHost::HOOK_PREFS_TAB_SECTION,
			"hook_prefs_tab_section", "prefPrefsPrefsInside");

		print '</div>'; # inside pane
		print '<div dojoType="dijit.layout.ContentPane" region="bottom">';

		print_hidden("op", "pref-prefs");
		print_hidden("method", "saveconfig");

		print "<div dojoType=\"dijit.form.ComboButton\" type=\"submit\" class=\"alt-primary\">
			<span>".__('Save configuration')."</span>
			<div dojoType=\"dijit.DropDownMenu\">
				<div dojoType=\"dijit.MenuItem\"
					onclick=\"dijit.byId('changeSettingsForm').onSubmit(null, true)\">".
				__("Save and exit preferences")."</div>
			</div>
			</div>";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return Helpers.editProfiles()\">".
			__('Manage profiles')."</button> ";

		print "<button dojoType=\"dijit.form.Button\" class=\"alt-danger\" onclick=\"return Helpers.confirmReset()\">".
			__('Reset to defaults')."</button>";

		print "&nbsp;";

		PluginHost::getInstance()->run_hooks(PluginHost::HOOK_PREFS_TAB_SECTION,
			"hook_prefs_tab_section", "prefPrefsPrefsOutside");

		print "</form>";
		print '</div>'; # inner pane
		print '</div>'; # border container

		print "</div>"; #pane

		print "<div dojoType=\"dijit.layout.AccordionPane\" 
			title=\"<i class='material-icons'>extension</i> ".__('Plugins')."\">";

		print "<form dojoType=\"dijit.form.Form\" id=\"changePluginsForm\">";

		print "<script type=\"dojo/method\" event=\"onSubmit\" args=\"evt\">
		evt.preventDefault();
		if (this.validate()) {
			Notify.progress('Saving data...', true);

			new Ajax.Request('backend.php', {
				parameters: dojo.objectToQuery(this.getValues()),
				onComplete: function(transport) {
					Notify.close();
					if (confirm(__('Selected plugins have been enabled. Reload?'))) {
						window.location.reload();
					}
			} });

		}
		</script>";

		print_hidden("op", "pref-prefs");
		print_hidden("method", "setplugins");

		print '<div dojoType="dijit.layout.BorderContainer" gutters="false">';
		print '<div dojoType="dijit.layout.ContentPane" region="center" style="overflow-y : auto">';

		if (ini_get("open_basedir") && function_exists("curl_init") && !defined("NO_CURL")) {
			print_warning("Your PHP configuration has open_basedir restrictions enabled. Some plugins relying on CURL for functionality may not work correctly.");
		}

		print "<table width='100%' class='prefPluginsList'>";

		print "<tr><td colspan='5'><h2>".__("System plugins")."</h2>".
            format_notice(__("System plugins are enabled in <strong>config.php</strong> for all users.")).
            "</td></tr>";

		print "<tr class=\"title\">
				<td width=\"5%\">&nbsp;</td>
				<td width='10%'>".__('Plugin')."</td>
				<td width=''>".__('Description')."</td>
				<td width='5%'>".__('Version')."</td>
				<td width='10%'>".__('Author')."</td></tr>";

		$system_enabled = array_map("trim", explode(",", PLUGINS));
		$user_enabled = array_map("trim", explode(",", get_pref("_ENABLED_PLUGINS")));

		$tmppluginhost = new PluginHost();
		$tmppluginhost->load_all($tmppluginhost::KIND_ALL, $_SESSION["uid"], true);
		$tmppluginhost->load_data(true);

		foreach ($tmppluginhost->get_plugins() as $name => $plugin) {
			$about = $plugin->about();

			if ($about[3]) {
				if (in_array($name, $system_enabled)) {
					$checked = "checked='1'";
				} else {
					$checked = "";
				}

				print "<tr>";

				print "<td align='center'><input disabled='1'
						dojoType=\"dijit.form.CheckBox\" $checked
						type=\"checkbox\"></td>";

				$icon_class = $checked ? "plugin-enabled" : "plugin-disabled";

				print "<td><label><i class='material-icons $icon_class'>extension</i> $name</label></td>";
				print "<td>" . htmlspecialchars($about[1]);
				if (@$about[4]) {
					print " &mdash; <a target=\"_blank\" rel=\"noopener noreferrer\" class=\"visibleLink\"
						href=\"".htmlspecialchars($about[4])."\">".__("more info")."</a>";
				}
				print "</td>";
				print "<td>" . htmlspecialchars(sprintf("%.2f", $about[0])) . "</td>";
				print "<td>" . htmlspecialchars($about[2]) . "</td>";

				if (count($tmppluginhost->get_all($plugin)) > 0) {
					if (in_array($name, $system_enabled)) {
						print "<td><a href='#' onclick=\"Helpers.clearPluginData('$name')\"
							class='visibleLink'>".__("Clear data")."</a></td>";
					}
				}

				print "</tr>";

			}
		}

		print "<tr><td colspan='4'><h2>".__("User plugins")."</h2></td></tr>";

		print "<tr class=\"title\">
				<td width=\"5%\">&nbsp;</td>
				<td width='10%'>".__('Plugin')."</td>
				<td width=''>".__('Description')."</td>
				<td width='5%'>".__('Version')."</td>
				<td width='10%'>".__('Author')."</td></tr>";


		foreach ($tmppluginhost->get_plugins() as $name => $plugin) {
			$about = $plugin->about();

			if (!$about[3]) {

				if (in_array($name, $system_enabled)) {
					$checked = "checked='1'";
					$disabled = "disabled='1'";
					$rowclass = '';
				} else if (in_array($name, $user_enabled)) {
					$checked = "checked='1'";
					$disabled = "";
					$rowclass = "Selected";
				} else {
					$checked = "";
					$disabled = "";
					$rowclass = '';
				}

				print "<tr class='$rowclass'>";

				$icon_class = $checked ? "plugin-enabled" : "plugin-disabled";

				print "<td align='center'><input id='FPCHK-$name' name='plugins[]' value='$name' onclick='Tables.onRowChecked(this);'
					dojoType=\"dijit.form.CheckBox\" $checked $disabled
					type=\"checkbox\"></td>";

				print "<td><label for='FPCHK-$name'><i class='material-icons $icon_class'>extension</i> $name</label></td>";
				print "<td><label for='FPCHK-$name'>" . htmlspecialchars($about[1]) . "</label>";
				if (@$about[4]) {
					print " &mdash; <a target=\"_blank\" rel=\"noopener noreferrer\" class=\"visibleLink\"
						href=\"".htmlspecialchars($about[4])."\">".__("more info")."</a>";
				}
				print "</td>";

				print "<td>" . htmlspecialchars(sprintf("%.2f", $about[0])) . "</td>";
				print "<td>" . htmlspecialchars($about[2]) . "</td>";

				if (count($tmppluginhost->get_all($plugin)) > 0) {
					if (in_array($name, $system_enabled) || in_array($name, $user_enabled)) {
						print "<td><a href='#' onclick=\"Helpers.clearPluginData('$name')\" class='visibleLink'>".__("Clear data")."</a></td>";
					}
				}

				print "</tr>";



			}

		}

		print "</table>";

		//print "<p>" . __("You will need to reload Tiny Tiny RSS for plugin changes to take effect.") . "</p>";

		print "</div>"; #content-pane
		print '<div dojoType="dijit.layout.ContentPane" region="bottom">';
		print "<button dojoType=\"dijit.form.Button\" type=\"submit\">".
			__("Enable selected plugins")."</button>";
		print "</div>"; #pane

		print "</div>"; #pane
		print "</div>"; #border-container

		print "</form>";


		PluginHost::getInstance()->run_hooks(PluginHost::HOOK_PREFS_TAB,
			"hook_prefs_tab", "prefPrefs");

		print "</div>"; #container

	}

	function toggleAdvanced() {
		$_SESSION["prefs_show_advanced"] = !$_SESSION["prefs_show_advanced"];
	}

	function otpqrcode() {
		require_once "lib/phpqrcode/phpqrcode.php";

		$sth = $this->pdo->prepare("SELECT login,salt,otp_enabled
			FROM ttrss_users
			WHERE id = ?");
		$sth->execute([$_SESSION['uid']]);

		if ($row = $sth->fetch()) {

			$base32 = new \OTPHP\Base32();

			$login = $row["login"];
			$otp_enabled = sql_bool_to_bool($row["otp_enabled"]);

			if (!$otp_enabled) {
				$secret = $base32->encode(sha1($row["salt"]));

				QRcode::png("otpauth://totp/".urlencode($login).
					"?secret=$secret&issuer=".urlencode("Tiny Tiny RSS"));

			}
		}
	}

	function otpenable() {

		$password = clean($_REQUEST["password"]);
		$otp = clean($_REQUEST["otp"]);

		$authenticator = PluginHost::getInstance()->get_plugin($_SESSION["auth_module"]);

		if ($authenticator->check_password($_SESSION["uid"], $password)) {

			$sth = $this->pdo->prepare("SELECT salt
				FROM ttrss_users
				WHERE id = ?");
			$sth->execute([$_SESSION['uid']]);

			if ($row = $sth->fetch()) {

				$base32 = new \OTPHP\Base32();

				$secret = $base32->encode(sha1($row["salt"]));
				$topt = new \OTPHP\TOTP($secret);

				$otp_check = $topt->now();

				if ($otp == $otp_check) {
					$sth = $this->pdo->prepare("UPDATE ttrss_users
					SET otp_enabled = true WHERE id = ?");

					$sth->execute([$_SESSION['uid']]);

					print "OK";
				} else {
					print "ERROR:".__("Incorrect one time password");
				}
			}

		} else {
			print "ERROR:".__("Incorrect password");
		}

	}

	static function isdefaultpassword() {
		$authenticator = PluginHost::getInstance()->get_plugin($_SESSION["auth_module"]);

		if ($authenticator &&
                method_exists($authenticator, "check_password") &&
                $authenticator->check_password($_SESSION["uid"], "password")) {

			return true;
		}

		return false;
	}

	function otpdisable() {
		$password = clean($_REQUEST["password"]);

		$authenticator = PluginHost::getInstance()->get_plugin($_SESSION["auth_module"]);

		if ($authenticator->check_password($_SESSION["uid"], $password)) {

			$sth = $this->pdo->prepare("UPDATE ttrss_users SET otp_enabled = false WHERE
				id = ?");
			$sth->execute([$_SESSION['uid']]);

			print "OK";
		} else {
			print "ERROR: ".__("Incorrect password");
		}

	}

	function setplugins() {
		if (is_array(clean($_REQUEST["plugins"])))
			$plugins = join(",", clean($_REQUEST["plugins"]));
		else
			$plugins = "";

		set_pref("_ENABLED_PLUGINS", $plugins);
	}

	function clearplugindata() {
		$name = clean($_REQUEST["name"]);

		PluginHost::getInstance()->clear_data(PluginHost::getInstance()->get_plugin($name));
	}

	function customizeCSS() {
		$value = get_pref("USER_STYLESHEET");
		$value = str_replace("<br/>", "\n", $value);

		print_notice(__("You can override colors, fonts and layout of your currently selected theme with custom CSS declarations here."));

		print_hidden("op", "rpc");
		print_hidden("method", "setpref");
		print_hidden("key", "USER_STYLESHEET");

		print "<textarea class='panel user-css-editor' dojoType='dijit.form.SimpleTextarea'
			style='font-size : 12px;'
			name='value'>$value</textarea>";

		print "<div class='dlgButtons'>";
		print "<button dojoType=\"dijit.form.Button\"
			onclick=\"dijit.byId('cssEditDlg').execute()\">".__('Save')."</button> ";
		print "<button dojoType=\"dijit.form.Button\"
			onclick=\"dijit.byId('cssEditDlg').hide()\">".__('Cancel')."</button>";
		print "</div>";

	}

	function editPrefProfiles() {
		print "<div dojoType=\"dijit.Toolbar\">";

		print "<div dojoType=\"dijit.form.DropDownButton\">".
				"<span>" . __('Select')."</span>";
		print "<div dojoType=\"dijit.Menu\" style=\"display: none;\">";
		print "<div onclick=\"Tables.select('pref-profiles-list', true)\"
			dojoType=\"dijit.MenuItem\">".__('All')."</div>";
		print "<div onclick=\"Tables.select('pref-profiles-list', false)\"
			dojoType=\"dijit.MenuItem\">".__('None')."</div>";
		print "</div></div>";

		print "<div style=\"float : right\">";

		print "<input name=\"newprofile\" dojoType=\"dijit.form.ValidationTextBox\"
				required=\"1\">
			<button dojoType=\"dijit.form.Button\"
			onclick=\"dijit.byId('profileEditDlg').addProfile()\">".
				__('Create profile')."</button></div>";

		print "</div>";

		$sth = $this->pdo->prepare("SELECT title,id FROM ttrss_settings_profiles
			WHERE owner_uid = ? ORDER BY title");
		$sth->execute([$_SESSION['uid']]);

		print "<div class='panel panel-scrollable'>";

		print "<form id='profile_edit_form' onsubmit='return false'>";

		print "<table width='100%' id='pref-profiles-list'>";

		print "<tr>"; # data-row-id='0' <-- no point, shouldn't be removed

		print "<td><input onclick='Tables.onRowChecked(this);' dojoType='dijit.form.CheckBox' type='checkbox'></td>";

		if (!$_SESSION["profile"]) {
			$is_active = __("(active)");
		} else {
			$is_active = "";
		}

		print "<td width='100%'><span>" . __("Default profile") . " $is_active</span></td>";

		print "</tr>";

		$lnum = 1;

		while ($line = $sth->fetch()) {

			$profile_id = $line["id"];

			print "<tr data-row-id='$profile_id'>";

			$edit_title = htmlspecialchars($line["title"]);

			print "<td><input onclick='Tables.onRowChecked(this);' dojoType='dijit.form.CheckBox' type='checkbox'></td>";

			if ($_SESSION["profile"] == $line["id"]) {
				$is_active = __("(active)");
			} else {
				$is_active = "";
			}

			print "<td><span dojoType=\"dijit.InlineEditBox\"
				width=\"300px\" autoSave=\"false\"
				profile-id=\"$profile_id\">" . $edit_title .
				"<script type=\"dojo/method\" event=\"onChange\" args=\"item\">
					var elem = this;
					dojo.xhrPost({
						url: 'backend.php',
						content: {op: 'rpc', method: 'saveprofile',
							value: this.value,
							id: this.srcNodeRef.getAttribute('profile-id')},
							load: function(response) {
								elem.attr('value', response);
						}
					});
				</script>
			</span> $is_active</td>";

			print "</tr>";

			++$lnum;
		}

		print "</table>";
		print "</form>";
		print "</div>";

		print "<div class='dlgButtons'>
			<div style='float : left'>
			<button class=\"alt-danger\" dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('profileEditDlg').removeSelected()\">".
			__('Remove selected profiles')."</button>
			<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('profileEditDlg').activateProfile()\">".
			__('Activate profile')."</button>
			</div>";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('profileEditDlg').hide()\">".
			__('Close this window')."</button>";
		print "</div>";

	}

	private function getShortDesc($pref_name) {
		if (isset($this->pref_help[$pref_name])) {
			return $this->pref_help[$pref_name][0];
		}
		return "";
	}

	private function getHelpText($pref_name) {
		if (isset($this->pref_help[$pref_name])) {
			return $this->pref_help[$pref_name][1];
		}
		return "";
	}

	private function getSectionName($id) {
		if (isset($this->pref_sections[$id])) {
			return $this->pref_sections[$id];
		}

		return "";
	}
}
