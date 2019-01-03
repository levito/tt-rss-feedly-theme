<?php
class Pref_Labels extends Handler_Protected {

	function csrf_ignore($method) {
		$csrf_ignored = array("index", "getlabeltree", "edit");

		return array_search($method, $csrf_ignored) !== false;
	}

	function edit() {
		$label_id = clean($_REQUEST['id']);

		$sth = $this->pdo->prepare("SELECT * FROM ttrss_labels2 WHERE
			id = ? AND owner_uid = ?");
		$sth->execute([$label_id, $_SESSION['uid']]);

		if ($line = $sth->fetch()) {

			print_hidden("id", "$label_id");
			print_hidden("op", "pref-labels");
			print_hidden("method", "save");

			print "<form onsubmit='return false;'>";

			print "<div class=\"dlgSec\">".__("Caption")."</div>";

			print "<div class=\"dlgSecCont\">";

			$fg_color = $line['fg_color'];
			$bg_color = $line['bg_color'] ? $line['bg_color'] : '#fff7d5';

			print "<input style='font-size : 16px; color : $fg_color; background : $bg_color; transition : background 0.1s linear'
				id='labelEdit_caption' name='caption' dojoType='dijit.form.ValidationTextBox'
				required='true' value=\"".htmlspecialchars($line['caption'])."\">";

			print "</div>";
			print "<div class=\"dlgSec\">" . __("Colors") . "</div>";
			print "<div class=\"dlgSecCont\">";

			print "<table cellspacing=\"0\">";

			print "<tr><td>".__("Foreground:")."</td><td>".__("Background:").
				"</td></tr>";

			print "<tr><td style='padding-right : 10px'>";

			print "<input dojoType=\"dijit.form.TextBox\"
			style=\"display : none\" id=\"labelEdit_fgColor\"
			name=\"fg_color\" value=\"$fg_color\">";
			print "<input dojoType=\"dijit.form.TextBox\"
			style=\"display : none\" id=\"labelEdit_bgColor\"
			name=\"bg_color\" value=\"$bg_color\">";

			print "<div dojoType=\"dijit.ColorPalette\">
			<script type=\"dojo/method\" event=\"onChange\" args=\"fg_color\">
				dijit.byId('labelEdit_fgColor').attr('value', fg_color);
				dijit.byId('labelEdit_caption').domNode.setStyle({color: fg_color});
			</script>
			</div>";
			print "</div>";

			print "</td><td>";

			print "<div dojoType=\"dijit.ColorPalette\">
			<script type=\"dojo/method\" event=\"onChange\" args=\"bg_color\">
				dijit.byId('labelEdit_bgColor').attr('value', bg_color);
				dijit.byId('labelEdit_caption').domNode.setStyle({backgroundColor: bg_color});
			</script>
			</div>";
			print "</div>";

			print "</td></tr></table>";
			print "</div>";

#			print "</form>";

			print "<div class=\"dlgButtons\">";
			print "<button dojoType=\"dijit.form.Button\" type=\"submit\" class=\"alt-primary\" onclick=\"dijit.byId('labelEditDlg').execute()\">".
				__('Save')."</button>";
			print "<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('labelEditDlg').hide()\">".
				__('Cancel')."</button>";
			print "</div>";

			print "</form>";
		}
	}

	function getlabeltree() {
		$root = array();
		$root['id'] = 'root';
		$root['name'] = __('Labels');
		$root['items'] = array();

		$sth = $this->pdo->prepare("SELECT *
			FROM ttrss_labels2
			WHERE owner_uid = ?
			ORDER BY caption");
		$sth->execute([$_SESSION['uid']]);

		while ($line = $sth->fetch()) {
			$label = array();
			$label['id'] = 'LABEL:' . $line['id'];
			$label['bare_id'] = $line['id'];
			$label['name'] = $line['caption'];
			$label['fg_color'] = $line['fg_color'];
			$label['bg_color'] = $line['bg_color'];
			$label['type'] = 'label';
			$label['checkbox'] = false;

			array_push($root['items'], $label);
		}

		$fl = array();
		$fl['identifier'] = 'id';
		$fl['label'] = 'name';
		$fl['items'] = array($root);

		print json_encode($fl);
		return;
	}

	function colorset() {
		$kind = clean($_REQUEST["kind"]);
		$ids = explode(',', clean($_REQUEST["ids"]));
		$color = clean($_REQUEST["color"]);
		$fg = clean($_REQUEST["fg"]);
		$bg = clean($_REQUEST["bg"]);

		foreach ($ids as $id) {

			if ($kind == "fg" || $kind == "bg") {
				$sth = $this->pdo->prepare("UPDATE ttrss_labels2 SET
					${kind}_color = ? WHERE id = ?
					AND owner_uid = ?");

				$sth->execute([$color, $id, $_SESSION['uid']]);

			} else {

				$sth = $this->pdo->prepare("UPDATE ttrss_labels2 SET
					fg_color = ?, bg_color = ? WHERE id = ?
					AND owner_uid = ?");

				$sth->execute([$fg, $bg, $id, $_SESSION['uid']]);
			}

			/* Remove cached data */

			$sth = $this->pdo->prepare("UPDATE ttrss_user_entries SET label_cache = ''
				WHERE owner_uid = ?");
			$sth->execute([$_SESSION['uid']]);
		}
	}

	function colorreset() {
		$ids = explode(',', clean($_REQUEST["ids"]));

		foreach ($ids as $id) {
			$sth = $this->pdo->prepare("UPDATE ttrss_labels2 SET
				fg_color = '', bg_color = '' WHERE id = ?
				AND owner_uid = ?");
			$sth->execute([$id, $_SESSION['uid']]);

			/* Remove cached data */

			$sth = $this->pdo->prepare("UPDATE ttrss_user_entries SET label_cache = ''
				WHERE owner_uid = ?");
			$sth->execute([$_SESSION['uid']]);
		}
	}

	function save() {

		$id = clean($_REQUEST["id"]);
		$caption = trim(clean($_REQUEST["caption"]));

		$this->pdo->beginTransaction();

		$sth = $this->pdo->prepare("SELECT caption FROM ttrss_labels2
			WHERE id = ? AND owner_uid = ?");
		$sth->execute([$id, $_SESSION['uid']]);

		if ($row = $sth->fetch()) {
			$old_caption = $row["caption"];

			$sth = $this->pdo->prepare("SELECT id FROM ttrss_labels2
				WHERE caption = ? AND owner_uid = ?");
			$sth->execute([$caption, $_SESSION['uid']]);

			if (!$sth->fetch()) {
				if ($caption) {
					$sth = $this->pdo->prepare("UPDATE ttrss_labels2 SET
						caption = ? WHERE id = ? AND
						owner_uid = ?");
					$sth->execute([$caption, $id, $_SESSION['uid']]);

					/* Update filters that reference label being renamed */

					$sth = $this->pdo->prepare("UPDATE ttrss_filters2_actions SET
						action_param = ? WHERE action_param = ?
						AND action_id = 7
						AND filter_id IN (SELECT id FROM ttrss_filters2 WHERE owner_uid = ?)");

					$sth->execute([$caption, $old_caption, $_SESSION['uid']]);

					print clean($_REQUEST["value"]);
				} else {
					print $old_caption;
				}
			} else {
				print $old_caption;
			}
		}

		$this->pdo->commit();

	}

	function remove() {

		$ids = explode(",", clean($_REQUEST["ids"]));

		foreach ($ids as $id) {
			Labels::remove($id, $_SESSION["uid"]);
		}

	}

	function add() {
		$caption = clean($_REQUEST["caption"]);
		$output = clean($_REQUEST["output"]);

		if ($caption) {

			if (Labels::create($caption)) {
				if (!$output) {
					print T_sprintf("Created label <b>%s</b>", htmlspecialchars($caption));
				}
			}

			if ($output == "select") {
				header("Content-Type: text/xml");

				print "<rpc-reply><payload>";

				print_label_select("select_label",
					$caption, "");

				print "</payload></rpc-reply>";
			}
		}

		return;
	}

	function index() {

		print "<div dojoType='dijit.layout.BorderContainer' gutters='false'>";
		print "<div style='padding : 0px' dojoType='dijit.layout.ContentPane' region='top'>";
		print "<div dojoType='dijit.Toolbar'>";

		print "<div dojoType='dijit.form.DropDownButton'>".
				"<span>" . __('Select')."</span>";
		print "<div dojoType=\"dijit.Menu\" style=\"display: none;\">";
		print "<div onclick=\"dijit.byId('labelTree').model.setAllChecked(true)\"
			dojoType=\"dijit.MenuItem\">".__('All')."</div>";
		print "<div onclick=\"dijit.byId('labelTree').model.setAllChecked(false)\"
			dojoType=\"dijit.MenuItem\">".__('None')."</div>";
		print "</div></div>";

		print"<button dojoType=\"dijit.form.Button\" onclick=\"CommonDialogs.addLabel()\">".
			__('Create label')."</button dojoType=\"dijit.form.Button\"> ";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('labelTree').removeSelected()\">".
			__('Remove')."</button dojoType=\"dijit.form.Button\"> ";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('labelTree').resetColors()\">".
			__('Clear colors')."</button dojoType=\"dijit.form.Button\">";


		print "</div>"; #toolbar
		print "</div>"; #pane
		print "<div style='padding : 0px' dojoType=\"dijit.layout.ContentPane\" region=\"center\">";

		print "<div id=\"labellistLoading\">
		<img src='images/indicator_tiny.gif'>".
		 __("Loading, please wait...")."</div>";

		print "<div dojoType=\"dojo.data.ItemFileWriteStore\" jsId=\"labelStore\"
			url=\"backend.php?op=pref-labels&method=getlabeltree\">
		</div>
		<div dojoType=\"lib.CheckBoxStoreModel\" jsId=\"labelModel\" store=\"labelStore\"
		query=\"{id:'root'}\" rootId=\"root\"
			childrenAttrs=\"items\" checkboxStrict=\"false\" checkboxAll=\"false\">
		</div>
		<div dojoType=\"fox.PrefLabelTree\" id=\"labelTree\"
			model=\"labelModel\" openOnClick=\"true\">
		<script type=\"dojo/method\" event=\"onLoad\" args=\"item\">
			Element.hide(\"labellistLoading\");
		</script>
		<script type=\"dojo/method\" event=\"onClick\" args=\"item\">
			var id = String(item.id);
			var bare_id = id.substr(id.indexOf(':')+1);

			if (id.match('LABEL:')) {
				dijit.byId('labelTree').editLabel(bare_id);
			}
		</script>
		</div>";

		print "</div>"; #pane

		PluginHost::getInstance()->run_hooks(PluginHost::HOOK_PREFS_TAB,
			"hook_prefs_tab", "prefLabels");

		print "</div>"; #container

	}
}
