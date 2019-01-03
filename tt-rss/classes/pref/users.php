<?php
class Pref_Users extends Handler_Protected {
		function before($method) {
			if (parent::before($method)) {
				if ($_SESSION["access_level"] < 10) {
					print __("Your access level is insufficient to open this tab.");
					return false;
				}
				return true;
			}
			return false;
		}

		function csrf_ignore($method) {
			$csrf_ignored = array("index", "edit", "userdetails");

			return array_search($method, $csrf_ignored) !== false;
		}

		function edit() {
			global $access_level_names;

			print "<form id=\"user_edit_form\" onsubmit='return false' dojoType=\"dijit.form.Form\">";

			print '<div dojoType="dijit.layout.TabContainer" style="height : 400px">
        		<div dojoType="dijit.layout.ContentPane" title="'.__('Edit user').'">';

			//print "<form id=\"user_edit_form\" onsubmit='return false' dojoType=\"dijit.form.Form\">";

			$id = (int) clean($_REQUEST["id"]);

			print_hidden("id", "$id");
			print_hidden("op", "pref-users");
			print_hidden("method", "editSave");

			$sth = $this->pdo->prepare("SELECT * FROM ttrss_users WHERE id = ?");
			$sth->execute([$id]);

			if ($row = $sth->fetch()) {

				$login = $row["login"];
				$access_level = $row["access_level"];
				$email = $row["email"];

				$sel_disabled = ($id == $_SESSION["uid"] || $login == "admin") ? "disabled" : "";

				print "<div class=\"dlgSec\">".__("User")."</div>";
				print "<div class=\"dlgSecCont\">";

				if ($sel_disabled) {
					print_hidden("login", "$login");
				}

				print "<input size=\"30\" style=\"font-size : 16px\"
					dojoType=\"dijit.form.ValidationTextBox\" required=\"1\"
					$sel_disabled
					name=\"login\" value=\"$login\">";

				print "</div>";

				print "<div class=\"dlgSec\">".__("Authentication")."</div>";
				print "<div class=\"dlgSecCont\">";

				print __('Access level: ') . " ";

				if (!$sel_disabled) {
					print_select_hash("access_level", $access_level, $access_level_names,
						"dojoType=\"dijit.form.Select\" $sel_disabled");
				} else {
					print_select_hash("", $access_level, $access_level_names,
						"dojoType=\"dijit.form.Select\" $sel_disabled");
					print_hidden("access_level", "$access_level");
				}

				print "<hr/>";

				print "<input dojoType=\"dijit.form.TextBox\" type=\"password\" size=\"20\" placeholder=\"Change password\"
				name=\"password\">";

				print "</div>";

				print "<div class=\"dlgSec\">".__("Options")."</div>";
				print "<div class=\"dlgSecCont\">";

				print "<input dojoType=\"dijit.form.TextBox\" size=\"30\" name=\"email\" placeholder=\"E-mail\"
				value=\"$email\">";

				print "</div>";

				print "</table>";

			}

			print '</div>'; #tab
			print "<div href=\"backend.php?op=pref-users&method=userdetails&id=$id\"
				dojoType=\"dijit.layout.ContentPane\" title=\"".__('User details')."\">";

			print '</div>';
			print '</div>';

			print "<div class=\"dlgButtons\">
				<button dojoType=\"dijit.form.Button\" class=\"alt-primary\" type=\"submit\" onclick=\"dijit.byId('userEditDlg').execute()\">".
				__('Save')."</button>
				<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('userEditDlg').hide()\">".
				__('Cancel')."</button></div>";

			print "</form>";

			return;
		}

		function userdetails() {
			$id = (int) clean($_REQUEST["id"]);

			$sth = $this->pdo->prepare("SELECT login,
				".SUBSTRING_FOR_DATE."(last_login,1,16) AS last_login,
				access_level,
				(SELECT COUNT(int_id) FROM ttrss_user_entries
					WHERE owner_uid = id) AS stored_articles,
				".SUBSTRING_FOR_DATE."(created,1,16) AS created
				FROM ttrss_users
				WHERE id = ?");
			$sth->execute([$id]);

			if ($row = $sth->fetch()) {
				print "<table width='100%'>";

				$last_login = make_local_datetime(
					$row["last_login"], true);

				$created = make_local_datetime(
					$row["created"], true);

				$stored_articles = $row["stored_articles"];

				print "<tr><td>".__('Registered')."</td><td>$created</td></tr>";
				print "<tr><td>".__('Last logged in')."</td><td>$last_login</td></tr>";

				$sth = $this->pdo->prepare("SELECT COUNT(id) as num_feeds FROM ttrss_feeds
					WHERE owner_uid = ?");
				$sth->execute([$id]);
				$row = $sth->fetch();
				$num_feeds = $row["num_feeds"];

				print "<tr><td>".__('Subscribed feeds count')."</td><td>$num_feeds</td></tr>";
				print "<tr><td>".__('Stored articles')."</td><td>$stored_articles</td></tr>";

				print "</table>";

				print "<h1>".__('Subscribed feeds')."</h1>";

				$sth = $this->pdo->prepare("SELECT id,title,site_url FROM ttrss_feeds
					WHERE owner_uid = ? ORDER BY title");
				$sth->execute([$id]);

				print "<ul class=\"panel panel-scrollable list list-unstyled\">";

				while ($line = $sth->fetch()) {

					$icon_file = ICONS_URL."/".$line["id"].".ico";

					if (file_exists($icon_file) && filesize($icon_file) > 0) {
						$feed_icon = "<img class=\"icon\" src=\"$icon_file\">";
					} else {
						$feed_icon = "<img class=\"icon\" src=\"images/blank_icon.gif\">";
					}

					print "<li>$feed_icon&nbsp;<a href=\"".$line["site_url"]."\">".$line["title"]."</a></li>";

				}

				print "</ul>";


			} else {
				print "<h1>".__('User not found')."</h1>";
			}

		}

		function editSave() {
			$login = trim(clean($_REQUEST["login"]));
			$uid = clean($_REQUEST["id"]);
			$access_level = (int) clean($_REQUEST["access_level"]);
			$email = trim(clean($_REQUEST["email"]));
			$password = clean($_REQUEST["password"]);

			if ($password) {
				$salt = substr(bin2hex(get_random_bytes(125)), 0, 250);
				$pwd_hash = encrypt_password($password, $salt, true);
				$pass_query_part = "pwd_hash = ".$this->pdo->quote($pwd_hash).",
					salt = ".$this->pdo->quote($salt).",";
			} else {
				$pass_query_part = "";
			}

			$sth = $this->pdo->prepare("UPDATE ttrss_users SET $pass_query_part login = ?,
				access_level = ?, email = ?, otp_enabled = false WHERE id = ?");
			$sth->execute([$login, $access_level, $email, $uid]);

		}

		function remove() {
			$ids = explode(",", clean($_REQUEST["ids"]));

			foreach ($ids as $id) {
				if ($id != $_SESSION["uid"] && $id != 1) {
					$sth = $this->pdo->prepare("DELETE FROM ttrss_tags WHERE owner_uid = ?");
					$sth->execute([$id]);

					$sth = $this->pdo->prepare("DELETE FROM ttrss_feeds WHERE owner_uid = ?");
					$sth->execute([$id]);

					$sth = $this->pdo->prepare("DELETE FROM ttrss_users WHERE id = ?");
					$sth->execute([$id]);
				}
			}
		}

		function add() {
			$login = trim(clean($_REQUEST["login"]));
			$tmp_user_pwd = make_password(8);
			$salt = substr(bin2hex(get_random_bytes(125)), 0, 250);
			$pwd_hash = encrypt_password($tmp_user_pwd, $salt, true);

			if (!$login) return; // no blank usernames

			$sth = $this->pdo->prepare("SELECT id FROM ttrss_users WHERE
				login = ?");
			$sth->execute([$login]);

			if (!$sth->fetch()) {

				$sth = $this->pdo->prepare("INSERT INTO ttrss_users
					(login,pwd_hash,access_level,last_login,created, salt)
					VALUES (?, ?, 0, null, NOW(), ?)");
				$sth->execute([$login, $pwd_hash, $salt]);

				$sth = $this->pdo->prepare("SELECT id FROM ttrss_users WHERE
					login = ? AND pwd_hash = ?");
				$sth->execute([$login, $pwd_hash]);

				if ($row = $sth->fetch()) {

					$new_uid = $row['id'];

					print T_sprintf("Added user %s with password %s",
						$login, $tmp_user_pwd);

					initialize_user($new_uid);

				} else {

					print T_sprintf("Could not create user %s", $login);

				}
			} else {
				print T_sprintf("User %s already exists.", $login);
			}
		}

		static function resetUserPassword($uid, $show_password) {

			$pdo = Db::pdo();

			$sth = $pdo->prepare("SELECT login, email
				FROM ttrss_users WHERE id = ?");
			$sth->execute([$uid]);

			if ($row = $sth->fetch()) {

				$login = $row["login"];
				$email = $row["email"];

				$new_salt = substr(bin2hex(get_random_bytes(125)), 0, 250);
				$tmp_user_pwd = make_password(8);

				$pwd_hash = encrypt_password($tmp_user_pwd, $new_salt, true);

				$sth = $pdo->prepare("UPDATE ttrss_users
					  SET pwd_hash = ?, salt = ?, otp_enabled = false
					WHERE id = ?");
				$sth->execute([$pwd_hash, $new_salt, $uid]);

				if ($show_password) {
					print T_sprintf("Changed password of user %s to %s", $login, $tmp_user_pwd);
				} else {
					print_notice(T_sprintf("Sending new password of user %s to %s", $login, $email));
				}

				if ($email) {
					require_once "lib/MiniTemplator.class.php";

					$tpl = new MiniTemplator;

					$tpl->readTemplateFromFile("templates/resetpass_template.txt");

					$tpl->setVariable('LOGIN', $login);
					$tpl->setVariable('NEWPASS', $tmp_user_pwd);

					$tpl->addBlock('message');

					$message = "";

					$tpl->generateOutputToString($message);

					$mailer = new Mailer();

					$rc = $mailer->mail(["to_name" => $login,
						"to_address" => $email,
						"subject" => __("[tt-rss] Password change notification"),
						"message" => $message]);

					if (!$rc) print_error($mailer->error());
				}

			}
		}

		function resetPass() {
			$uid = clean($_REQUEST["id"]);
			Pref_Users::resetUserPassword($uid, true);
		}

		function index() {

			global $access_level_names;

			print "<div dojoType='dijit.layout.BorderContainer' gutters='false'>";
			print "<div style='padding : 0px' dojoType='dijit.layout.ContentPane' region='top'>";
			print "<div dojoType='dijit.Toolbar'>";

			$user_search = trim(clean($_REQUEST["search"]));

			if (array_key_exists("search", $_REQUEST)) {
				$_SESSION["prefs_user_search"] = $user_search;
			} else {
				$user_search = $_SESSION["prefs_user_search"];
			}

			print "<div style='float : right; padding-right : 4px;'>
				<input dojoType=\"dijit.form.TextBox\" id=\"user_search\" size=\"20\" type=\"search\"
					value=\"$user_search\">
				<button dojoType=\"dijit.form.Button\" oncl1ick=\"Users.reload()\">".
					__('Search')."</button>
				</div>";

			$sort = clean($_REQUEST["sort"]);

			if (!$sort || $sort == "undefined") {
				$sort = "login";
			}

			print "<div dojoType=\"dijit.form.DropDownButton\">".
					"<span>" . __('Select')."</span>";
			print "<div dojoType=\"dijit.Menu\" style=\"display: none;\">";
			print "<div onclick=\"Tables.select('prefUserList', true)\"
				dojoType=\"dijit.MenuItem\">".__('All')."</div>";
			print "<div onclick=\"Tables.select('prefUserList', false)\"
				dojoType=\"dijit.MenuItem\">".__('None')."</div>";
			print "</div></div>";

			print "<button dojoType=\"dijit.form.Button\" onclick=\"Users.add()\">".__('Create user')."</button>";

			print "
				<button dojoType=\"dijit.form.Button\" onclick=\"Users.editSelected()\">".
				__('Edit')."</button dojoType=\"dijit.form.Button\">
				<button dojoType=\"dijit.form.Button\" onclick=\"Users.removeSelected()\">".
				__('Remove')."</button dojoType=\"dijit.form.Button\">
				<button dojoType=\"dijit.form.Button\" onclick=\"Users.resetSelected()\">".
				__('Reset password')."</button dojoType=\"dijit.form.Button\">";

			PluginHost::getInstance()->run_hooks(PluginHost::HOOK_PREFS_TAB_SECTION,
				"hook_prefs_tab_section", "prefUsersToolbar");

			print "</div>"; #toolbar
			print "</div>"; #pane
			print "<div style='padding : 0px' dojoType='dijit.layout.ContentPane' region='center'>";

			$sort = validate_field($sort,
				["login", "access_level", "created", "num_feeds", "created", "last_login"], "login");

			if ($sort != "login") $sort = "$sort DESC";

			$sth = $this->pdo->prepare("SELECT
					tu.id,
					login,access_level,email,
					".SUBSTRING_FOR_DATE."(last_login,1,16) as last_login,
					".SUBSTRING_FOR_DATE."(created,1,16) as created,
					(SELECT COUNT(id) FROM ttrss_feeds WHERE owner_uid = tu.id) AS num_feeds
				FROM
					ttrss_users tu
				WHERE
					(:search = '' OR login LIKE :search) AND tu.id > 0
				ORDER BY $sort");
			$sth->execute([":search" => $user_search ? "%$user_search%" : ""]);

			print "<p><table width=\"100%\" cellspacing=\"0\"
				class=\"prefUserList\" id=\"prefUserList\">";

			print "<tr class=\"title\">
						<td align='center' width=\"5%\">&nbsp;</td>
						<td width='20%'><a href=\"#\" onclick=\"Users.reload('login')\">".__('Login')."</a></td>
						<td width='20%'><a href=\"#\" onclick=\"Users.reload('access_level')\">".__('Access Level')."</a></td>
						<td width='10%'><a href=\"#\" onclick=\"Users.reload('num_feeds')\">".__('Subscribed feeds')."</a></td>
						<td width='20%'><a href=\"#\" onclick=\"Users.reload('created')\">".__('Registered')."</a></td>
						<td width='20%'><a href=\"#\" onclick=\"Users.reload('last_login')\">".__('Last login')."</a></td></tr>";

			$lnum = 0;

			while ($line = $sth->fetch()) {

				$uid = $line["id"];

				print "<tr data-row-id=\"$uid\" onclick='Users.edit($uid)'>";

				$line["login"] = htmlspecialchars($line["login"]);
				$line["created"] = make_local_datetime($line["created"], false);
				$line["last_login"] = make_local_datetime($line["last_login"], false);

				print "<td align='center'><input onclick='Tables.onRowChecked(this); event.stopPropagation();'
					dojoType=\"dijit.form.CheckBox\" type=\"checkbox\"></td>";

				print "<td title='".__('Click to edit')."'><i class='material-icons'>person</i> " . $line["login"] . "</td>";

				print "<td>" .	$access_level_names[$line["access_level"]] . "</td>";
				print "<td>" . $line["num_feeds"] . "</td>";
				print "<td>" . $line["created"] . "</td>";
				print "<td>" . $line["last_login"] . "</td>";

				print "</tr>";

				++$lnum;
			}

			print "</table>";

			if ($lnum == 0) {
				if (!$user_search) {
					print_warning(__('No users defined.'));
				} else {
					print_warning(__('No matching users found.'));
				}
			}

			print "</div>"; #pane

			PluginHost::getInstance()->run_hooks(PluginHost::HOOK_PREFS_TAB,
				"hook_prefs_tab", "prefUsers");

			print "</div>"; #container

		}
	}
