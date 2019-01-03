<?php
class Pref_Feeds extends Handler_Protected {
	public static $feed_languages = array("English", "Danish", "Dutch", "Finnish", "French", "German", "Hungarian", "Italian", "Norwegian",
		"Portuguese", "Russian", "Spanish", "Swedish", "Turkish", "Simple");

	function csrf_ignore($method) {
		$csrf_ignored = array("index", "getfeedtree", "add", "editcats", "editfeed",
			"savefeedorder", "uploadicon", "feedswitherrors", "inactivefeeds",
			"batchsubscribe");

		return array_search($method, $csrf_ignored) !== false;
	}

	function batch_edit_cbox($elem, $label = false) {
		print "<input type=\"checkbox\" title=\"".__("Check to enable field")."\"
			onchange=\"dijit.byId('feedEditDlg').toggleField(this, '$elem', '$label')\">";
	}

	function renamecat() {
		$title = clean($_REQUEST['title']);
		$id = clean($_REQUEST['id']);

		if ($title) {
			$sth = $this->pdo->prepare("UPDATE ttrss_feed_categories SET
				title = ? WHERE id = ? AND owner_uid = ?");
			$sth->execute([$title, $id, $_SESSION['uid']]);
		}
	}

	private function get_category_items($cat_id) {

		if (clean($_REQUEST['mode']) != 2)
			$search = $_SESSION["prefs_feed_search"];
		else
			$search = "";

		// first one is set by API
		$show_empty_cats = clean($_REQUEST['force_show_empty']) ||
			(clean($_REQUEST['mode']) != 2 && !$search);

		$items = array();

		$sth = $this->pdo->prepare("SELECT id, title FROM ttrss_feed_categories
				WHERE owner_uid = ? AND parent_cat = ? ORDER BY order_id, title");
		$sth->execute([$_SESSION['uid'], $cat_id]);

		while ($line = $sth->fetch()) {

			$cat = array();
			$cat['id'] = 'CAT:' . $line['id'];
			$cat['bare_id'] = (int)$line['id'];
			$cat['name'] = $line['title'];
			$cat['items'] = array();
			$cat['checkbox'] = false;
			$cat['type'] = 'category';
			$cat['unread'] = 0;
			$cat['child_unread'] = 0;
			$cat['auxcounter'] = 0;
			$cat['parent_id'] = $cat_id;

			$cat['items'] = $this->get_category_items($line['id']);

			$num_children = $this->calculate_children_count($cat);
			$cat['param'] = vsprintf(_ngettext('(%d feed)', '(%d feeds)', (int) $num_children), $num_children);

			if ($num_children > 0 || $show_empty_cats)
				array_push($items, $cat);

		}

		$fsth = $this->pdo->prepare("SELECT id, title, last_error,
			".SUBSTRING_FOR_DATE."(last_updated,1,19) AS last_updated, update_interval
			FROM ttrss_feeds
			WHERE cat_id = :cat AND
			owner_uid = :uid AND
			(:search = '' OR (LOWER(title) LIKE :search OR LOWER(feed_url) LIKE :search))
			ORDER BY order_id, title");

		$fsth->execute([":cat" => $cat_id, ":uid" => $_SESSION['uid'], ":search" => $search ? "%$search%" : ""]);

		while ($feed_line = $fsth->fetch()) {
			$feed = array();
			$feed['id'] = 'FEED:' . $feed_line['id'];
			$feed['bare_id'] = (int)$feed_line['id'];
			$feed['auxcounter'] = 0;
			$feed['name'] = $feed_line['title'];
			$feed['checkbox'] = false;
			$feed['unread'] = 0;
			$feed['error'] = $feed_line['last_error'];
			$feed['icon'] = Feeds::getFeedIcon($feed_line['id']);
			$feed['param'] = make_local_datetime(
				$feed_line['last_updated'], true);
			$feed['updates_disabled'] = (int)($feed_line['update_interval'] < 0);

			array_push($items, $feed);
		}

		return $items;
	}

	function getfeedtree() {
		print json_encode($this->makefeedtree());
	}

	function makefeedtree() {

		if (clean($_REQUEST['mode']) != 2)
			$search = $_SESSION["prefs_feed_search"];
		else
			$search = "";

		$root = array();
		$root['id'] = 'root';
		$root['name'] = __('Feeds');
		$root['items'] = array();
		$root['type'] = 'category';

		$enable_cats = get_pref('ENABLE_FEED_CATS');

		if (clean($_REQUEST['mode']) == 2) {

			if ($enable_cats) {
				$cat = $this->feedlist_init_cat(-1);
			} else {
				$cat['items'] = array();
			}

			foreach (array(-4, -3, -1, -2, 0, -6) as $i) {
				array_push($cat['items'], $this->feedlist_init_feed($i));
			}

			/* Plugin feeds for -1 */

			$feeds = PluginHost::getInstance()->get_feeds(-1);

			if ($feeds) {
				foreach ($feeds as $feed) {
					$feed_id = PluginHost::pfeed_to_feed_id($feed['id']);

					$item = array();
					$item['id'] = 'FEED:' . $feed_id;
					$item['bare_id'] = (int)$feed_id;
					$item['auxcounter'] = 0;
					$item['name'] = $feed['title'];
					$item['checkbox'] = false;
					$item['error'] = '';
					$item['icon'] = $feed['icon'];

					$item['param'] = '';
					$item['unread'] = 0; //$feed['sender']->get_unread($feed['id']);
					$item['type'] = 'feed';

					array_push($cat['items'], $item);
				}
			}

			if ($enable_cats) {
				array_push($root['items'], $cat);
			} else {
				$root['items'] = array_merge($root['items'], $cat['items']);
			}

			$sth = $this->pdo->prepare("SELECT * FROM
				ttrss_labels2 WHERE owner_uid = ? ORDER by caption");
			$sth->execute([$_SESSION['uid']]);

			if (get_pref('ENABLE_FEED_CATS')) {
				$cat = $this->feedlist_init_cat(-2);
			} else {
				$cat['items'] = array();
			}

			$num_labels = 0;
			while ($line = $sth->fetch()) {
				++$num_labels;

				$label_id = Labels::label_to_feed_id($line['id']);

				$feed = $this->feedlist_init_feed($label_id, false, 0);

				$feed['fg_color'] = $line['fg_color'];
				$feed['bg_color'] = $line['bg_color'];

				array_push($cat['items'], $feed);
			}

			if ($num_labels) {
				if ($enable_cats) {
					array_push($root['items'], $cat);
				} else {
					$root['items'] = array_merge($root['items'], $cat['items']);
				}
			}
		}

		if ($enable_cats) {
			$show_empty_cats = clean($_REQUEST['force_show_empty']) ||
				(clean($_REQUEST['mode']) != 2 && !$search);

			$sth = $this->pdo->prepare("SELECT id, title FROM ttrss_feed_categories
				WHERE owner_uid = ? AND parent_cat IS NULL ORDER BY order_id, title");
			$sth->execute([$_SESSION['uid']]);

			while ($line = $sth->fetch()) {
				$cat = array();
				$cat['id'] = 'CAT:' . $line['id'];
				$cat['bare_id'] = (int)$line['id'];
				$cat['auxcounter'] = 0;
				$cat['name'] = $line['title'];
				$cat['items'] = array();
				$cat['checkbox'] = false;
				$cat['type'] = 'category';
				$cat['unread'] = 0;
				$cat['child_unread'] = 0;

				$cat['items'] = $this->get_category_items($line['id']);

				$num_children = $this->calculate_children_count($cat);
				$cat['param'] = vsprintf(_ngettext('(%d feed)', '(%d feeds)', (int) $num_children), $num_children);

				if ($num_children > 0 || $show_empty_cats)
					array_push($root['items'], $cat);

				$root['param'] += count($cat['items']);
			}

			/* Uncategorized is a special case */

			$cat = array();
			$cat['id'] = 'CAT:0';
			$cat['bare_id'] = 0;
			$cat['auxcounter'] = 0;
			$cat['name'] = __("Uncategorized");
			$cat['items'] = array();
			$cat['type'] = 'category';
			$cat['checkbox'] = false;
			$cat['unread'] = 0;
			$cat['child_unread'] = 0;

			$fsth = $this->pdo->prepare("SELECT id, title,last_error,
				".SUBSTRING_FOR_DATE."(last_updated,1,19) AS last_updated, update_interval
				FROM ttrss_feeds
				WHERE cat_id IS NULL AND
				owner_uid = :uid AND
				(:search = '' OR (LOWER(title) LIKE :search OR LOWER(feed_url) LIKE :search))
				ORDER BY order_id, title");
			$fsth->execute([":uid" => $_SESSION['uid'], ":search" => $search ? "%$search%" : ""]);

			while ($feed_line = $fsth->fetch()) {
				$feed = array();
				$feed['id'] = 'FEED:' . $feed_line['id'];
				$feed['bare_id'] = (int)$feed_line['id'];
				$feed['auxcounter'] = 0;
				$feed['name'] = $feed_line['title'];
				$feed['checkbox'] = false;
				$feed['error'] = $feed_line['last_error'];
				$feed['icon'] = Feeds::getFeedIcon($feed_line['id']);
				$feed['param'] = make_local_datetime(
					$feed_line['last_updated'], true);
				$feed['unread'] = 0;
				$feed['type'] = 'feed';
				$feed['updates_disabled'] = (int)($feed_line['update_interval'] < 0);

				array_push($cat['items'], $feed);
			}

			$cat['param'] = vsprintf(_ngettext('(%d feed)', '(%d feeds)', count($cat['items'])), count($cat['items']));

			if (count($cat['items']) > 0 || $show_empty_cats)
				array_push($root['items'], $cat);

			$num_children = $this->calculate_children_count($root);
			$root['param'] = vsprintf(_ngettext('(%d feed)', '(%d feeds)', (int) $num_children), $num_children);

		} else {
			$fsth = $this->pdo->prepare("SELECT id, title, last_error,
				".SUBSTRING_FOR_DATE."(last_updated,1,19) AS last_updated, update_interval
				FROM ttrss_feeds
				WHERE owner_uid = :uid AND
				(:search = '' OR (LOWER(title) LIKE :search OR LOWER(feed_url) LIKE :search))
				ORDER BY order_id, title");
			$fsth->execute([":uid" => $_SESSION['uid'], ":search" => $search ? "%$search%" : ""]);

			while ($feed_line = $fsth->fetch()) {
				$feed = array();
				$feed['id'] = 'FEED:' . $feed_line['id'];
				$feed['bare_id'] = (int)$feed_line['id'];
				$feed['auxcounter'] = 0;
				$feed['name'] = $feed_line['title'];
				$feed['checkbox'] = false;
				$feed['error'] = $feed_line['last_error'];
				$feed['icon'] = Feeds::getFeedIcon($feed_line['id']);
				$feed['param'] = make_local_datetime(
					$feed_line['last_updated'], true);
				$feed['unread'] = 0;
				$feed['type'] = 'feed';
				$feed['updates_disabled'] = (int)($feed_line['update_interval'] < 0);

				array_push($root['items'], $feed);
			}

			$root['param'] = vsprintf(_ngettext('(%d feed)', '(%d feeds)', count($cat['items'])), count($cat['items']));
		}

		$fl = array();
		$fl['identifier'] = 'id';
		$fl['label'] = 'name';

		if (clean($_REQUEST['mode']) != 2) {
			$fl['items'] = array($root);
		} else {
			$fl['items'] = $root['items'];
		}

		return $fl;
	}

	function catsortreset() {
		$sth = $this->pdo->prepare("UPDATE ttrss_feed_categories
				SET order_id = 0 WHERE owner_uid = ?");
		$sth->execute([$_SESSION['uid']]);
	}

	function feedsortreset() {
		$sth = $this->pdo->prepare("UPDATE ttrss_feeds
				SET order_id = 0 WHERE owner_uid = ?");
		$sth->execute([$_SESSION['uid']]);
	}

	private function process_category_order(&$data_map, $item_id, $parent_id = false, $nest_level = 0) {

		$prefix = "";
		for ($i = 0; $i < $nest_level; $i++)
			$prefix .= "   ";

		Debug::log("$prefix C: $item_id P: $parent_id");

		$bare_item_id = substr($item_id, strpos($item_id, ':')+1);

		if ($item_id != 'root') {
			if ($parent_id && $parent_id != 'root') {
				$parent_bare_id = substr($parent_id, strpos($parent_id, ':')+1);
				$parent_qpart = $parent_bare_id;
			} else {
				$parent_qpart = null;
			}

			$sth = $this->pdo->prepare("UPDATE ttrss_feed_categories
				SET parent_cat = ? WHERE id = ? AND
				owner_uid = ?");
			$sth->execute([$parent_qpart, $bare_item_id, $_SESSION['uid']]);
		}

		$order_id = 1;

		$cat = $data_map[$item_id];

		if ($cat && is_array($cat)) {
			foreach ($cat as $item) {
				$id = $item['_reference'];
				$bare_id = substr($id, strpos($id, ':')+1);

				Debug::log("$prefix [$order_id] $id/$bare_id");

				if ($item['_reference']) {

					if (strpos($id, "FEED") === 0) {

						$cat_id = ($item_id != "root") ? $bare_item_id : null;

						$sth = $this->pdo->prepare("UPDATE ttrss_feeds
							SET order_id = ?, cat_id = ?
							WHERE id = ? AND owner_uid = ?");

						$sth->execute([$order_id, $cat_id ? $cat_id : null, $bare_id, $_SESSION['uid']]);

					} else if (strpos($id, "CAT:") === 0) {
						$this->process_category_order($data_map, $item['_reference'], $item_id,
							$nest_level+1);

						$sth = $this->pdo->prepare("UPDATE ttrss_feed_categories
								SET order_id = ? WHERE id = ? AND
								owner_uid = ?");
						$sth->execute([$order_id, $bare_id, $_SESSION['uid']]);
					}
				}

				++$order_id;
			}
		}
	}

	function savefeedorder() {
		$data = json_decode($_POST['payload'], true);

		#file_put_contents("/tmp/saveorder.json", clean($_POST['payload']));
		#$data = json_decode(file_get_contents("/tmp/saveorder.json"), true);

		if (!is_array($data['items']))
			$data['items'] = json_decode($data['items'], true);

#		print_r($data['items']);

		if (is_array($data) && is_array($data['items'])) {
#			$cat_order_id = 0;

			$data_map = array();
			$root_item = false;

			foreach ($data['items'] as $item) {

#				if ($item['id'] != 'root') {
					if (is_array($item['items'])) {
						if (isset($item['items']['_reference'])) {
							$data_map[$item['id']] = array($item['items']);
						} else {
							$data_map[$item['id']] = $item['items'];
						}
					}
				if ($item['id'] == 'root') {
					$root_item = $item['id'];
				}
			}

			$this->process_category_order($data_map, $root_item);
		}
	}

	function removeicon() {
		$feed_id = clean($_REQUEST["feed_id"]);

		$sth = $this->pdo->prepare("SELECT id FROM ttrss_feeds
			WHERE id = ? AND owner_uid = ?");
		$sth->execute([$feed_id, $_SESSION['uid']]);

		if ($row = $sth->fetch()) {
			@unlink(ICONS_DIR . "/$feed_id.ico");

			$sth = $this->pdo->prepare("UPDATE ttrss_feeds SET favicon_avg_color = NULL
				where id = ?");
			$sth->execute([$feed_id]);
		}
	}

	function uploadicon() {
		header("Content-type: text/html");

		if (is_uploaded_file($_FILES['icon_file']['tmp_name'])) {
			$tmp_file = tempnam(CACHE_DIR . '/upload', 'icon');

			$result = move_uploaded_file($_FILES['icon_file']['tmp_name'],
				$tmp_file);

			if (!$result) {
				return;
			}
		} else {
			return;
		}

		$icon_file = $tmp_file;
		$feed_id = clean($_REQUEST["feed_id"]);

		if (is_file($icon_file) && $feed_id) {
			if (filesize($icon_file) < 65535) {

				$sth = $this->pdo->prepare("SELECT id FROM ttrss_feeds
					WHERE id = ? AND owner_uid = ?");
				$sth->execute([$feed_id, $_SESSION['uid']]);

				if ($row = $sth->fetch()) {
					@unlink(ICONS_DIR . "/$feed_id.ico");
					if (rename($icon_file, ICONS_DIR . "/$feed_id.ico")) {

						$sth = $this->pdo->prepare("UPDATE ttrss_feeds SET
							favicon_avg_color = ''
							WHERE id = ?");
						$sth->execute([$feed_id]);

						$rc = 0;
					}
				} else {
					$rc = 2;
				}
			} else {
				$rc = 1;
			}
		} else {
			$rc = 2;
		}

		@unlink($icon_file);

		print "<script type=\"text/javascript\">";
		print "parent.CommonDialogs.uploadIconHandler($rc);";
		print "</script>";
		return;
	}

	function editfeed() {
		global $purge_intervals;
		global $update_intervals;


		$feed_id = clean($_REQUEST["id"]);

		$sth = $this->pdo->prepare("SELECT * FROM ttrss_feeds WHERE id = ? AND
				owner_uid = ?");
		$sth->execute([$feed_id, $_SESSION['uid']]);

		if ($row = $sth->fetch()) {
			print '<div dojoType="dijit.layout.TabContainer" style="height : 450px">
        		<div dojoType="dijit.layout.ContentPane" title="'.__('General').'">';

			$title = htmlspecialchars($row["title"]);

			print_hidden("id", "$feed_id");
			print_hidden("op", "pref-feeds");
			print_hidden("method", "editSave");

			print "<div class=\"dlgSec\">".__("Feed")."</div>";
			print "<div class=\"dlgSecCont\">";

			/* Title */

			print "<input dojoType=\"dijit.form.ValidationTextBox\" required=\"1\"
			placeHolder=\"".__("Feed Title")."\"
			style=\"font-size : 16px; width: 20em\" name=\"title\" value=\"$title\">";

			/* Feed URL */

			$feed_url = htmlspecialchars($row["feed_url"]);

			print "<hr/>";

			print __('URL:') . " ";
			print "<input dojoType=\"dijit.form.ValidationTextBox\" required=\"1\"
			placeHolder=\"".__("Feed URL")."\"
			regExp='^(http|https)://.*' style=\"width : 20em\"
			name=\"feed_url\" value=\"$feed_url\">";

			$last_error = $row["last_error"];

			if ($last_error) {
				print "&nbsp;<i class=\"material-icons\" 
					title=\"".htmlspecialchars($last_error)."\">error</i>";

			}

			/* Category */

			if (get_pref('ENABLE_FEED_CATS')) {

				$cat_id = $row["cat_id"];

				print "<hr/>";

				print __('Place in category:') . " ";

				print_feed_cat_select("cat_id", $cat_id,
					'dojoType="dijit.form.Select"');
			}

			/* Site URL  */

			$site_url = htmlspecialchars($row["site_url"]);

			print "<hr/>";

			print __('Site URL:') . " ";
			print "<input dojoType=\"dijit.form.ValidationTextBox\" required=\"1\"
			placeHolder=\"".__("Site URL")."\"
			regExp='^(http|https)://.*' style=\"width : 15em\"
			name=\"site_url\" value=\"$site_url\">";

			/* FTS Stemming Language */

			if (DB_TYPE == "pgsql") {
				$feed_language = $row["feed_language"];

				print "<hr/>";

				print __('Language:') . " ";
				print_select("feed_language", $feed_language, $this::$feed_languages,
					'dojoType="dijit.form.Select"');
			}

			print "</div>";

			print "<div class=\"dlgSec\">".__("Update")."</div>";
			print "<div class=\"dlgSecCont\">";

			/* Update Interval */

			$update_interval = $row["update_interval"];

			print_select_hash("update_interval", $update_interval, $update_intervals,
				'dojoType="dijit.form.Select"');

			/* Purge intl */

			$purge_interval = $row["purge_interval"];

			print "<hr/>";
			print __('Article purging:') . " ";

			print_select_hash("purge_interval", $purge_interval, $purge_intervals,
				'dojoType="dijit.form.Select" ' .
				((FORCE_ARTICLE_PURGE == 0) ? "" : 'disabled="1"'));

			print "</div>";

			$auth_login = htmlspecialchars($row["auth_login"]);
			$auth_pass = htmlspecialchars($row["auth_pass"]);

			$auth_enabled = $auth_login !== '' || $auth_pass !== '';

			$auth_style = $auth_enabled ? '' : 'display: none';
			print "<div id='feedEditDlg_loginContainer' style='$auth_style'>";
			print "<div class=\"dlgSec\">".__("Authentication")."</div>";
			print "<div class=\"dlgSecCont\">";

			print "<input dojoType=\"dijit.form.TextBox\" id=\"feedEditDlg_login\"
			placeHolder=\"".__("Login")."\"
			autocomplete=\"new-password\"
			name=\"auth_login\" value=\"$auth_login\"><hr/>";

			print "<input dojoType=\"dijit.form.TextBox\" type=\"password\" name=\"auth_pass\"
			autocomplete=\"new-password\"
			placeHolder=\"".__("Password")."\"
			value=\"$auth_pass\">";

			print "<div dojoType=\"dijit.Tooltip\" connectId=\"feedEditDlg_login\" position=\"below\">
			".__('<b>Hint:</b> you need to fill in your login information if your feed requires authentication, except for Twitter feeds.')."
			</div>";

			print "</div></div>";

			$auth_checked = $auth_enabled ? 'checked' : '';
			print "<div style=\"clear : both\">
				<input type=\"checkbox\" $auth_checked name=\"need_auth\" dojoType=\"dijit.form.CheckBox\" id=\"feedEditDlg_loginCheck\"
						onclick='displayIfChecked(this, \"feedEditDlg_loginContainer\")'>
					<label for=\"feedEditDlg_loginCheck\">".
				__('This feed requires authentication.')."</div>";

			print '</div><div dojoType="dijit.layout.ContentPane" title="'.__('Options').'">';

			//print "<div class=\"dlgSec\">".__("Options")."</div>";
			print "<div class=\"dlgSecSimple\">";

			$private = $row["private"];

			if ($private) {
				$checked = "checked=\"1\"";
			} else {
				$checked = "";
			}

			print "<input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" name=\"private\" id=\"private\"
			$checked>&nbsp;<label for=\"private\">".__('Hide from Popular feeds')."</label>";

			$include_in_digest = $row["include_in_digest"];

			if ($include_in_digest) {
				$checked = "checked=\"1\"";
			} else {
				$checked = "";
			}

			print "<hr/><input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" id=\"include_in_digest\"
			name=\"include_in_digest\"
			$checked>&nbsp;<label for=\"include_in_digest\">".__('Include in e-mail digest')."</label>";


			$always_display_enclosures = $row["always_display_enclosures"];

			if ($always_display_enclosures) {
				$checked = "checked";
			} else {
				$checked = "";
			}

			print "<hr/><input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" id=\"always_display_enclosures\"
			name=\"always_display_enclosures\"
			$checked>&nbsp;<label for=\"always_display_enclosures\">".__('Always display image attachments')."</label>";

			$hide_images = $row["hide_images"];

			if ($hide_images) {
				$checked = "checked=\"1\"";
			} else {
				$checked = "";
			}

			print "<hr/><input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" id=\"hide_images\"
		name=\"hide_images\"
			$checked>&nbsp;<label for=\"hide_images\">".
				__('Do not embed media')."</label>";

			$cache_images = $row["cache_images"];

			if ($cache_images) {
				$checked = "checked=\"1\"";
			} else {
				$checked = "";
			}

			print "<hr/><input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" id=\"cache_images\"
		name=\"cache_images\"
			$checked>&nbsp;<label for=\"cache_images\">".
				__('Cache media')."</label>";

			$mark_unread_on_update = $row["mark_unread_on_update"];

			if ($mark_unread_on_update) {
				$checked = "checked";
			} else {
				$checked = "";
			}

			print "<hr/><input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" id=\"mark_unread_on_update\"
			name=\"mark_unread_on_update\"
			$checked>&nbsp;<label for=\"mark_unread_on_update\">".__('Mark updated articles as unread')."</label>";

			print "</div>";

			print '</div><div dojoType="dijit.layout.ContentPane" title="'.__('Icon').'">';

			/* Icon */

			print "<div class=\"dlgSecSimple\">";

			print "<img class=\"feedIcon\" src=\"".Feeds::getFeedIcon($feed_id)."\">";

			print "<iframe name=\"icon_upload_iframe\"
				style=\"width: 400px; height: 100px; display: none;\"></iframe>";

			print "<form style='display : block' target=\"icon_upload_iframe\"
			enctype=\"multipart/form-data\" method=\"POST\"
			action=\"backend.php\">
			<label class=\"dijitButton\">".__("Choose file...")."
				<input style=\"display: none\" id=\"icon_file\" size=\"10\" name=\"icon_file\" type=\"file\">
			</label>
			<input type=\"hidden\" name=\"op\" value=\"pref-feeds\">
			<input type=\"hidden\" name=\"feed_id\" value=\"$feed_id\">
			<input type=\"hidden\" name=\"method\" value=\"uploadicon\">
			<button class=\"\" dojoType=\"dijit.form.Button\" onclick=\"return CommonDialogs.uploadFeedIcon();\"
				type=\"submit\">".__('Replace')."</button>
			<button class=\"alt-danger\" dojoType=\"dijit.form.Button\" onclick=\"return CommonDialogs.removeFeedIcon($feed_id);\"
				type=\"submit\">".__('Remove')."</button>
			</form>";

			print "</div>";

			print '</div><div dojoType="dijit.layout.ContentPane" title="'.__('Plugins').'">';

			PluginHost::getInstance()->run_hooks(PluginHost::HOOK_PREFS_EDIT_FEED,
				"hook_prefs_edit_feed", $feed_id);


			print "</div></div>";

			$title = htmlspecialchars($title, ENT_QUOTES);

			print "<div class='dlgButtons'>
			<div style=\"float : left\">
			<button class=\"alt-danger\" dojoType=\"dijit.form.Button\" onclick='return CommonDialogs.unsubscribeFeed($feed_id, \"$title\")'>".
				__('Unsubscribe')."</button>";

			print "</div>";

			print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('feedEditDlg').execute()\">".__('Save')."</button>
				<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('feedEditDlg').hide()\">".__('Cancel')."</button>
				</div>";
		}
	}

	function editfeeds() {
		global $purge_intervals;
		global $update_intervals;

		$feed_ids = clean($_REQUEST["ids"]);

		print_notice("Enable the options you wish to apply using checkboxes on the right:");

		print "<p>";

		print_hidden("ids", "$feed_ids");
		print_hidden("op", "pref-feeds");
		print_hidden("method", "batchEditSave");

		print "<div class=\"dlgSec\">".__("Feed")."</div>";
		print "<div class=\"dlgSecCont\">";

		/* Category */

		if (get_pref('ENABLE_FEED_CATS')) {

			print __('Place in category:') . " ";

			print_feed_cat_select("cat_id", false,
				'disabled="1" dojoType="dijit.form.Select"');

			$this->batch_edit_cbox("cat_id");

		}

		/* FTS Stemming Language */

		if (DB_TYPE == "pgsql") {
			print "<hr/>";

			print __('Language:') . " ";
			print_select("feed_language", "", $this::$feed_languages,
				'disabled="1" dojoType="dijit.form.Select"');

			$this->batch_edit_cbox("feed_language");
		}

		print "</div>";

		print "<div class=\"dlgSec\">".__("Update")."</div>";
		print "<div class=\"dlgSecCont\">";

		/* Update Interval */

		print_select_hash("update_interval", "", $update_intervals,
			'disabled="1" dojoType="dijit.form.Select"');

		$this->batch_edit_cbox("update_interval");

		/* Purge intl */

		if (FORCE_ARTICLE_PURGE == 0) {

			print "<br/>";

			print __('Article purging:') . " ";

			print_select_hash("purge_interval", "", $purge_intervals,
				'disabled="1" dojoType="dijit.form.Select"');

			$this->batch_edit_cbox("purge_interval");
		}

		print "</div>";
		print "<div class=\"dlgSec\">".__("Authentication")."</div>";
		print "<div class=\"dlgSecCont\">";

		print "<input dojoType=\"dijit.form.TextBox\"
			placeHolder=\"".__("Login")."\" disabled=\"1\"
			autocomplete=\"new-password\"
			name=\"auth_login\" value=\"\">";

		$this->batch_edit_cbox("auth_login");

		print "<hr/> <input dojoType=\"dijit.form.TextBox\" type=\"password\" name=\"auth_pass\"
			autocomplete=\"new-password\"
			placeHolder=\"".__("Password")."\" disabled=\"1\"
			value=\"\">";

		$this->batch_edit_cbox("auth_pass");

		print "</div>";
		print "<div class=\"dlgSec\">".__("Options")."</div>";
		print "<div class=\"dlgSecCont\">";

		print "<input disabled=\"1\" type=\"checkbox\" name=\"private\" id=\"private\"
			dojoType=\"dijit.form.CheckBox\">&nbsp;<label id=\"private_l\" class='insensitive' for=\"private\">".__('Hide from Popular feeds')."</label>";

		print "&nbsp;"; $this->batch_edit_cbox("private", "private_l");

		print "<br/><input disabled=\"1\" type=\"checkbox\" id=\"include_in_digest\"
			name=\"include_in_digest\"
			dojoType=\"dijit.form.CheckBox\">&nbsp;<label id=\"include_in_digest_l\" class='insensitive' for=\"include_in_digest\">".__('Include in e-mail digest')."</label>";

		print "&nbsp;"; $this->batch_edit_cbox("include_in_digest", "include_in_digest_l");

		print "<br/><input disabled=\"1\" type=\"checkbox\" id=\"always_display_enclosures\"
			name=\"always_display_enclosures\"
			dojoType=\"dijit.form.CheckBox\">&nbsp;<label id=\"always_display_enclosures_l\" class='insensitive' for=\"always_display_enclosures\">".__('Always display image attachments')."</label>";

		print "&nbsp;"; $this->batch_edit_cbox("always_display_enclosures", "always_display_enclosures_l");

		print "<br/><input disabled=\"1\" type=\"checkbox\" id=\"hide_images\"
			name=\"hide_images\"
			dojoType=\"dijit.form.CheckBox\">&nbsp;<label class='insensitive' id=\"hide_images_l\"
			for=\"hide_images\">".
		__('Do not embed media')."</label>";

		print "&nbsp;"; $this->batch_edit_cbox("hide_images", "hide_images_l");

		print "<br/><input disabled=\"1\" type=\"checkbox\" id=\"cache_images\"
			name=\"cache_images\"
			dojoType=\"dijit.form.CheckBox\">&nbsp;<label class='insensitive' id=\"cache_images_l\"
			for=\"cache_images\">".
		__('Cache media')."</label>";

		print "&nbsp;"; $this->batch_edit_cbox("cache_images", "cache_images_l");

		print "<br/><input disabled=\"1\" type=\"checkbox\" id=\"mark_unread_on_update\"
			name=\"mark_unread_on_update\"
			dojoType=\"dijit.form.CheckBox\">&nbsp;<label id=\"mark_unread_on_update_l\" class='insensitive' for=\"mark_unread_on_update\">".__('Mark updated articles as unread')."</label>";

		print "&nbsp;"; $this->batch_edit_cbox("mark_unread_on_update", "mark_unread_on_update_l");

		print "</div>";

		print "<div class='dlgButtons'>
			<button dojoType=\"dijit.form.Button\"
				onclick=\"return dijit.byId('feedEditDlg').execute()\">".
				__('Save')."</button>
			<button dojoType=\"dijit.form.Button\"
			onclick=\"return dijit.byId('feedEditDlg').hide()\">".
				__('Cancel')."</button>
			</div>";

		return;
	}

	function batchEditSave() {
		return $this->editsaveops(true);
	}

	function editSave() {
		return $this->editsaveops(false);
	}

	function editsaveops($batch) {

		$feed_title = trim(clean($_POST["title"]));
		$feed_url = trim(clean($_POST["feed_url"]));
		$site_url = trim(clean($_POST["site_url"]));
		$upd_intl = (int) clean($_POST["update_interval"]);
		$purge_intl = (int) clean($_POST["purge_interval"]);
		$feed_id = (int) clean($_POST["id"]); /* editSave */
		$feed_ids = explode(",", clean($_POST["ids"])); /* batchEditSave */
		$cat_id = (int) clean($_POST["cat_id"]);
		$auth_login = trim(clean($_POST["auth_login"]));
		$auth_pass = trim(clean($_POST["auth_pass"]));
		$private = checkbox_to_sql_bool(clean($_POST["private"]));
		$include_in_digest = checkbox_to_sql_bool(
			clean($_POST["include_in_digest"]));
		$cache_images = checkbox_to_sql_bool(
			clean($_POST["cache_images"]));
		$hide_images = checkbox_to_sql_bool(
			clean($_POST["hide_images"]));
		$always_display_enclosures = checkbox_to_sql_bool(
			clean($_POST["always_display_enclosures"]));

		$mark_unread_on_update = checkbox_to_sql_bool(
			clean($_POST["mark_unread_on_update"]));

		$feed_language = trim(clean($_POST["feed_language"]));

		if (!$batch) {
			if (clean($_POST["need_auth"]) !== 'on') {
				$auth_login = '';
				$auth_pass = '';
			}

			/* $sth = $this->pdo->prepare("SELECT feed_url FROM ttrss_feeds WHERE id = ?");
			$sth->execute([$feed_id]);
			$row = $sth->fetch();$orig_feed_url = $row["feed_url"];

			$reset_basic_info = $orig_feed_url != $feed_url; */

			$sth = $this->pdo->prepare("UPDATE ttrss_feeds SET
				cat_id = :cat_id,
				title = :title,
				feed_url = :feed_url,
				site_url = :site_url,
				update_interval = :upd_intl,
				purge_interval = :purge_intl,
				auth_login = :auth_login,
				auth_pass = :auth_pass,
				auth_pass_encrypted = false,
				private = :private,
				cache_images = :cache_images,
				hide_images = :hide_images,
				include_in_digest = :include_in_digest,
				always_display_enclosures = :always_display_enclosures,
				mark_unread_on_update = :mark_unread_on_update,
				feed_language = :feed_language
			WHERE id = :id AND owner_uid = :uid");

			$sth->execute([":title" => $feed_title,
					":cat_id" => $cat_id ? $cat_id : null,
					":feed_url" => $feed_url,
					":site_url" => $site_url,
					":upd_intl" => $upd_intl,
					":purge_intl" => $purge_intl,
					":auth_login" => $auth_login,
					":auth_pass" => $auth_pass,
					":private" => (int)$private,
					":cache_images" => (int)$cache_images,
					":hide_images" => (int)$hide_images,
					":include_in_digest" => (int)$include_in_digest,
					":always_display_enclosures" => (int)$always_display_enclosures,
					":mark_unread_on_update" => (int)$mark_unread_on_update,
					":feed_language" => $feed_language,
					":id" => $feed_id,
					":uid" => $_SESSION['uid']]);

/*			if ($reset_basic_info) {
				RSSUtils::set_basic_feed_info($feed_id);
			} */

			PluginHost::getInstance()->run_hooks(PluginHost::HOOK_PREFS_SAVE_FEED,
				"hook_prefs_save_feed", $feed_id);

		} else {
			$feed_data = array();

			foreach (array_keys($_POST) as $k) {
				if ($k != "op" && $k != "method" && $k != "ids") {
					$feed_data[$k] = clean($_POST[$k]);
				}
			}

			$this->pdo->beginTransaction();

			$feed_ids_qmarks = arr_qmarks($feed_ids);

			foreach (array_keys($feed_data) as $k) {

				$qpart = "";

				switch ($k) {
					case "title":
						$qpart = "title = " . $this->pdo->quote($feed_title);
						break;

					case "feed_url":
						$qpart = "feed_url = " . $this->pdo->quote($feed_url);
						break;

					case "update_interval":
						$qpart = "update_interval = " . $this->pdo->quote($upd_intl);
						break;

					case "purge_interval":
						$qpart = "purge_interval =" . $this->pdo->quote($purge_intl);
						break;

					case "auth_login":
						$qpart = "auth_login = " . $this->pdo->quote($auth_login);
						break;

					case "auth_pass":
						$qpart = "auth_pass =" . $this->pdo->quote($auth_pass). ", auth_pass_encrypted = false";
						break;

					case "private":
						$qpart = "private = " . $this->pdo->quote($private);
						break;

					case "include_in_digest":
						$qpart = "include_in_digest = " . $this->pdo->quote($include_in_digest);
						break;

					case "always_display_enclosures":
						$qpart = "always_display_enclosures = " . $this->pdo->quote($always_display_enclosures);
						break;

					case "mark_unread_on_update":
						$qpart = "mark_unread_on_update = " . $this->pdo->quote($mark_unread_on_update);
						break;

					case "cache_images":
						$qpart = "cache_images = " . $this->pdo->quote($cache_images);
						break;

					case "hide_images":
						$qpart = "hide_images = " . $this->pdo->quote($hide_images);
						break;

					case "cat_id":
						if (get_pref('ENABLE_FEED_CATS')) {
							if ($cat_id) {
								$qpart = "cat_id = " . $this->pdo->quote($cat_id);
							} else {
								$qpart = 'cat_id = NULL';
							}
						} else {
							$qpart = "";
						}

						break;

					case "feed_language":
						$qpart = "feed_language = " . $this->pdo->quote($feed_language);
						break;

				}

				if ($qpart) {
					$sth = $this->pdo->prepare("UPDATE ttrss_feeds SET $qpart WHERE id IN ($feed_ids_qmarks)
						AND owner_uid = ?");
					$sth->execute(array_merge($feed_ids, [$_SESSION['uid']]));
				}
			}

			$this->pdo->commit();
		}
		return;
	}

	function remove() {

		$ids = explode(",", clean($_REQUEST["ids"]));

		foreach ($ids as $id) {
			Pref_Feeds::remove_feed($id, $_SESSION["uid"]);
		}

		return;
	}

	function removeCat() {
		$ids = explode(",", clean($_REQUEST["ids"]));
		foreach ($ids as $id) {
			$this->remove_feed_category($id, $_SESSION["uid"]);
		}
	}

	function addCat() {
		$feed_cat = trim(clean($_REQUEST["cat"]));

		add_feed_category($feed_cat);
	}

	function index() {

		print "<div dojoType='dijit.layout.AccordionContainer' region='center'>";
		print "<div style='padding : 0px' dojoType='dijit.layout.AccordionPane' 
			title=\"<i class='material-icons'>rss_feed</i> ".__('Feeds')."\">";

		$sth = $this->pdo->prepare("SELECT COUNT(id) AS num_errors
			FROM ttrss_feeds WHERE last_error != '' AND owner_uid = ?");
		$sth->execute([$_SESSION['uid']]);

		if ($row = $sth->fetch()) {
			$num_errors = $row["num_errors"];
		} else {
			$num_errors = 0;
		}

		if ($num_errors > 0) {

			$error_button = "<button dojoType=\"dijit.form.Button\"
			  		onclick=\"CommonDialogs.showFeedsWithErrors()\" id=\"errorButton\">" .
				__("Feeds with errors") . "</button>";
		}

		$inactive_button = "<button dojoType=\"dijit.form.Button\"
				id=\"pref_feeds_inactive_btn\"
				style=\"display : none\"
				onclick=\"dijit.byId('feedTree').showInactiveFeeds()\">" .
				__("Inactive feeds") . "</button>";

		$feed_search = clean($_REQUEST["search"]);

		if (array_key_exists("search", $_REQUEST)) {
			$_SESSION["prefs_feed_search"] = $feed_search;
		} else {
			$feed_search = $_SESSION["prefs_feed_search"];
		}

		print '<div dojoType="dijit.layout.BorderContainer" gutters="false">';

		print "<div region='top' dojoType=\"dijit.Toolbar\">"; #toolbar

		print "<div style='float : right; padding-right : 4px;'>
			<input dojoType=\"dijit.form.TextBox\" id=\"feed_search\" size=\"20\" type=\"search\"
				value=\"$feed_search\">
			<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('feedTree').reload()\">".
				__('Search')."</button>
			</div>";

		print "<div dojoType=\"dijit.form.DropDownButton\">".
				"<span>" . __('Select')."</span>";
		print "<div dojoType=\"dijit.Menu\" style=\"display: none;\">";
		print "<div onclick=\"dijit.byId('feedTree').model.setAllChecked(true)\"
			dojoType=\"dijit.MenuItem\">".__('All')."</div>";
		print "<div onclick=\"dijit.byId('feedTree').model.setAllChecked(false)\"
			dojoType=\"dijit.MenuItem\">".__('None')."</div>";
		print "</div></div>";

		print "<div dojoType=\"dijit.form.DropDownButton\">".
				"<span>" . __('Feeds')."</span>";
		print "<div dojoType=\"dijit.Menu\" style=\"display: none;\">";
		print "<div onclick=\"CommonDialogs.quickAddFeed()\"
			dojoType=\"dijit.MenuItem\">".__('Subscribe to feed')."</div>";
		print "<div onclick=\"dijit.byId('feedTree').editSelectedFeed()\"
			dojoType=\"dijit.MenuItem\">".__('Edit selected feeds')."</div>";
		print "<div onclick=\"dijit.byId('feedTree').resetFeedOrder()\"
			dojoType=\"dijit.MenuItem\">".__('Reset sort order')."</div>";
		print "<div onclick=\"dijit.byId('feedTree').batchSubscribe()\"
			dojoType=\"dijit.MenuItem\">".__('Batch subscribe')."</div>";
		print "<div dojoType=\"dijit.MenuItem\" onclick=\"dijit.byId('feedTree').removeSelectedFeeds()\">"
			.__('Unsubscribe')."</div> ";
		print "</div></div>";

		if (get_pref('ENABLE_FEED_CATS')) {
			print "<div dojoType=\"dijit.form.DropDownButton\">".
					"<span>" . __('Categories')."</span>";
			print "<div dojoType=\"dijit.Menu\" style=\"display: none;\">";
			print "<div onclick=\"dijit.byId('feedTree').createCategory()\"
				dojoType=\"dijit.MenuItem\">".__('Add category')."</div>";
			print "<div onclick=\"dijit.byId('feedTree').resetCatOrder()\"
				dojoType=\"dijit.MenuItem\">".__('Reset sort order')."</div>";
			print "<div onclick=\"dijit.byId('feedTree').removeSelectedCategories()\"
				dojoType=\"dijit.MenuItem\">".__('Remove selected')."</div>";
			print "</div></div>";

		}

		print $error_button;
		print $inactive_button;

		print "</div>"; # toolbar

		//print '</div>';
		print '<div style="padding : 0px" dojoType="dijit.layout.ContentPane" region="center">';

		print "<div id=\"feedlistLoading\">
		<img src='images/indicator_tiny.gif'>".
		 __("Loading, please wait...")."</div>";

		$auto_expand = $feed_search != "" ? "true" : "false";

		print "<div dojoType=\"fox.PrefFeedStore\" jsId=\"feedStore\"
			url=\"backend.php?op=pref-feeds&method=getfeedtree\">
		</div>
		<div dojoType=\"lib.CheckBoxStoreModel\" jsId=\"feedModel\" store=\"feedStore\"
		query=\"{id:'root'}\" rootId=\"root\" rootLabel=\"Feeds\"
			childrenAttrs=\"items\" checkboxStrict=\"false\" checkboxAll=\"false\">
		</div>
		<div dojoType=\"fox.PrefFeedTree\" id=\"feedTree\"
			dndController=\"dijit.tree.dndSource\"
			betweenThreshold=\"5\"
			autoExpand='$auto_expand'
			model=\"feedModel\" openOnClick=\"false\">
		<script type=\"dojo/method\" event=\"onClick\" args=\"item\">
			var id = String(item.id);
			var bare_id = id.substr(id.indexOf(':')+1);

			if (id.match('FEED:')) {
				CommonDialogs.editFeed(bare_id);
			} else if (id.match('CAT:')) {
				dijit.byId('feedTree').editCategory(bare_id, item);
			}
		</script>
		<script type=\"dojo/method\" event=\"onLoad\" args=\"item\">
			Element.hide(\"feedlistLoading\");

			dijit.byId('feedTree').checkInactiveFeeds();
		</script>
		</div>";

#		print "<div dojoType=\"dijit.Tooltip\" connectId=\"feedTree\" position=\"below\">
#			".__('<b>Hint:</b> you can drag feeds and categories around.')."
#			</div>";

		print '</div>';
		print '</div>';

		print "</div>"; # feeds pane

		print "<div dojoType=\"dijit.layout.AccordionPane\" 
			title=\"<i class='material-icons'>import_export</i> ".__('OPML')."\">";

		print __("Using OPML you can export and import your feeds, filters, labels and Tiny Tiny RSS settings.") .
			__("Only main settings profile can be migrated using OPML.");

		print "<p/>";

		print "<iframe id=\"upload_iframe\"
			name=\"upload_iframe\" onload=\"Helpers.OPML.onImportComplete(this)\"
			style=\"width: 400px; height: 100px; display: none;\"></iframe>";

		print "<form  name=\"opml_form\" style='display : block' target=\"upload_iframe\"
			enctype=\"multipart/form-data\" method=\"POST\"
			action=\"backend.php\">
			<label class=\"dijitButton\">".__("Choose file...")."
				<input style=\"display : none\" id=\"opml_file\" name=\"opml_file\" type=\"file\">&nbsp;
			</label>
			<input type=\"hidden\" name=\"op\" value=\"dlg\">
			<input type=\"hidden\" name=\"method\" value=\"importOpml\">
			<button dojoType=\"dijit.form.Button\" onclick=\"return Helpers.OPML.import();\" type=\"submit\">" .
			__('Import OPML') . "</button>";

		print "</form>";

		print "<hr>";

		print "<form dojoType=\"dijit.form.Form\" id=\"opmlExportForm\">";

		print "<button dojoType=\"dijit.form.Button\"
			onclick=\"Helpers.OPML.export()\" >" .
			__('Export OPML') . "</button>";

		print "<label>";
		print_checkbox("include_settings", true, "1", "");
		print "&nbsp;" . __("Include settings");
		print "</label>";

		print "</form>";

		print "<p/>";

		print "<h2>" . __("Published OPML") . "</h2>";

		print "<p>" . __('Your OPML can be published publicly and can be subscribed by anyone who knows the URL below.') .
			" " .
			__("Published OPML does not include your Tiny Tiny RSS settings, feeds that require authentication or feeds hidden from Popular feeds.") . "</p>";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return App.displayDlg('".__("Public OPML URL")."','pubOPMLUrl')\">".
			__('Display published OPML URL')."</button> ";

		PluginHost::getInstance()->run_hooks(PluginHost::HOOK_PREFS_TAB_SECTION,
			"hook_prefs_tab_section", "prefFeedsOPML");

		print "</div>"; # pane

		print "<div dojoType=\"dijit.layout.AccordionPane\" 
			title=\"<i class='material-icons'>share</i> ".__('Published & shared articles / Generated feeds')."\">";

		print __('Published articles can be subscribed by anyone who knows the following URL:');

		$rss_url = '-2::' . htmlspecialchars(get_self_url_prefix() .
				"/public.php?op=rss&id=-2&view-mode=all_articles");;

		print "<p>";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return App.displayDlg('".__("Show as feed")."','generatedFeed', '$rss_url')\">".
			__('Display URL')."</button> ";

		print "<button class=\"alt-danger\" dojoType=\"dijit.form.Button\" onclick=\"return Helpers.clearFeedAccessKeys()\">".
			__('Clear all generated URLs')."</button> ";

		print "</p>";

		PluginHost::getInstance()->run_hooks(PluginHost::HOOK_PREFS_TAB_SECTION,
			"hook_prefs_tab_section", "prefFeedsPublishedGenerated");

		print "</div>"; #pane

		PluginHost::getInstance()->run_hooks(PluginHost::HOOK_PREFS_TAB,
			"hook_prefs_tab", "prefFeeds");

		print "</div>"; #container
	}

	private function feedlist_init_cat($cat_id) {
		$obj = array();
		$cat_id = (int) $cat_id;

		if ($cat_id > 0) {
			$cat_unread = CCache::find($cat_id, $_SESSION["uid"], true);
		} else if ($cat_id == 0 || $cat_id == -2) {
			$cat_unread = Feeds::getCategoryUnread($cat_id);
		}

		$obj['id'] = 'CAT:' . $cat_id;
		$obj['items'] = array();
		$obj['name'] = Feeds::getCategoryTitle($cat_id);
		$obj['type'] = 'category';
		$obj['unread'] = (int) $cat_unread;
		$obj['bare_id'] = $cat_id;

		return $obj;
	}

	private function feedlist_init_feed($feed_id, $title = false, $unread = false, $error = '', $updated = '') {
		$obj = array();
		$feed_id = (int) $feed_id;

		if (!$title)
			$title = Feeds::getFeedTitle($feed_id, false);

		if ($unread === false)
			$unread = getFeedUnread($feed_id, false);

		$obj['id'] = 'FEED:' . $feed_id;
		$obj['name'] = $title;
		$obj['unread'] = (int) $unread;
		$obj['type'] = 'feed';
		$obj['error'] = $error;
		$obj['updated'] = $updated;
		$obj['icon'] = Feeds::getFeedIcon($feed_id);
		$obj['bare_id'] = $feed_id;
		$obj['auxcounter'] = 0;

		return $obj;
	}

	function inactiveFeeds() {

		if (DB_TYPE == "pgsql") {
			$interval_qpart = "NOW() - INTERVAL '3 months'";
		} else {
			$interval_qpart = "DATE_SUB(NOW(), INTERVAL 3 MONTH)";
		}

		$sth = $this->pdo->prepare("SELECT ttrss_feeds.title, ttrss_feeds.site_url,
		  		ttrss_feeds.feed_url, ttrss_feeds.id, MAX(updated) AS last_article
			FROM ttrss_feeds, ttrss_entries, ttrss_user_entries WHERE
				(SELECT MAX(updated) FROM ttrss_entries, ttrss_user_entries WHERE
					ttrss_entries.id = ref_id AND
						ttrss_user_entries.feed_id = ttrss_feeds.id) < $interval_qpart
			AND ttrss_feeds.owner_uid = ? AND
				ttrss_user_entries.feed_id = ttrss_feeds.id AND
				ttrss_entries.id = ref_id
			GROUP BY ttrss_feeds.title, ttrss_feeds.id, ttrss_feeds.site_url, ttrss_feeds.feed_url
			ORDER BY last_article");
		$sth->execute([$_SESSION['uid']]);

		print "<p" .__("These feeds have not been updated with new content for 3 months (oldest first):") . "</p>";

		print "<div dojoType=\"dijit.Toolbar\">";
		print "<div dojoType=\"dijit.form.DropDownButton\">".
				"<span>" . __('Select')."</span>";
		print "<div dojoType=\"dijit.Menu\" style=\"display: none;\">";
		print "<div onclick=\"Tables.select('inactive-feeds-list', true)\"
			dojoType=\"dijit.MenuItem\">".__('All')."</div>";
		print "<div onclick=\"Tables.select('inactive-feeds-list', false)\"
			dojoType=\"dijit.MenuItem\">".__('None')."</div>";
		print "</div></div>";
		print "</div>"; #toolbar

		print "<div class='panel panel-scrollable'>";
		print "<table width='100%' id='inactive-feeds-list'>";

		$lnum = 1;

		while ($line = $sth->fetch()) {

			$feed_id = $line["id"];

			print "<tr data-row-id='$feed_id'>";

			print "<td width='5%' align='center'><input
				onclick='Tables.onRowChecked(this);' dojoType=\"dijit.form.CheckBox\"
				type=\"checkbox\"></td>";
			print "<td>";

			print "<a class=\"visibleLink\" href=\"#\" ".
				"title=\"".__("Click to edit feed")."\" ".
				"onclick=\"CommonDialogs.editFeed(".$line["id"].")\">".
				htmlspecialchars($line["title"])."</a>";

			print "</td><td class=\"insensitive\" align='right'>";
			print make_local_datetime($line['last_article'], false);
			print "</td>";
			print "</tr>";

			++$lnum;
		}

		print "</table>";
		print "</div>";

		print "<div class='dlgButtons'>";
		print "<div style='float : left'>";
		print "<button class=\"alt-danger\" dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('inactiveFeedsDlg').removeSelected()\">"
			.__('Unsubscribe from selected feeds')."</button> ";
		print "</div>";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('inactiveFeedsDlg').hide()\">".
			__('Close this window')."</button>";

		print "</div>";

	}

	function feedsWithErrors() {
		$sth = $this->pdo->prepare("SELECT id,title,feed_url,last_error,site_url
			FROM ttrss_feeds WHERE last_error != '' AND owner_uid = ?");
		$sth->execute([$_SESSION['uid']]);

		print "<div dojoType=\"dijit.Toolbar\">";
		print "<div dojoType=\"dijit.form.DropDownButton\">".
				"<span>" . __('Select')."</span>";
		print "<div dojoType=\"dijit.Menu\" style=\"display: none;\">";
		print "<div onclick=\"Tables.select('error-feeds-list', true)\"
			dojoType=\"dijit.MenuItem\">".__('All')."</div>";
		print "<div onclick=\"Tables.select('error-feeds-list', false)\"
			dojoType=\"dijit.MenuItem\">".__('None')."</div>";
		print "</div></div>";
		print "</div>"; #toolbar

		print "<div class='panel panel-scrollable'>";
		print "<table width='100%' id='error-feeds-list'>";

		$lnum = 1;

		while ($line = $sth->fetch()) {

			$feed_id = $line["id"];

			print "<tr data-row-id='$feed_id'>";

			print "<td width='5%' align='center'><input
				onclick='Tables.onRowChecked(this);' dojoType=\"dijit.form.CheckBox\"
				type=\"checkbox\"></td>";
			print "<td>";

			print "<a class=\"visibleLink\" href=\"#\" ".
				"title=\"".__("Click to edit feed")."\" ".
				"onclick=\"CommonDialogs.editFeed(".$line["id"].")\">".
				htmlspecialchars($line["title"])."</a>: ";

			print "<span class=\"insensitive\">";
			print htmlspecialchars($line["last_error"]);
			print "</span>";

			print "</td>";
			print "</tr>";

			++$lnum;
		}

		print "</table>";
		print "</div>";

		print "<div class='dlgButtons'>";
		print "<div style='float : left'>";
		print "<button class=\"alt-danger\" dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('errorFeedsDlg').removeSelected()\">"
			.__('Unsubscribe from selected feeds')."</button> ";
		print "</div>";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('errorFeedsDlg').hide()\">".
			__('Close this window')."</button>";

		print "</div>";
	}

	private function remove_feed_category($id, $owner_uid) {

		$sth = $this->pdo->prepare("DELETE FROM ttrss_feed_categories
			WHERE id = ? AND owner_uid = ?");
		$sth->execute([$id, $owner_uid]);

		CCache::remove($id, $owner_uid, true);
	}

	static function remove_feed($id, $owner_uid) {
		foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_UNSUBSCRIBE_FEED) as $p) {
			if (! $p->hook_unsubscribe_feed($id, $owner_uid)) {
                user_error("Feed $id (owner: $owner_uid) not removed due to plugin error (HOOK_UNSUBSCRIBE_FEED).", E_USER_WARNING);
                return;
			}
		}

		$pdo = Db::pdo();

		if ($id > 0) {
			$pdo->beginTransaction();

			/* save starred articles in Archived feed */

			/* prepare feed if necessary */

			$sth = $pdo->prepare("SELECT feed_url FROM ttrss_feeds WHERE id = ?
				AND owner_uid = ?");
			$sth->execute([$id, $owner_uid]);

			if ($row = $sth->fetch()) {
				$feed_url = $row["feed_url"];

				$sth = $pdo->prepare("SELECT id FROM ttrss_archived_feeds
					WHERE feed_url = ? AND owner_uid = ?");
				$sth->execute([$feed_url, $owner_uid]);

				if ($row = $sth->fetch()) {
					$archive_id = $row["id"];
				} else {
					$res = $pdo->query("SELECT MAX(id) AS id FROM ttrss_archived_feeds");
					$row = $res->fetch();

					$new_feed_id = (int)$row['id'] + 1;

					$sth = $pdo->prepare("INSERT INTO ttrss_archived_feeds
						(id, owner_uid, title, feed_url, site_url)
							SELECT ?, owner_uid, title, feed_url, site_url from ttrss_feeds
							WHERE id = ?");
					$sth->execute([$new_feed_id, $id]);

					$archive_id = $new_feed_id;
				}

				$sth = $pdo->prepare("UPDATE ttrss_user_entries SET feed_id = NULL,
					orig_feed_id = ? WHERE feed_id = ? AND
						marked = true AND owner_uid = ?");

				$sth->execute([$archive_id, $id, $owner_uid]);

				/* Remove access key for the feed */

				$sth = $pdo->prepare("DELETE FROM ttrss_access_keys WHERE
					feed_id = ? AND owner_uid = ?");
				$sth->execute([$id, $owner_uid]);

				/* remove the feed */

				$sth = $pdo->prepare("DELETE FROM ttrss_feeds
					WHERE id = ? AND owner_uid = ?");
				$sth->execute([$id, $owner_uid]);
			}

			$pdo->commit();

			if (file_exists(ICONS_DIR . "/$id.ico")) {
				unlink(ICONS_DIR . "/$id.ico");
			}

			CCache::remove($id, $owner_uid);

		} else {
			Labels::remove(Labels::feed_to_label_id($id), $owner_uid);
			//CCache::remove($id, $owner_uid); don't think labels are cached
		}
	}

	function batchSubscribe() {
		print_hidden("op", "pref-feeds");
		print_hidden("method", "batchaddfeeds");

		print "<table width='100%'><tr><td>
			".__("Add one valid RSS feed per line (no feed detection is done)")."
		</td><td align='right'>";
		if (get_pref('ENABLE_FEED_CATS')) {
			print __('Place in category:') . " ";
			print_feed_cat_select("cat", false, 'dojoType="dijit.form.Select"');
		}
		print "</td></tr><tr><td colspan='2'>";
		print "<textarea
			style='font-size : 12px; width : 98%; height: 200px;'
			placeHolder=\"".__("Feeds to subscribe, One per line")."\"
			dojoType=\"dijit.form.SimpleTextarea\" required=\"1\" name=\"feeds\"></textarea>";

		print "</td></tr><tr><td colspan='2'>";

		print "<div id='feedDlg_loginContainer' style='display : none'>
				" .
				" <input dojoType=\"dijit.form.TextBox\" name='login'\"
					placeHolder=\"".__("Login")."\"
					style=\"width : 10em;\"> ".
				" <input
					placeHolder=\"".__("Password")."\"
					dojoType=\"dijit.form.TextBox\" type='password'
					autocomplete=\"new-password\"
					style=\"width : 10em;\" name='pass'\">".
				"</div>";

		print "</td></tr><tr><td colspan='2'>";

		print "<div style=\"clear : both\">
			<input type=\"checkbox\" name=\"need_auth\" dojoType=\"dijit.form.CheckBox\" id=\"feedDlg_loginCheck\"
					onclick='displayIfChecked(this, \"feedDlg_loginContainer\")'>
				<label for=\"feedDlg_loginCheck\">".
				__('Feeds require authentication.')."</div>";

		print "</form>";

		print "</td></tr></table>";

		print "<div class=\"dlgButtons\">
			<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('batchSubDlg').execute()\">".__('Subscribe')."</button>
			<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('batchSubDlg').hide()\">".__('Cancel')."</button>
			</div>";
	}

	function batchAddFeeds() {
		$cat_id = clean($_REQUEST['cat']);
		$feeds = explode("\n", clean($_REQUEST['feeds']));
		$login = clean($_REQUEST['login']);
		$pass = trim(clean($_REQUEST['pass']));

		foreach ($feeds as $feed) {
			$feed = trim($feed);

			if (validate_feed_url($feed)) {

				$this->pdo->beginTransaction();

				$sth = $this->pdo->prepare("SELECT id FROM ttrss_feeds
						WHERE feed_url = ? AND owner_uid = ?");
				$sth->execute([$feed, $_SESSION['uid']]);

				if (!$sth->fetch()) {
					$sth = $this->pdo->prepare("INSERT INTO ttrss_feeds
							(owner_uid,feed_url,title,cat_id,auth_login,auth_pass,update_method,auth_pass_encrypted)
						VALUES (?, ?, '[Unknown]', ?, ?, ?, 0, false)");

					$sth->execute([$_SESSION['uid'], $feed, $cat_id ? $cat_id : null, $login, $pass]);
				}

				$this->pdo->commit();
			}
		}
	}

	function regenOPMLKey() {
		$this->update_feed_access_key('OPML:Publish',
		false, $_SESSION["uid"]);

		$new_link = Opml::opml_publish_url();

		print json_encode(array("link" => $new_link));
	}

	function regenFeedKey() {
		$feed_id = clean($_REQUEST['id']);
		$is_cat = clean($_REQUEST['is_cat']);

		$new_key = $this->update_feed_access_key($feed_id, $is_cat);

		print json_encode(["link" => $new_key]);
	}


	private function update_feed_access_key($feed_id, $is_cat, $owner_uid = false) {
		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		// clear old value and generate new one
		$sth = $this->pdo->prepare("DELETE FROM ttrss_access_keys
			WHERE feed_id = ? AND is_cat = ? AND owner_uid = ?");
		$sth->execute([$feed_id, bool_to_sql_bool($is_cat), $owner_uid]);

		return get_feed_access_key($feed_id, $is_cat, $owner_uid);
	}

	// Silent
	function clearKeys() {
		$sth = $this->pdo->prepare("DELETE FROM ttrss_access_keys WHERE
			owner_uid = ?");
		$sth->execute([$_SESSION['uid']]);
	}

	private function calculate_children_count($cat) {
		$c = 0;

		foreach ($cat['items'] as $child) {
			if ($child['type'] == 'category') {
				$c += $this->calculate_children_count($child);
			} else {
				$c += 1;
			}
		}

		return $c;
	}

	function getinactivefeeds() {
		if (DB_TYPE == "pgsql") {
			$interval_qpart = "NOW() - INTERVAL '3 months'";
		} else {
			$interval_qpart = "DATE_SUB(NOW(), INTERVAL 3 MONTH)";
		}

		$sth = $this->pdo->prepare("SELECT COUNT(id) AS num_inactive FROM ttrss_feeds WHERE
				(SELECT MAX(updated) FROM ttrss_entries, ttrss_user_entries WHERE
					ttrss_entries.id = ref_id AND
						ttrss_user_entries.feed_id = ttrss_feeds.id) < $interval_qpart AND
			  ttrss_feeds.owner_uid = ?");
		$sth->execute([$_SESSION['uid']]);

		if ($row = $sth->fetch()) {
			print (int)$row["num_inactive"];
		}
	}

	static function subscribe_to_feed_url() {
		$url_path = get_self_url_prefix() .
			"/public.php?op=subscribe&feed_url=%s";
		return $url_path;
	}

}
