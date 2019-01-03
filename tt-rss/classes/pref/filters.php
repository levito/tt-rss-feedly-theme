<?php
class Pref_Filters extends Handler_Protected {

	function csrf_ignore($method) {
		$csrf_ignored = array("index", "getfiltertree", "edit", "newfilter", "newrule",
			"newaction", "savefilterorder");

		return array_search($method, $csrf_ignored) !== false;
	}

	function filtersortreset() {
		$sth = $this->pdo->prepare("UPDATE ttrss_filters2
				SET order_id = 0 WHERE owner_uid = ?");
		$sth->execute([$_SESSION['uid']]);
		return;
	}

	function savefilterorder() {
		$data = json_decode($_POST['payload'], true);

		#file_put_contents("/tmp/saveorder.json", clean($_POST['payload']));
		#$data = json_decode(file_get_contents("/tmp/saveorder.json"), true);

		if (!is_array($data['items']))
			$data['items'] = json_decode($data['items'], true);

		$index = 0;

		if (is_array($data) && is_array($data['items'])) {

			$sth = $this->pdo->prepare("UPDATE ttrss_filters2 SET
						order_id = ? WHERE id = ? AND
						owner_uid = ?");

			foreach ($data['items'][0]['items'] as $item) {
				$filter_id = (int) str_replace("FILTER:", "", $item['_reference']);

				if ($filter_id > 0) {
					$sth->execute([$index, $filter_id, $_SESSION['uid']]);
					++$index;
				}
			}
		}

		return;
	}

	function testFilterDo() {
		$offset = (int) clean($_REQUEST["offset"]);
		$limit = (int) clean($_REQUEST["limit"]);

		$filter = array();

		$filter["enabled"] = true;
		$filter["match_any_rule"] = checkbox_to_sql_bool(clean($_REQUEST["match_any_rule"]));
		$filter["inverse"] = checkbox_to_sql_bool(clean($_REQUEST["inverse"]));

		$filter["rules"] = array();
		$filter["actions"] = array("dummy-action");

		$res = $this->pdo->query("SELECT id,name FROM ttrss_filter_types");

		$filter_types = array();
		while ($line = $res->fetch()) {
			$filter_types[$line["id"]] = $line["name"];
		}

		$scope_qparts = array();

		$rctr = 0;
		foreach (clean($_REQUEST["rule"]) AS $r) {
			$rule = json_decode($r, true);

			if ($rule && $rctr < 5) {
				$rule["type"] = $filter_types[$rule["filter_type"]];
				unset($rule["filter_type"]);

				$scope_inner_qparts = [];
				foreach ($rule["feed_id"] as $feed_id) {

                    if (strpos($feed_id, "CAT:") === 0) {
                        $cat_id = (int) substr($feed_id, 4);
                        array_push($scope_inner_qparts, "cat_id = " . $this->pdo->quote($cat_id));
                    } else if ($feed_id > 0) {
                        array_push($scope_inner_qparts, "feed_id = " . $this->pdo->quote($feed_id));
                    }
                }

                if (count($scope_inner_qparts) > 0) {
				    array_push($scope_qparts, "(" . implode(" OR ", $scope_inner_qparts) . ")");
                }

				array_push($filter["rules"], $rule);

				++$rctr;
			} else {
				break;
			}
		}

		if (count($scope_qparts) == 0) $scope_qparts = ["true"];

		$glue = $filter['match_any_rule'] ? " OR " :  " AND ";
		$scope_qpart = join($glue, $scope_qparts);

		if (!$scope_qpart) $scope_qpart = "true";

		$rv = array();

		//while ($found < $limit && $offset < $limit * 1000 && time() - $started < ini_get("max_execution_time") * 0.7) {

		$sth = $this->pdo->prepare("SELECT ttrss_entries.id,
				ttrss_entries.title,
				ttrss_feeds.id AS feed_id,
				ttrss_feeds.title AS feed_title,
				ttrss_feed_categories.id AS cat_id,
				content,
				date_entered,
				link,
				author,
				tag_cache
			FROM
				ttrss_entries, ttrss_user_entries
					LEFT JOIN ttrss_feeds ON (feed_id = ttrss_feeds.id)
					LEFT JOIN ttrss_feed_categories ON (ttrss_feeds.cat_id = ttrss_feed_categories.id)
			WHERE
				ref_id = ttrss_entries.id AND
				($scope_qpart) AND
				ttrss_user_entries.owner_uid = ?
			ORDER BY date_entered DESC LIMIT $limit OFFSET $offset");

		$sth->execute([$_SESSION['uid']]);

		while ($line = $sth->fetch()) {

			$rc = RSSUtils::get_article_filters(array($filter), $line['title'], $line['content'], $line['link'],
				$line['author'], explode(",", $line['tag_cache']));

			if (count($rc) > 0) {

				$line["content_preview"] = truncate_string(strip_tags($line["content"]), 200, '&hellip;');

				foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_QUERY_HEADLINES) as $p) {
					$line = $p->hook_query_headlines($line, 100);
				}

				$content_preview = $line["content_preview"];

				$tmp = "<li><span class='title'>" . $line["title"] . "</span><br/>" .
					"<span class='feed'>" . $line['feed_title'] . "</span>, <span class='date'>" . mb_substr($line["date_entered"], 0, 16) . "</span>" .
					"<div class='preview insensitive'>" . $content_preview . "</div>" .
					"</li>";

				array_push($rv, $tmp);

			}
		}

		print json_encode($rv);
	}

	function testFilter() {

		if (isset($_REQUEST["offset"])) return $this->testFilterDo();

		//print __("Articles matching this filter:");

		print "<div><img id='prefFilterLoadingIndicator' src='images/indicator_tiny.gif'>&nbsp;<span id='prefFilterProgressMsg'>Looking for articles...</span></div>";

		print "<ul class='panel panel-scrollable list list-unstyled' id='prefFilterTestResultList'>";
		print "</ul>";

		print "<div style='text-align : center'>";
		print "<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('filterTestDlg').hide()\">".
			__('Close this window')."</button>";
		print "</div>";

	}

	private function getfilterrules_list($filter_id) {
		$sth = $this->pdo->prepare("SELECT reg_exp,
			inverse,
			match_on,
			feed_id,
			cat_id,
			cat_filter,
			ttrss_filter_types.description AS field
			FROM
				ttrss_filters2_rules, ttrss_filter_types
			WHERE
				filter_id = ? AND filter_type = ttrss_filter_types.id
			ORDER BY reg_exp");
		$sth->execute([$filter_id]);

		$rv = "";

		while ($line = $sth->fetch()) {

		    if ($line["match_on"]) {
		        $feeds = json_decode($line["match_on"], true);
		        $feeds_fmt = [];

                foreach ($feeds as $feed_id) {

                    if (strpos($feed_id, "CAT:") === 0) {
                        $feed_id = (int)substr($feed_id, 4);
                        array_push($feeds_fmt, Feeds::getCategoryTitle($feed_id));
                    } else {
                        if ($feed_id)
                            array_push($feeds_fmt, Feeds::getFeedTitle((int)$feed_id));
                        else
                            array_push($feeds_fmt, __("All feeds"));
                    }
                }

                $where = implode(", ", $feeds_fmt);

            } else {

                $where = $line["cat_filter"] ?
                    Feeds::getCategoryTitle($line["cat_id"]) :
                    ($line["feed_id"] ?
                        Feeds::getFeedTitle($line["feed_id"]) : __("All feeds"));
            }

#			$where = $line["cat_id"] . "/" . $line["feed_id"];

			$inverse = $line["inverse"] ? "inverse" : "";

			$rv .= "<li class='$inverse'>" . T_sprintf("%s on %s in %s %s",
				htmlspecialchars($line["reg_exp"]),
				$line["field"],
				$where,
				$line["inverse"] ? __("(inverse)") : "") . "</li>";
		}

		return $rv;
	}

	function getfiltertree() {
		$root = array();
		$root['id'] = 'root';
		$root['name'] = __('Filters');
		$root['items'] = array();

		$filter_search = $_SESSION["prefs_filter_search"];

		$sth = $this->pdo->prepare("SELECT *,
			(SELECT action_param FROM ttrss_filters2_actions
				WHERE filter_id = ttrss_filters2.id ORDER BY id LIMIT 1) AS action_param,
			(SELECT action_id FROM ttrss_filters2_actions
				WHERE filter_id = ttrss_filters2.id ORDER BY id LIMIT 1) AS action_id,
			(SELECT description FROM ttrss_filter_actions
				WHERE id = (SELECT action_id FROM ttrss_filters2_actions
					WHERE filter_id = ttrss_filters2.id ORDER BY id LIMIT 1)) AS action_name,
			(SELECT reg_exp FROM ttrss_filters2_rules
				WHERE filter_id = ttrss_filters2.id ORDER BY id LIMIT 1) AS reg_exp
			FROM ttrss_filters2 WHERE
			owner_uid = ? ORDER BY order_id, title");
		$sth->execute([$_SESSION['uid']]);

		$folder = array();
		$folder['items'] = array();

		while ($line = $sth->fetch()) {

			$name = $this->getFilterName($line["id"]);

			$match_ok = false;
			if ($filter_search) {
				if (mb_strpos($line['title'], $filter_search) !== false) {
					$match_ok = true;
				}

				$rules_sth = $this->pdo->prepare("SELECT reg_exp
					FROM ttrss_filters2_rules WHERE filter_id = ?");
				$rules_sth->execute([$line['id']]);

				while ($rule_line = $rules_sth->fetch()) {
					if (mb_strpos($rule_line['reg_exp'], $filter_search) !== false) {
						$match_ok = true;
						break;
					}
				}
			}

			if ($line['action_id'] == 7) {
				$label_sth = $this->pdo->prepare("SELECT fg_color, bg_color
					FROM ttrss_labels2 WHERE caption = ? AND
						owner_uid = ?");
				$label_sth->execute([$line['action_param'], $_SESSION['uid']]);

				if ($label_row = $label_sth->fetch()) {
					//$fg_color = $label_row["fg_color"];
					$bg_color = $label_row["bg_color"];

					$name[1] = "<i class=\"material-icons\" style='color : $bg_color; margin-right : 4px'>label</i>" . $name[1];
				}
			}

			$filter = array();
			$filter['id'] = 'FILTER:' . $line['id'];
			$filter['bare_id'] = $line['id'];
			$filter['name'] = $name[0];
			$filter['param'] = $name[1];
			$filter['checkbox'] = false;
			$filter['last_triggered'] = $line["last_triggered"] ? make_local_datetime($line["last_triggered"], false) : null;
			$filter['enabled'] = $line["enabled"];
			$filter['rules'] = $this->getfilterrules_list($line['id']);

			if (!$filter_search || $match_ok) {
				array_push($folder['items'], $filter);
			}
		}

		$root['items'] = $folder['items'];

		$fl = array();
		$fl['identifier'] = 'id';
		$fl['label'] = 'name';
		$fl['items'] = array($root);

		print json_encode($fl);
		return;
	}

	function edit() {

		$filter_id = clean($_REQUEST["id"]);

		$sth = $this->pdo->prepare("SELECT * FROM ttrss_filters2
			WHERE id = ? AND owner_uid = ?");
		$sth->execute([$filter_id, $_SESSION['uid']]);

		if ($row = $sth->fetch()) {

			$enabled = $row["enabled"];
			$match_any_rule = $row["match_any_rule"];
			$inverse = $row["inverse"];
			$title = htmlspecialchars($row["title"]);

			print "<form id=\"filter_edit_form\" onsubmit='return false'>";

			print_hidden("op", "pref-filters");
			print_hidden("id", "$filter_id");
			print_hidden("method", "editSave");
			print_hidden("csrf_token", $_SESSION['csrf_token']);

			print "<div class=\"dlgSecHoriz\">".__("Caption")."</div>";

			print "<input required=\"true\" dojoType=\"dijit.form.ValidationTextBox\" style=\"width : 20em;\" name=\"title\" value=\"$title\">";

			print "</div>";

			print "<div class=\"dlgSecHoriz\">".__("Match")."</div>";

			print "<div dojoType=\"dijit.Toolbar\">";

			print "<div dojoType=\"dijit.form.DropDownButton\">".
				"<span>" . __('Select')."</span>";
			print "<div dojoType=\"dijit.Menu\" style=\"display: none;\">";
			print "<div onclick=\"dijit.byId('filterEditDlg').selectRules(true)\"
			dojoType=\"dijit.MenuItem\">".__('All')."</div>";
			print "<div onclick=\"dijit.byId('filterEditDlg').selectRules(false)\"
			dojoType=\"dijit.MenuItem\">".__('None')."</div>";
			print "</div></div>";

			print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').addRule()\">".
				__('Add')."</button> ";

			print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').deleteRule()\">".
				__('Delete')."</button> ";

			print "</div>";

			print "<ul id='filterDlg_Matches'>";

			$rules_sth = $this->pdo->prepare("SELECT * FROM ttrss_filters2_rules
				  WHERE filter_id = ? ORDER BY reg_exp, id");
			$rules_sth->execute([$filter_id]);

			while ($line = $rules_sth->fetch()) {
				if ($line["match_on"]) {
					$line["feed_id"] = json_decode($line["match_on"], true);
				} else {
					if ($line["cat_filter"]) {
						$feed_id = "CAT:" . (int)$line["cat_id"];
					} else {
						$feed_id = (int)$line["feed_id"];
					}

					$line["feed_id"] = ["" . $feed_id]; // set item type to string for in_array()
				}

				unset($line["cat_filter"]);
				unset($line["cat_id"]);
				unset($line["filter_id"]);
				unset($line["id"]);
				if (!$line["inverse"]) unset($line["inverse"]);
				unset($line["match_on"]);

				$data = htmlspecialchars(json_encode($line));

				print "<li><input dojoType='dijit.form.CheckBox' type='checkbox' onclick='Lists.onRowChecked(this)'>".
					"<span onclick=\"dijit.byId('filterEditDlg').editRule(this)\">".$this->getRuleName($line)."</span>".
					"<input type='hidden' name='rule[]' value=\"$data\"/></li>";
			}

			print "</ul>";

			print "</div>";

			print "<div class=\"dlgSecHoriz\">".__("Apply actions")."</div>";

			print "<div dojoType=\"dijit.Toolbar\">";

			print "<div dojoType=\"dijit.form.DropDownButton\">".
				"<span>" . __('Select')."</span>";
			print "<div dojoType=\"dijit.Menu\" style=\"display: none;\">";
			print "<div onclick=\"dijit.byId('filterEditDlg').selectActions(true)\"
			dojoType=\"dijit.MenuItem\">".__('All')."</div>";
			print "<div onclick=\"dijit.byId('filterEditDlg').selectActions(false)\"
			dojoType=\"dijit.MenuItem\">".__('None')."</div>";
			print "</div></div>";

			print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').addAction()\">".
				__('Add')."</button> ";

			print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').deleteAction()\">".
				__('Delete')."</button> ";

			print "</div>";

			print "<ul id='filterDlg_Actions'>";

			$actions_sth = $this->pdo->prepare("SELECT * FROM ttrss_filters2_actions
				WHERE filter_id = ? ORDER BY id");
			$actions_sth->execute([$filter_id]);

			while ($line = $actions_sth->fetch()) {
				$line["action_param_label"] = $line["action_param"];

				unset($line["filter_id"]);
				unset($line["id"]);

				$data = htmlspecialchars(json_encode($line));

				print "<li><input dojoType='dijit.form.CheckBox' type='checkbox' onclick='Lists.onRowChecked(this)'>".
					"<span onclick=\"dijit.byId('filterEditDlg').editAction(this)\">".$this->getActionName($line)."</span>".
					"<input type='hidden' name='action[]' value=\"$data\"/></li>";
			}

			print "</ul>";

			print "</div>";

			if ($enabled) {
				$checked = "checked=\"1\"";
			} else {
				$checked = "";
			}

			print "<input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" name=\"enabled\" id=\"enabled\" $checked>
				<label for=\"enabled\">".__('Enabled')."</label>";

			if ($match_any_rule) {
				$checked = "checked=\"1\"";
			} else {
				$checked = "";
			}

			print "<br/><input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" name=\"match_any_rule\" id=\"match_any_rule\" $checked>
				<label for=\"match_any_rule\">".__('Match any rule')."</label>";

			if ($inverse) {
				$checked = "checked=\"1\"";
			} else {
				$checked = "";
			}

			print "<br/><input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" name=\"inverse\" id=\"inverse\" $checked>
				<label for=\"inverse\">".__('Inverse matching')."</label>";

			print "<p/>";

			print "<div class=\"dlgButtons\">";

			print "<div style=\"float : left\">";
			print "<button dojoType=\"dijit.form.Button\" class=\"alt-danger\" onclick=\"return dijit.byId('filterEditDlg').removeFilter()\">".
				__('Remove')."</button>";
			print "</div>";

			print "<button dojoType=\"dijit.form.Button\" class=\"alt-info\" onclick=\"return dijit.byId('filterEditDlg').test()\">".
				__('Test')."</button> ";

			print "<button dojoType=\"dijit.form.Button\" type=\"submit\" class=\"alt-primary\" onclick=\"return dijit.byId('filterEditDlg').execute()\">".
				__('Save')."</button> ";

			print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').hide()\">".
				__('Cancel')."</button>";

			print "</div>";
			print "</form>";

		}
	}

	private function getRuleName($rule) {
		if (!$rule) $rule = json_decode(clean($_REQUEST["rule"]), true);

		$feeds = $rule["feed_id"];
		$feeds_fmt = [];

		if (!is_array($feeds)) $feeds = [$feeds];

		foreach ($feeds as $feed_id) {

            if (strpos($feed_id, "CAT:") === 0) {
                $feed_id = (int)substr($feed_id, 4);
                array_push($feeds_fmt, Feeds::getCategoryTitle($feed_id));
            } else {
                if ($feed_id)
                    array_push($feeds_fmt, Feeds::getFeedTitle((int)$feed_id));
                else
                    array_push($feeds_fmt, __("All feeds"));
            }
        }

        $feed = implode(", ", $feeds_fmt);

		$sth = $this->pdo->prepare("SELECT description FROM ttrss_filter_types
			WHERE id = ?");
		$sth->execute([(int)$rule["filter_type"]]);

		if ($row = $sth->fetch()) {
			$filter_type = $row["description"];
		} else {
			$filter_type = "?UNKNOWN?";
		}

		$inverse = isset($rule["inverse"]) ? "inverse" : "";

		return "<span class='filterRule $inverse'>" .
			T_sprintf("%s on %s in %s %s", htmlspecialchars($rule["reg_exp"]),
			$filter_type, $feed, isset($rule["inverse"]) ? __("(inverse)") : "") . "</span>";
	}

	function printRuleName() {
		print $this->getRuleName(json_decode(clean($_REQUEST["rule"]), true));
	}

	private function getActionName($action) {
		$sth = $this->pdo->prepare("SELECT description FROM
			ttrss_filter_actions WHERE id = ?");
		$sth->execute([(int)$action["action_id"]]);

		$title = "";

		if ($row = $sth->fetch()) {

			$title = __($row["description"]);

			if ($action["action_id"] == 4 || $action["action_id"] == 6 ||
				$action["action_id"] == 7)
				$title .= ": " . $action["action_param"];

			if ($action["action_id"] == 9) {
				list ($pfclass, $pfaction) = explode(":", $action["action_param"]);

				$filter_actions = PluginHost::getInstance()->get_filter_actions();

				foreach ($filter_actions as $fclass => $factions) {
					foreach ($factions as $faction) {
						if ($pfaction == $faction["action"] && $pfclass == $fclass) {
							$title .= ": " . $fclass . ": " . $faction["description"];
							break;
						}
					}
				}
			}
		}

		return $title;
	}

	function printActionName() {
		print $this->getActionName(json_decode(clean($_REQUEST["action"]), true));
	}

	function editSave() {
		if (clean($_REQUEST["savemode"] && $_REQUEST["savemode"]) == "test") {
			return $this->testFilter();
		}

		$filter_id = clean($_REQUEST["id"]);
		$enabled = checkbox_to_sql_bool(clean($_REQUEST["enabled"]));
		$match_any_rule = checkbox_to_sql_bool(clean($_REQUEST["match_any_rule"]));
		$inverse = checkbox_to_sql_bool(clean($_REQUEST["inverse"]));
		$title = clean($_REQUEST["title"]);

		$this->pdo->beginTransaction();

		$sth = $this->pdo->prepare("UPDATE ttrss_filters2 SET enabled = ?,
			match_any_rule = ?,
			inverse = ?,
			title = ?
			WHERE id = ? AND owner_uid = ?");

		$sth->execute([$enabled, $match_any_rule, $inverse, $title, $filter_id, $_SESSION['uid']]);

		$this->saveRulesAndActions($filter_id);

		$this->pdo->commit();
	}

	function remove() {

		$ids = explode(",", clean($_REQUEST["ids"]));
		$ids_qmarks = arr_qmarks($ids);

		$sth = $this->pdo->prepare("DELETE FROM ttrss_filters2 WHERE id IN ($ids_qmarks)
			AND owner_uid = ?");
		$sth->execute(array_merge($ids, [$_SESSION['uid']]));
	}

	private function saveRulesAndActions($filter_id)
	{

		$sth = $this->pdo->prepare("DELETE FROM ttrss_filters2_rules WHERE filter_id = ?");
		$sth->execute([$filter_id]);

		$sth = $this->pdo->prepare("DELETE FROM ttrss_filters2_actions WHERE filter_id = ?");
		$sth->execute([$filter_id]);

		if (!is_array(clean($_REQUEST["rule"]))) $_REQUEST["rule"] = [];
		if (!is_array(clean($_REQUEST["action"]))) $_REQUEST["action"] = [];

		if ($filter_id) {
			/* create rules */

			$rules = array();
			$actions = array();

			foreach (clean($_REQUEST["rule"]) as $rule) {
				$rule = json_decode($rule, true);
				unset($rule["id"]);

				if (array_search($rule, $rules) === false) {
					array_push($rules, $rule);
				}
			}

			foreach (clean($_REQUEST["action"]) as $action) {
				$action = json_decode($action, true);
				unset($action["id"]);

				if (array_search($action, $actions) === false) {
					array_push($actions, $action);
				}
			}

			$rsth = $this->pdo->prepare("INSERT INTO ttrss_filters2_rules
						(filter_id, reg_exp,filter_type,feed_id,cat_id,match_on,inverse) VALUES
						(?, ?, ?, NULL, NULL, ?, ?)");

			foreach ($rules as $rule) {
				if ($rule) {

					$reg_exp = trim($rule["reg_exp"]);
					$inverse = isset($rule["inverse"]) ? 1 : 0;

					$filter_type = (int)trim($rule["filter_type"]);
					$match_on = json_encode($rule["feed_id"]);

					$rsth->execute([$filter_id, $reg_exp, $filter_type, $match_on, $inverse]);
				}
			}

			$asth = $this->pdo->prepare("INSERT INTO ttrss_filters2_actions
						(filter_id, action_id, action_param) VALUES
						(?, ?, ?)");

			foreach ($actions as $action) {
				if ($action) {

					$action_id = (int)$action["action_id"];
					$action_param = $action["action_param"];
					$action_param_label = $action["action_param_label"];

					if ($action_id == 7) {
						$action_param = $action_param_label;
					}

					if ($action_id == 6) {
						$action_param = (int)str_replace("+", "", $action_param);
					}

					$asth->execute([$filter_id, $action_id, $action_param]);
				}
			}
		}
	}

	function add() {
		if (clean($_REQUEST["savemode"] && $_REQUEST["savemode"]) == "test") {
			return $this->testFilter();
		}

		$enabled = checkbox_to_sql_bool(clean($_REQUEST["enabled"]));
		$match_any_rule = checkbox_to_sql_bool(clean($_REQUEST["match_any_rule"]));
		$title = clean($_REQUEST["title"]);
		$inverse = checkbox_to_sql_bool(clean($_REQUEST["inverse"]));

		$this->pdo->beginTransaction();

		/* create base filter */

		$sth = $this->pdo->prepare("INSERT INTO ttrss_filters2
			(owner_uid, match_any_rule, enabled, title, inverse) VALUES
			(?, ?, ?, ?, ?)");

		$sth->execute([$_SESSION['uid'], $match_any_rule, $enabled, $title, $inverse]);

		$sth = $this->pdo->prepare("SELECT MAX(id) AS id FROM ttrss_filters2
			WHERE owner_uid = ?");
		$sth->execute([$_SESSION['uid']]);

		if ($row = $sth->fetch()) {
			$filter_id = $row['id'];
			$this->saveRulesAndActions($filter_id);
		}

		$this->pdo->commit();
	}

	function index() {

		$filter_search = clean($_REQUEST["search"]);

		if (array_key_exists("search", $_REQUEST)) {
			$_SESSION["prefs_filter_search"] = $filter_search;
		} else {
			$filter_search = $_SESSION["prefs_filter_search"];
		}

		print "<div dojoType='dijit.layout.BorderContainer' gutters='false'>";
		print "<div style='padding : 0px' dojoType='dijit.layout.ContentPane' region='top'>";
		print "<div dojoType='dijit.Toolbar'>";

		if (array_key_exists("search", $_REQUEST)) {
			$_SESSION["prefs_filter_search"] = $filter_search;
		} else {
			$filter_search = $_SESSION["prefs_filter_search"];
		}

		print "<div style='float : right; padding-right : 4px;'>
			<input dojoType=\"dijit.form.TextBox\" id=\"filter_search\" size=\"20\" type=\"search\"
				value=\"$filter_search\">
			<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('filterTree').reload()\">".
				__('Search')."</button>
			</div>";

		print "<div dojoType=\"dijit.form.DropDownButton\">".
				"<span>" . __('Select')."</span>";
		print "<div dojoType=\"dijit.Menu\" style=\"display: none;\">";
		print "<div onclick=\"dijit.byId('filterTree').model.setAllChecked(true)\"
			dojoType=\"dijit.MenuItem\">".__('All')."</div>";
		print "<div onclick=\"dijit.byId('filterTree').model.setAllChecked(false)\"
			dojoType=\"dijit.MenuItem\">".__('None')."</div>";
		print "</div></div>";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return Filters.quickAddFilter()\">".
			__('Create filter')."</button> ";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterTree').joinSelectedFilters()\">".
			__('Combine')."</button> ";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterTree').editSelectedFilter()\">".
			__('Edit')."</button> ";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterTree').resetFilterOrder()\">".
			__('Reset sort order')."</button> ";


		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterTree').removeSelectedFilters()\">".
			__('Remove')."</button> ";

		print "</div>"; # toolbar
		print "</div>"; # toolbar-frame
		print "<div style='padding : 0px' dojoType='dijit.layout.ContentPane' region='center'>";

		print "<div id='filterlistLoading'>
		<img src='images/indicator_tiny.gif'>".
		 __("Loading, please wait...")."</div>";

		print "<div dojoType=\"fox.PrefFilterStore\" jsId=\"filterStore\"
			url=\"backend.php?op=pref-filters&method=getfiltertree\">
		</div>
		<div dojoType=\"lib.CheckBoxStoreModel\" jsId=\"filterModel\" store=\"filterStore\"
			query=\"{id:'root'}\" rootId=\"root\" rootLabel=\"Filters\"
			childrenAttrs=\"items\" checkboxStrict=\"false\" checkboxAll=\"false\">
		</div>
		<div dojoType=\"fox.PrefFilterTree\" id=\"filterTree\"
			dndController=\"dijit.tree.dndSource\"
			betweenThreshold=\"5\"
			model=\"filterModel\" openOnClick=\"true\">
		<script type=\"dojo/method\" event=\"onLoad\" args=\"item\">
			Element.hide(\"filterlistLoading\");
		</script>
		<script type=\"dojo/method\" event=\"onClick\" args=\"item\">
			var id = String(item.id);
			var bare_id = id.substr(id.indexOf(':')+1);

			if (id.match('FILTER:')) {
				dijit.byId('filterTree').editFilter(bare_id);
			}
		</script>

		</div>";

		print "</div>"; #pane

		PluginHost::getInstance()->run_hooks(PluginHost::HOOK_PREFS_TAB,
			"hook_prefs_tab", "prefFilters");

		print "</div>"; #container

	}

	function newfilter() {

		print "<form name='filter_new_form' id='filter_new_form' onsubmit='return false'>";

		print_hidden("op", "pref-filters");
		print_hidden("method", "add");
		print_hidden("csrf_token", $_SESSION['csrf_token']);

		print "<div class=\"dlgSecHoriz\">".__("Caption")."</div>";

		print "<input required=\"true\" dojoType=\"dijit.form.ValidationTextBox\" style=\"width : 20em;\" name=\"title\" value=\"\">";

		print "<div class=\"dlgSecHoriz\">".__("Match")."</div>";

		print "<div dojoType=\"dijit.Toolbar\">";

		print "<div dojoType=\"dijit.form.DropDownButton\">".
				"<span>" . __('Select')."</span>";
		print "<div dojoType=\"dijit.Menu\" style=\"display: none;\">";
		print "<div onclick=\"dijit.byId('filterEditDlg').selectRules(true)\"
			dojoType=\"dijit.MenuItem\">".__('All')."</div>";
		print "<div onclick=\"dijit.byId('filterEditDlg').selectRules(false)\"
			dojoType=\"dijit.MenuItem\">".__('None')."</div>";
		print "</div></div>";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').addRule()\">".
			__('Add')."</button> ";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').deleteRule()\">".
			__('Delete')."</button> ";

		print "</div>";

		print "<ul id='filterDlg_Matches'>";
#		print "<li>No rules</li>";
		print "</ul>";

		print "</div>";

		print "<div class=\"dlgSecHoriz\">".__("Apply actions")."</div>";

		print "<div dojoType=\"dijit.Toolbar\">";

		print "<div dojoType=\"dijit.form.DropDownButton\">".
				"<span>" . __('Select')."</span>";
		print "<div dojoType=\"dijit.Menu\" style=\"display: none;\">";
		print "<div onclick=\"dijit.byId('filterEditDlg').selectActions(true)\"
			dojoType=\"dijit.MenuItem\">".__('All')."</div>";
		print "<div onclick=\"dijit.byId('filterEditDlg').selectActions(false)\"
			dojoType=\"dijit.MenuItem\">".__('None')."</div>";
		print "</div></div>";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').addAction()\">".
			__('Add')."</button> ";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').deleteAction()\">".
			__('Delete')."</button> ";

		print "</div>";

		print "<ul id='filterDlg_Actions'>";
#		print "<li>No actions</li>";
		print "</ul>";

/*		print "<div class=\"dlgSec\">".__("Options")."</div>";
		print "<div class=\"dlgSecCont\">"; */

		print "<input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" name=\"enabled\" id=\"enabled\" checked=\"1\">
				<label for=\"enabled\">".__('Enabled')."</label>";

		print "<br/><input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" name=\"match_any_rule\" id=\"match_any_rule\">
				<label for=\"match_any_rule\">".__('Match any rule')."</label>";

		print "<br/><input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" name=\"inverse\" id=\"inverse\">
				<label for=\"inverse\">".__('Inverse matching')."</label>";

//		print "</div>";

		print "<div class=\"dlgButtons\">";

		print "<button dojoType=\"dijit.form.Button\" class=\"alt-info\" onclick=\"return dijit.byId('filterEditDlg').test()\">".
			__('Test')."</button> ";

		print "<button dojoType=\"dijit.form.Button\" type=\"submit\" class=\"alt-primary\" onclick=\"return dijit.byId('filterEditDlg').execute()\">".
			__('Create')."</button> ";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterEditDlg').hide()\">".
			__('Cancel')."</button>";

		print "</div>";

	}

	function newrule() {
		$rule = json_decode(clean($_REQUEST["rule"]), true);

		if ($rule) {
			$reg_exp = htmlspecialchars($rule["reg_exp"]);
			$filter_type = $rule["filter_type"];
			$feed_id = $rule["feed_id"];
			$inverse_checked = isset($rule["inverse"]) ? "checked" : "";
		} else {
			$reg_exp = "";
			$filter_type = 1;
			$feed_id = ["0"];
			$inverse_checked = "";
		}

		print "<form name='filter_new_rule_form' id='filter_new_rule_form' onsubmit='return false;'>";

		$res = $this->pdo->query("SELECT id,description
			FROM ttrss_filter_types WHERE id != 5 ORDER BY description");

		$filter_types = array();

		while ($line = $res->fetch()) {
			$filter_types[$line["id"]] = __($line["description"]);
		}

		print "<div class=\"dlgSec\">".__("Match")."</div>";

		print "<div class=\"dlgSecCont\">";

		print "<input dojoType=\"dijit.form.ValidationTextBox\"
			 required=\"true\" id=\"filterDlg_regExp\"
			 style=\"font-size : 16px; width : 20em;\"
			 name=\"reg_exp\" value=\"$reg_exp\"/>";

		print "<div dojoType=\"dijit.Tooltip\" connectId=\"filterDlg_regExp\" position=\"below\">
			".__("Regular expression, without outer delimiters (i.e. slashes)")."
		</div>";

		print "<hr/>";
		print "<input id=\"filterDlg_inverse\" dojoType=\"dijit.form.CheckBox\"
			 name=\"inverse\" $inverse_checked/>";
		print "<label for=\"filterDlg_inverse\">".__("Inverse regular expression matching")."</label>";

		print "<hr/>" .  __("on field") . " ";
		print_select_hash("filter_type", $filter_type, $filter_types,
			'dojoType="dijit.form.Select"');

		print "<hr/>";

		print __("in") . " ";

		print "<span id='filterDlg_feeds'>";
		print_feed_multi_select("feed_id",
			$feed_id,
			'dojoType="dijit.form.MultiSelect"');
		print "</span>";

		print "</div>";

		print "<div class=\"dlgButtons\">";

		print "<div style=\"float : left\">
			<a class=\"visibleLink\" target=\"_blank\" href=\"http://tt-rss.org/wiki/ContentFilters\">".__("Wiki: Filters")."</a>
		</div>";


		print "<button dojoType=\"dijit.form.Button\" class=\"alt-primary \" type=\"submit\" onclick=\"return dijit.byId('filterNewRuleDlg').execute()\">".
			($rule ? __("Save rule") : __('Add rule'))."</button> ";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterNewRuleDlg').hide()\">".
			__('Cancel')."</button>";

		print "</div>";

		print "</form>";
	}

	function newaction() {
		$action = json_decode(clean($_REQUEST["action"]), true);

		if ($action) {
			$action_param = $action["action_param"];
			$action_id = (int)$action["action_id"];
		} else {
			$action_param = "";
			$action_id = 0;
		}

		print "<form name='filter_new_action_form' id='filter_new_action_form' onsubmit='return false;'>";

		print "<div class=\"dlgSec\">".__("Perform Action")."</div>";

		print "<div class=\"dlgSecCont\">";

		print "<select name=\"action_id\" dojoType=\"dijit.form.Select\"
			onchange=\"Filters.filterDlgCheckAction(this)\">";

		$res = $this->pdo->query("SELECT id,description FROM ttrss_filter_actions
			ORDER BY name");

		while ($line = $res->fetch()) {
			$is_selected = ($line["id"] == $action_id) ? "selected='1'" : "";
			printf("<option $is_selected value='%d'>%s</option>", $line["id"], __($line["description"]));
		}

		print "</select>";

		$param_box_hidden = ($action_id == 7 || $action_id == 4 || $action_id == 6 || $action_id == 9) ?
			"" : "display : none";

		$param_hidden = ($action_id == 4 || $action_id == 6) ?
			"" : "display : none";

		$label_param_hidden = ($action_id == 7) ?	"" : "display : none";
		$plugin_param_hidden = ($action_id == 9) ?	"" : "display : none";

		print "<span id=\"filterDlg_paramBox\" style=\"$param_box_hidden\">";
		print " ";
		//print " " . __("with parameters:") . " ";
		print "<input dojoType=\"dijit.form.TextBox\"
			id=\"filterDlg_actionParam\" style=\"$param_hidden\"
			name=\"action_param\" value=\"$action_param\">";

		print_label_select("action_param_label", $action_param,
			"id=\"filterDlg_actionParamLabel\" style=\"$label_param_hidden\"
			dojoType=\"dijit.form.Select\"");

		$filter_actions = PluginHost::getInstance()->get_filter_actions();
		$filter_action_hash = array();

		foreach ($filter_actions as $fclass => $factions) {
			foreach ($factions as $faction) {

				$filter_action_hash[$fclass . ":" . $faction["action"]] =
					$fclass . ": " . $faction["description"];
			}
		}

		if (count($filter_action_hash) == 0) {
			$filter_plugin_disabled = "disabled";

			$filter_action_hash["no-data"] = __("No actions available");

		} else {
			$filter_plugin_disabled = "";
		}

		print_select_hash("filterDlg_actionParamPlugin", $action_param, $filter_action_hash,
			"style=\"$plugin_param_hidden\" dojoType=\"dijit.form.Select\" $filter_plugin_disabled",
			"action_param_plugin");

		print "</span>";

		print "&nbsp;"; // tiny layout hack

		print "</div>";

		print "<div class=\"dlgButtons\">";

		print "<button dojoType=\"dijit.form.Button\" class=\"alt-primary\" type=\"submit\" onclick=\"return dijit.byId('filterNewActionDlg').execute()\">".
			($action ? __("Save action") : __('Add action'))."</button> ";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('filterNewActionDlg').hide()\">".
			__('Cancel')."</button>";

		print "</div>";

		print "</form>";
	}

	private function getFilterName($id) {

		$sth = $this->pdo->prepare(
			"SELECT title,match_any_rule,COUNT(DISTINCT r.id) AS num_rules,COUNT(DISTINCT a.id) AS num_actions
				FROM ttrss_filters2 AS f LEFT JOIN ttrss_filters2_rules AS r
					ON (r.filter_id = f.id)
						LEFT JOIN ttrss_filters2_actions AS a
							ON (a.filter_id = f.id) WHERE f.id = ? GROUP BY f.title, f.match_any_rule");
		$sth->execute([$id]);

		if ($row = $sth->fetch()) {

			$title = $row["title"];
			$num_rules = $row["num_rules"];
			$num_actions = $row["num_actions"];
			$match_any_rule = $row["match_any_rule"];

			if (!$title) $title = __("[No caption]");

			$title = sprintf(_ngettext("%s (%d rule)", "%s (%d rules)", (int) $num_rules), $title, $num_rules);

			$sth = $this->pdo->prepare("SELECT * FROM ttrss_filters2_actions
				WHERE filter_id = ? ORDER BY id LIMIT 1");
			$sth->execute([$id]);

			$actions = "";

			if ($line = $sth->fetch()) {
				$actions = $this->getActionName($line);

				$num_actions -= 1;
			}

			if ($match_any_rule) $title .= " (" . __("matches any rule") . ")";

			if ($num_actions > 0)
				$actions = sprintf(_ngettext("%s (+%d action)", "%s (+%d actions)", (int) $num_actions), $actions, $num_actions);

			return [$title, $actions];
		}

		return [];
	}

	function join() {
		$ids = explode(",", clean($_REQUEST["ids"]));

		if (count($ids) > 1) {
			$base_id = array_shift($ids);
			$ids_qmarks = arr_qmarks($ids);

			$this->pdo->beginTransaction();

			$sth = $this->pdo->prepare("UPDATE ttrss_filters2_rules
				SET filter_id = ? WHERE filter_id IN ($ids_qmarks)");
			$sth->execute(array_merge([$base_id], $ids));

			$sth = $this->pdo->prepare("UPDATE ttrss_filters2_actions
				SET filter_id = ? WHERE filter_id IN ($ids_qmarks)");
			$sth->execute(array_merge([$base_id], $ids));

			$sth = $this->pdo->prepare("DELETE FROM ttrss_filters2 WHERE id IN ($ids_qmarks)");
			$sth->execute($ids);

			$sth = $this->pdo->prepare("UPDATE ttrss_filters2 SET match_any_rule = true WHERE id = ?");
			$sth->execute([$base_id]);

			$this->pdo->commit();

			$this->optimizeFilter($base_id);

		}
	}

	private function optimizeFilter($id) {

		$this->pdo->beginTransaction();

		$sth = $this->pdo->prepare("SELECT * FROM ttrss_filters2_actions
			WHERE filter_id = ?");
		$sth->execute([$id]);

		$tmp = array();
		$dupe_ids = array();

		while ($line = $sth->fetch()) {
			$id = $line["id"];
			unset($line["id"]);

			if (array_search($line, $tmp) === false) {
				array_push($tmp, $line);
			} else {
				array_push($dupe_ids, $id);
			}
		}

		if (count($dupe_ids) > 0) {
			$ids_str = join(",", $dupe_ids);

			$this->pdo->query("DELETE FROM ttrss_filters2_actions WHERE id IN ($ids_str)");
		}

		$sth = $this->pdo->prepare("SELECT * FROM ttrss_filters2_rules
			WHERE filter_id = ?");
		$sth->execute([$id]);

		$tmp = array();
		$dupe_ids = array();

		while ($line = $sth->fetch()) {
			$id = $line["id"];
			unset($line["id"]);

			if (array_search($line, $tmp) === false) {
				array_push($tmp, $line);
			} else {
				array_push($dupe_ids, $id);
			}
		}

		if (count($dupe_ids) > 0) {
			$ids_str = join(",", $dupe_ids);

			$this->pdo->query("DELETE FROM ttrss_filters2_rules WHERE id IN ($ids_str)");
		}

		$this->pdo->commit();
	}
}
