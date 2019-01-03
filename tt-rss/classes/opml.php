<?php
class Opml extends Handler_Protected {

	function csrf_ignore($method) {
		$csrf_ignored = array("export", "import");

		return array_search($method, $csrf_ignored) !== false;
	}

	function export() {
		$output_name = "tt-rss_".date("Y-m-d").".opml";
		$show_settings = $_REQUEST["include_settings"];
		$owner_uid = $_SESSION["uid"];

		$rc = $this->opml_export($output_name, $owner_uid, false, ($show_settings == 1));

		return $rc;
	}

	function import() {
		$owner_uid = $_SESSION["uid"];

		header('Content-Type: text/html; charset=utf-8');

		print "<html>
			<head>
				".stylesheet_tag("css/default.css")."
				<title>".__("OPML Utility")."</title>
				<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\"/>
			</head>
			<body class='claro ttrss_utility'>
			<div class=\"floatingLogo\"><img src=\"images/logo_small.png\"></div>
			<h1>".__('OPML Utility')."</h1><div class='content'>";

		add_feed_category("Imported feeds");

		$this->opml_notice(__("Importing OPML..."));

		$this->opml_import($owner_uid);

		print "<br><form method=\"GET\" action=\"prefs.php\">
			<input type=\"submit\" value=\"".__("Return to preferences")."\">
			</form>";

		print "</div></body></html>";


	}

	// Export

	private function opml_export_category($owner_uid, $cat_id, $hide_private_feeds=false) {

		$cat_id = (int) $cat_id;

		if ($hide_private_feeds)
			$hide_qpart = "(private IS false AND auth_login = '' AND auth_pass = '')";
		else
			$hide_qpart = "true";

		$out = "";

		if ($cat_id) {
			$sth = $this->pdo->prepare("SELECT title FROM ttrss_feed_categories WHERE id = ?
				AND owner_uid = ?");
			$sth->execute([$cat_id, $owner_uid]);
			$row = $sth->fetch();
			$cat_title = htmlspecialchars($row['title']);
		}

		if ($cat_title) $out .= "<outline text=\"$cat_title\">\n";

		$sth = $this->pdo->prepare("SELECT id,title
			FROM ttrss_feed_categories WHERE
				(parent_cat = :cat OR (:cat = 0 AND parent_cat IS NULL)) AND
				owner_uid = :uid ORDER BY order_id, title");

		$sth->execute([':cat' => $cat_id, ':uid' => $owner_uid]);

		while ($line = $sth->fetch()) {
			$out .= $this->opml_export_category($owner_uid, $line["id"], $hide_private_feeds);
		}

		$fsth = $this->pdo->prepare("select title, feed_url, site_url
				FROM ttrss_feeds WHERE
					(cat_id = :cat OR (:cat = 0 AND cat_id IS NULL)) AND owner_uid = :uid AND $hide_qpart
				ORDER BY order_id, title");

		$fsth->execute([':cat' => $cat_id, ':uid' => $owner_uid]);

		while ($fline = $fsth->fetch()) {
			$title = htmlspecialchars($fline["title"]);
			$url = htmlspecialchars($fline["feed_url"]);
			$site_url = htmlspecialchars($fline["site_url"]);

			if ($site_url) {
				$html_url_qpart = "htmlUrl=\"$site_url\"";
			} else {
				$html_url_qpart = "";
			}

			$out .= "<outline type=\"rss\" text=\"$title\" xmlUrl=\"$url\" $html_url_qpart/>\n";
		}

		if ($cat_title) $out .= "</outline>\n";

		return $out;
	}

	function opml_export($name, $owner_uid, $hide_private_feeds=false, $include_settings=true) {
		if (!$owner_uid) return;

		if (!isset($_REQUEST["debug"])) {
			header("Content-type: application/xml+opml");
			header("Content-Disposition: attachment; filename=" . $name );
		} else {
			header("Content-type: text/xml");
		}

		$out = "<?xml version=\"1.0\" encoding=\"utf-8\"?".">";

		$out .= "<opml version=\"1.0\">";
		$out .= "<head>
			<dateCreated>" . date("r", time()) . "</dateCreated>
			<title>Tiny Tiny RSS Feed Export</title>
		</head>";
		$out .= "<body>";

		$out .= $this->opml_export_category($owner_uid, 0, $hide_private_feeds);

		# export tt-rss settings

		if ($include_settings) {
			$out .= "<outline text=\"tt-rss-prefs\" schema-version=\"".SCHEMA_VERSION."\">";

			$sth = $this->pdo->prepare("SELECT pref_name, value FROM ttrss_user_prefs WHERE
			   profile IS NULL AND owner_uid = ? ORDER BY pref_name");
			$sth->execute([$owner_uid]);

			while ($line = $sth->fetch()) {
				$name = $line["pref_name"];
				$value = htmlspecialchars($line["value"]);

				$out .= "<outline pref-name=\"$name\" value=\"$value\"/>";
			}

			$out .= "</outline>";

			$out .= "<outline text=\"tt-rss-labels\" schema-version=\"".SCHEMA_VERSION."\">";

			$sth = $this->pdo->prepare("SELECT * FROM ttrss_labels2 WHERE
				owner_uid = ?");
			$sth->execute([$owner_uid]);

			while ($line = $sth->fetch()) {
				$name = htmlspecialchars($line['caption']);
				$fg_color = htmlspecialchars($line['fg_color']);
				$bg_color = htmlspecialchars($line['bg_color']);

				$out .= "<outline label-name=\"$name\" label-fg-color=\"$fg_color\" label-bg-color=\"$bg_color\"/>";

			}

			$out .= "</outline>";

			$out .= "<outline text=\"tt-rss-filters\" schema-version=\"".SCHEMA_VERSION."\">";

			$sth = $this->pdo->prepare("SELECT * FROM ttrss_filters2
				WHERE owner_uid = ? ORDER BY id");
			$sth->execute([$owner_uid]);

			while ($line = $sth->fetch()) {
				$line["rules"] = array();
				$line["actions"] = array();

				$tmph = $this->pdo->prepare("SELECT * FROM ttrss_filters2_rules
					WHERE filter_id = ?");
				$tmph->execute([$line['id']]);

				while ($tmp_line = $tmph->fetch()) {
					unset($tmp_line["id"]);
					unset($tmp_line["filter_id"]);

					$cat_filter = $tmp_line["cat_filter"];

					if (!$tmp_line["match_on"]) {
                        if ($cat_filter && $tmp_line["cat_id"] || $tmp_line["feed_id"]) {
                            $tmp_line["feed"] = Feeds::getFeedTitle(
                                $cat_filter ? $tmp_line["cat_id"] : $tmp_line["feed_id"],
                                $cat_filter);
                        } else {
                            $tmp_line["feed"] = "";
                        }
                    } else {
					    $match = [];
					    foreach (json_decode($tmp_line["match_on"], true) as $feed_id) {

                            if (strpos($feed_id, "CAT:") === 0) {
                                $feed_id = (int)substr($feed_id, 4);
                                if ($feed_id) {
                                    array_push($match, [Feeds::getCategoryTitle($feed_id), true, false]);
                                } else {
                                    array_push($match, [0, true, true]);
                                }
                            } else {
                                if ($feed_id) {
                                    array_push($match, [Feeds::getFeedTitle((int)$feed_id), false, false]);
                                } else {
                                    array_push($match, [0, false, true]);
                                }
                            }
                        }

                        $tmp_line["match"] = $match;
					    unset($tmp_line["match_on"]);
                    }

					unset($tmp_line["feed_id"]);
					unset($tmp_line["cat_id"]);

					array_push($line["rules"], $tmp_line);
				}

				$tmph = $this->pdo->prepare("SELECT * FROM ttrss_filters2_actions
					WHERE filter_id = ?");
				$tmph->execute([$line['id']]);

				while ($tmp_line = $tmph->fetch()) {
					unset($tmp_line["id"]);
					unset($tmp_line["filter_id"]);

					array_push($line["actions"], $tmp_line);
				}

				unset($line["id"]);
				unset($line["owner_uid"]);
				$filter = json_encode($line);

				$out .= "<outline filter-type=\"2\"><![CDATA[$filter]]></outline>";

			}


			$out .= "</outline>";
		}

		$out .= "</body></opml>";

		// Format output.
		$doc = new DOMDocument();
		$doc->formatOutput = true;
		$doc->preserveWhiteSpace = false;
		$doc->loadXML($out);

		$xpath = new DOMXpath($doc);
		$outlines = $xpath->query("//outline[@title]");

		// cleanup empty categories
		foreach ($outlines as $node) {
			if ($node->getElementsByTagName('outline')->length == 0)
				$node->parentNode->removeChild($node);
		}

		$res = $doc->saveXML();

/*		// saveXML uses a two-space indent.  Change to tabs.
		$res = preg_replace_callback('/^(?:  )+/mu',
			create_function(
				'$matches',
				'return str_repeat("\t", intval(strlen($matches[0])/2));'),
			$res); */

		print $res;
	}

	// Import

	private function opml_import_feed($node, $cat_id, $owner_uid) {
		$attrs = $node->attributes;

		$feed_title = mb_substr($attrs->getNamedItem('text')->nodeValue, 0, 250);
		if (!$feed_title) $feed_title = mb_substr($attrs->getNamedItem('title')->nodeValue, 0, 250);

		$feed_url = $attrs->getNamedItem('xmlUrl')->nodeValue;
		if (!$feed_url) $feed_url = $attrs->getNamedItem('xmlURL')->nodeValue;

		$site_url = mb_substr($attrs->getNamedItem('htmlUrl')->nodeValue, 0, 250);

		if ($feed_url) {
			$sth = $this->pdo->prepare("SELECT id FROM ttrss_feeds WHERE
				feed_url = ? AND owner_uid = ?");
			$sth->execute([$feed_url, $owner_uid]);

			if (!$feed_title) $feed_title = '[Unknown]';

			if (!$sth->fetch()) {
				#$this->opml_notice("[FEED] [$feed_title/$feed_url] dst_CAT=$cat_id");
				$this->opml_notice(T_sprintf("Adding feed: %s", $feed_title == '[Unknown]' ? $feed_url : $feed_title));

				if (!$cat_id) $cat_id = null;

				$sth = $this->pdo->prepare("INSERT INTO ttrss_feeds
					(title, feed_url, owner_uid, cat_id, site_url, order_id) VALUES
					(?, ?, ?, ?, ?, 0)");

				$sth->execute([$feed_title, $feed_url, $owner_uid, $cat_id, $site_url]);

			} else {
				$this->opml_notice(T_sprintf("Duplicate feed: %s", $feed_title == '[Unknown]' ? $feed_url : $feed_title));
			}
		}
	}

	private function opml_import_label($node, $owner_uid) {
		$attrs = $node->attributes;
		$label_name = $attrs->getNamedItem('label-name')->nodeValue;

		if ($label_name) {
			$fg_color = $attrs->getNamedItem('label-fg-color')->nodeValue;
			$bg_color = $attrs->getNamedItem('label-bg-color')->nodeValue;

			if (!Labels::find_id($label_name, $_SESSION['uid'])) {
				$this->opml_notice(T_sprintf("Adding label %s", htmlspecialchars($label_name)));
				Labels::create($label_name, $fg_color, $bg_color, $owner_uid);
			} else {
				$this->opml_notice(T_sprintf("Duplicate label: %s", htmlspecialchars($label_name)));
			}
		}
	}

	private function opml_import_preference($node) {
		$attrs = $node->attributes;
		$pref_name = $attrs->getNamedItem('pref-name')->nodeValue;

		if ($pref_name) {
			$pref_value = $attrs->getNamedItem('value')->nodeValue;

			$this->opml_notice(T_sprintf("Setting preference key %s to %s",
				$pref_name, $pref_value));

			set_pref($pref_name, $pref_value);
		}
	}

	private function opml_import_filter($node) {
		$attrs = $node->attributes;

		$filter_type = $attrs->getNamedItem('filter-type')->nodeValue;

		if ($filter_type == '2') {
			$filter = json_decode($node->nodeValue, true);

			if ($filter) {
				$match_any_rule = bool_to_sql_bool($filter["match_any_rule"]);
				$enabled = bool_to_sql_bool($filter["enabled"]);
				$inverse = bool_to_sql_bool($filter["inverse"]);
				$title = $filter["title"];

				//print "F: $title, $inverse, $enabled, $match_any_rule";

				$sth = $this->pdo->prepare("INSERT INTO ttrss_filters2 (match_any_rule,enabled,inverse,title,owner_uid)
					VALUES (?, ?, ?, ?, ?)");

				$sth->execute([$match_any_rule, $enabled, $inverse, $title, $_SESSION['uid']]);

				$sth = $this->pdo->prepare("SELECT MAX(id) AS id FROM ttrss_filters2 WHERE
					owner_uid = ?");
				$sth->execute([$_SESSION['uid']]);

				$row = $sth->fetch();
				$filter_id = $row['id'];

				if ($filter_id) {
					$this->opml_notice(T_sprintf("Adding filter..."));

					foreach ($filter["rules"] as $rule) {
						$feed_id = null;
						$cat_id = null;

						if ($rule["match"]) {

                            $match_on = [];

						    foreach ($rule["match"] as $match) {
						        list ($name, $is_cat, $is_id) = $match;

						        if ($is_id) {
						            array_push($match_on, ($is_cat ? "CAT:" : "") . $name);
                                } else {

						            $match_id = false;

                                    if (!$is_cat) {
                                        $tsth = $this->pdo->prepare("SELECT id FROM ttrss_feeds
                                    		WHERE title = ? AND owner_uid = ?");

                                        $tsth->execute([$name, $_SESSION['uid']]);

                                        if ($row = $tsth->fetch()) {
                                            $match_id = $row['id'];
                                        }
                                    } else {
                                        $tsth = $this->pdo->prepare("SELECT id FROM ttrss_feed_categories
                                    		WHERE title = ? AND owner_uid = ?");
										$tsth->execute([$name, $_SESSION['uid']]);

										if ($row = $tsth->fetch()) {
											$match_id = $row['id'];
										}
                                    }

                                    if ($match_id) array_push($match_on, $match_id);
                                }
                            }

                            $reg_exp = $rule["reg_exp"];
                            $filter_type = (int)$rule["filter_type"];
                            $inverse = bool_to_sql_bool($rule["inverse"]);
                            $match_on = json_encode($match_on);

                            $usth = $this->pdo->prepare("INSERT INTO ttrss_filters2_rules
								(feed_id,cat_id,match_on,filter_id,filter_type,reg_exp,cat_filter,inverse)
                                VALUES
                                (NULL, NULL, ?, ?, ?, ?, false, ?)");
                            $usth->execute([$match_on, $filter_id, $filter_type, $reg_exp, $inverse]);

                        } else {

                            if (!$rule["cat_filter"]) {
                                $tsth = $this->pdo->prepare("SELECT id FROM ttrss_feeds
                                    WHERE title = ? AND owner_uid = ?");

                                $tsth->execute([$rule['feed'], $_SESSION['uid']]);

                                if ($row = $tsth->fetch()) {
                                    $feed_id = $row['id'];
                                }
                            } else {
								$tsth = $this->pdo->prepare("SELECT id FROM ttrss_feed_categories
                                    WHERE title = ? AND owner_uid = ?");

								$tsth->execute([$rule['feed'], $_SESSION['uid']]);

								if ($row = $tsth->fetch()) {
									$feed_id = $row['id'];
								}
                            }

                            $cat_filter = bool_to_sql_bool($rule["cat_filter"]);
                            $reg_exp = $rule["reg_exp"];
                            $filter_type = (int)$rule["filter_type"];
                            $inverse = bool_to_sql_bool($rule["inverse"]);

                            $usth = $this->pdo->prepare("INSERT INTO ttrss_filters2_rules
								(feed_id,cat_id,filter_id,filter_type,reg_exp,cat_filter,inverse)
                                VALUES
                                (?, ?, ?, ?, ?, ?, ?)");
                            $usth->execute([$feed_id, $cat_id, $filter_id, $filter_type, $reg_exp, $cat_filter, $inverse]);
                        }
					}

					foreach ($filter["actions"] as $action) {

						$action_id = (int)$action["action_id"];
						$action_param = $action["action_param"];

						$usth = $this->pdo->prepare("INSERT INTO ttrss_filters2_actions
							(filter_id,action_id,action_param)
							VALUES
							(?, ?, ?)");
						$usth->execute([$filter_id, $action_id, $action_param]);
					}
				}
			}
		}
	}

	private function opml_import_category($doc, $root_node, $owner_uid, $parent_id) {
		$default_cat_id = (int) $this->get_feed_category('Imported feeds', false);

		if ($root_node) {
			$cat_title = mb_substr($root_node->attributes->getNamedItem('text')->nodeValue, 0, 250);

			if (!$cat_title)
				$cat_title = mb_substr($root_node->attributes->getNamedItem('title')->nodeValue, 0, 250);

			if (!in_array($cat_title, array("tt-rss-filters", "tt-rss-labels", "tt-rss-prefs"))) {
				$cat_id = $this->get_feed_category($cat_title, $parent_id);

				if ($cat_id === false) {
					add_feed_category($cat_title, $parent_id);
					$cat_id = $this->get_feed_category($cat_title, $parent_id);
				}

			} else {
				$cat_id = 0;
			}

			$outlines = $root_node->childNodes;

		} else {
			$xpath = new DOMXpath($doc);
			$outlines = $xpath->query("//opml/body/outline");

			$cat_id = 0;
		}

		#$this->opml_notice("[CAT] $cat_title id: $cat_id P_id: $parent_id");
		$this->opml_notice(T_sprintf("Processing category: %s", $cat_title ? $cat_title : __("Uncategorized")));

		foreach ($outlines as $node) {
			if ($node->hasAttributes() && strtolower($node->tagName) == "outline") {
				$attrs = $node->attributes;
				$node_cat_title = $attrs->getNamedItem('text')->nodeValue;

				if (!$node_cat_title)
					$node_cat_title = $attrs->getNamedItem('title')->nodeValue;

				$node_feed_url = $attrs->getNamedItem('xmlUrl')->nodeValue;

				if ($node_cat_title && !$node_feed_url) {
					$this->opml_import_category($doc, $node, $owner_uid, $cat_id);
				} else {

					if (!$cat_id) {
						$dst_cat_id = $default_cat_id;
					} else {
						$dst_cat_id = $cat_id;
					}

					switch ($cat_title) {
					case "tt-rss-prefs":
						$this->opml_import_preference($node);
						break;
					case "tt-rss-labels":
						$this->opml_import_label($node, $owner_uid);
						break;
					case "tt-rss-filters":
						$this->opml_import_filter($node);
						break;
					default:
						$this->opml_import_feed($node, $dst_cat_id, $owner_uid);
					}
				}
			}
		}
	}

	function opml_import($owner_uid) {
		if (!$owner_uid) return;

		$doc = false;

		if ($_FILES['opml_file']['error'] != 0) {
			print_error(T_sprintf("Upload failed with error code %d",
				$_FILES['opml_file']['error']));
			return;
		}

		if (is_uploaded_file($_FILES['opml_file']['tmp_name'])) {
			$tmp_file = tempnam(CACHE_DIR . '/upload', 'opml');

			$result = move_uploaded_file($_FILES['opml_file']['tmp_name'],
				$tmp_file);

			if (!$result) {
				print_error(__("Unable to move uploaded file."));
				return;
			}
		} else {
			print_error(__('Error: please upload OPML file.'));
			return;
		}

		if (is_file($tmp_file)) {
			$doc = new DOMDocument();
			libxml_disable_entity_loader(false);
			$doc->load($tmp_file);
			libxml_disable_entity_loader(true);
			unlink($tmp_file);
		} else if (!$doc) {
			print_error(__('Error: unable to find moved OPML file.'));
			return;
		}

		if ($doc) {
			$this->pdo->beginTransaction();
			$this->opml_import_category($doc, false, $owner_uid, false);
			$this->pdo->commit();
		} else {
			print_error(__('Error while parsing document.'));
		}
	}

	private function opml_notice($msg) {
		print "$msg<br/>";
	}

	static function opml_publish_url(){

		$url_path = get_self_url_prefix();
		$url_path .= "/opml.php?op=publish&key=" .
			get_feed_access_key('OPML:Publish', false, $_SESSION["uid"]);

		return $url_path;
	}

	function get_feed_category($feed_cat, $parent_cat_id = false) {

		$parent_cat_id = (int) $parent_cat_id;

		$sth = $this->pdo->prepare("SELECT id FROM ttrss_feed_categories
			WHERE title = :title
			AND (parent_cat = :parent OR (:parent = 0 AND parent_cat IS NULL))
			AND owner_uid = :uid");

		$sth->execute([':title' => $feed_cat, ':parent' => $parent_cat_id, ':uid' => $_SESSION['uid']]);

		if ($row = $sth->fetch()) {
			return $row['id'];
		} else {
			return false;
		}
	}


}
