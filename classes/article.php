<?php
class Article extends Handler_Protected {

	function csrf_ignore($method) {
		$csrf_ignored = array("redirect", "editarticletags");

		return array_search($method, $csrf_ignored) !== false;
	}

	function redirect() {
		$id = clean($_REQUEST['id']);

		$sth = $this->pdo->prepare("SELECT link FROM ttrss_entries, ttrss_user_entries
						WHERE id = ? AND id = ref_id AND owner_uid = ?
						LIMIT 1");
        $sth->execute([$id, $_SESSION['uid']]);

		if ($row = $sth->fetch()) {
			$article_url = $row['link'];
			$article_url = str_replace("\n", "", $article_url);

			header("Location: $article_url");
			return;

		} else {
			print_error(__("Article not found."));
		}
	}

	/*
	function view() {
		$id = clean($_REQUEST["id"]);
		$cids = explode(",", clean($_REQUEST["cids"]));
		$mode = clean($_REQUEST["mode"]);

		// in prefetch mode we only output requested cids, main article
		// just gets marked as read (it already exists in client cache)

		$articles = array();

		if ($mode == "") {
			array_push($articles, $this->format_article($id, false));
		} else if ($mode == "zoom") {
			array_push($articles, $this->format_article($id, true, true));
		} else if ($mode == "raw") {
			if (isset($_REQUEST['html'])) {
				header("Content-Type: text/html");
				print '<link rel="stylesheet" type="text/css" href="css/default.css"/>';
			}

			$article = $this->format_article($id, false, isset($_REQUEST["zoom"]));
			print $article['content'];
			return;
		}

		$this->catchupArticleById($id, 0);

		if (!$_SESSION["bw_limit"]) {
			foreach ($cids as $cid) {
				if ($cid) {
					array_push($articles, $this->format_article($cid, false, false));
				}
			}
		}

		print json_encode($articles);
	} */

	/*
	private function catchupArticleById($id, $cmode) {

		if ($cmode == 0) {
			$sth = $this->pdo->prepare("UPDATE ttrss_user_entries SET
			unread = false,last_read = NOW()
			WHERE ref_id = ? AND owner_uid = ?");
		} else if ($cmode == 1) {
            $sth = $this->pdo->prepare("UPDATE ttrss_user_entries SET
			unread = true
			WHERE ref_id = ? AND owner_uid = ?");
		} else {
            $sth = $this->pdo->prepare("UPDATE ttrss_user_entries SET
			unread = NOT unread,last_read = NOW()
			WHERE ref_id = ? AND owner_uid = ?");
		}

		$sth->execute([$id, $_SESSION['uid']]);

		$feed_id = $this->getArticleFeed($id);
		CCache::update($feed_id, $_SESSION["uid"]);
	}
	*/

	static function create_published_article($title, $url, $content, $labels_str,
			$owner_uid) {

		$guid = 'SHA1:' . sha1("ttshared:" . $url . $owner_uid); // include owner_uid to prevent global GUID clash

		if (!$content) {
			$pluginhost = new PluginHost();
			$pluginhost->load_all(PluginHost::KIND_ALL, $owner_uid);
			$pluginhost->load_data();

			$af_readability = $pluginhost->get_plugin("Af_Readability");

			if ($af_readability) {
				$enable_share_anything = $pluginhost->get($af_readability, "enable_share_anything");

				if ($enable_share_anything) {
					$extracted_content = $af_readability->extract_content($url);

					if ($extracted_content) $content = $extracted_content;
				}
			}
		}

		$content_hash = sha1($content);

		if ($labels_str != "") {
			$labels = explode(",", $labels_str);
		} else {
			$labels = array();
		}

		$rc = false;

		if (!$title) $title = $url;
		if (!$title && !$url) return false;

		if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) return false;

		$pdo = Db::pdo();

		$pdo->beginTransaction();

		// only check for our user data here, others might have shared this with different content etc
		$sth = $pdo->prepare("SELECT id FROM ttrss_entries, ttrss_user_entries WHERE
			guid = ? AND ref_id = id AND owner_uid = ? LIMIT 1");
		$sth->execute([$guid, $owner_uid]);

		if ($row = $sth->fetch()) {
			$ref_id = $row['id'];

			$sth = $pdo->prepare("SELECT int_id FROM ttrss_user_entries WHERE
				ref_id = ? AND owner_uid = ? LIMIT 1");
            $sth->execute([$ref_id, $owner_uid]);

			if ($row = $sth->fetch()) {
				$int_id = $row['int_id'];

				$sth = $pdo->prepare("UPDATE ttrss_entries SET
					content = ?, content_hash = ? WHERE id = ?");
				$sth->execute([$content, $content_hash, $ref_id]);

				$sth = $pdo->prepare("UPDATE ttrss_user_entries SET published = true,
						last_published = NOW() WHERE
						int_id = ? AND owner_uid = ?");
				$sth->execute([$int_id, $owner_uid]);

			} else {

				$sth = $pdo->prepare("INSERT INTO ttrss_user_entries
					(ref_id, uuid, feed_id, orig_feed_id, owner_uid, published, tag_cache, label_cache,
						last_read, note, unread, last_published)
					VALUES
					(?, '', NULL, NULL, ?, true, '', '', NOW(), '', false, NOW())");
				$sth->execute([$ref_id, $owner_uid]);
			}

			if (count($labels) != 0) {
				foreach ($labels as $label) {
					Labels::add_article($ref_id, trim($label), $owner_uid);
				}
			}

			$rc = true;

		} else {
			$sth = $pdo->prepare("INSERT INTO ttrss_entries
				(title, guid, link, updated, content, content_hash, date_entered, date_updated)
				VALUES
				(?, ?, ?, NOW(), ?, ?, NOW(), NOW())");
			$sth->execute([$title, $guid, $url, $content, $content_hash]);

			$sth = $pdo->prepare("SELECT id FROM ttrss_entries WHERE guid = ?");
			$sth->execute([$guid]);

			if ($row = $sth->fetch()) {
				$ref_id = $row["id"];

				$sth = $pdo->prepare("INSERT INTO ttrss_user_entries
					(ref_id, uuid, feed_id, orig_feed_id, owner_uid, published, tag_cache, label_cache,
						last_read, note, unread, last_published)
					VALUES
					(?, '', NULL, NULL, ?, true, '', '', NOW(), '', false, NOW())");
				$sth->execute([$ref_id, $owner_uid]);

				if (count($labels) != 0) {
					foreach ($labels as $label) {
						Labels::add_article($ref_id, trim($label), $owner_uid);
					}
				}

				$rc = true;
			}
		}

		$pdo->commit();

		return $rc;
	}

	function editArticleTags() {

		print __("Tags for this article (separated by commas):")."<br>";

		$param = clean($_REQUEST['param']);

		$tags = Article::get_article_tags($param);

		$tags_str = join(", ", $tags);

		print_hidden("id", "$param");
		print_hidden("op", "article");
		print_hidden("method", "setArticleTags");

		print "<table width='100%'><tr><td>";

		print "<textarea dojoType=\"dijit.form.SimpleTextarea\" rows='4'
			style='height : 100px; font-size : 12px; width : 98%' id=\"tags_str\"
			name='tags_str'>$tags_str</textarea>
		<div class=\"autocomplete\" id=\"tags_choices\"
				style=\"display:none\"></div>";

		print "</td></tr></table>";

		print "<div class='dlgButtons'>";

		print "<button dojoType=\"dijit.form.Button\"
			onclick=\"dijit.byId('editTagsDlg').execute()\">".__('Save')."</button> ";
		print "<button dojoType=\"dijit.form.Button\"
			onclick=\"dijit.byId('editTagsDlg').hide()\">".__('Cancel')."</button>";
		print "</div>";

	}

	function setScore() {
		$ids = explode(",", clean($_REQUEST['id']));
		$score = (int)clean($_REQUEST['score']);

		$ids_qmarks = arr_qmarks($ids);

		$sth = $this->pdo->prepare("UPDATE ttrss_user_entries SET
			score = ? WHERE ref_id IN ($ids_qmarks) AND owner_uid = ?");

		$sth->execute(array_merge([$score], $ids, [$_SESSION['uid']]));

		print json_encode(["id" => $ids, "score" => (int)$score]);
	}

	function getScore() {
		$id = clean($_REQUEST['id']);

		$sth = $this->pdo->prepare("SELECT score FROM ttrss_user_entries WHERE ref_id = ? AND owner_uid = ?");
		$sth->execute([$id, $_SESSION['uid']]);
		$row = $sth->fetch();

		$score = $row['score'];

		print json_encode(["id" => $id, "score" => (int)$score]);
	}


	function setArticleTags() {

		$id = clean($_REQUEST["id"]);

		$tags_str = clean($_REQUEST["tags_str"]);
		$tags = array_unique(trim_array(explode(",", $tags_str)));

		$this->pdo->beginTransaction();

		$sth = $this->pdo->prepare("SELECT int_id FROM ttrss_user_entries WHERE
				ref_id = ? AND owner_uid = ? LIMIT 1");
		$sth->execute([$id, $_SESSION['uid']]);

		if ($row = $sth->fetch()) {

			$tags_to_cache = array();

			$int_id = $row['int_id'];

			$sth = $this->pdo->prepare("DELETE FROM ttrss_tags WHERE
				post_int_id = ? AND owner_uid = ?");
			$sth->execute([$int_id, $_SESSION['uid']]);

			foreach ($tags as $tag) {
				$tag = sanitize_tag($tag);

				if (!tag_is_valid($tag)) {
					continue;
				}

				if (preg_match("/^[0-9]*$/", $tag)) {
					continue;
				}

				//					print "<!-- $id : $int_id : $tag -->";

				if ($tag != '') {
					$sth = $this->pdo->prepare("INSERT INTO ttrss_tags
								(post_int_id, owner_uid, tag_name)
								VALUES (?, ?, ?)");

					$sth->execute([$int_id, $_SESSION['uid'], $tag]);
				}

				array_push($tags_to_cache, $tag);
			}

			/* update tag cache */

			sort($tags_to_cache);
			$tags_str = join(",", $tags_to_cache);

			$sth = $this->pdo->prepare("UPDATE ttrss_user_entries
				SET tag_cache = ? WHERE ref_id = ? AND owner_uid = ?");
			$sth->execute([$tags_str, $id, $_SESSION['uid']]);
		}

		$this->pdo->commit();

		$tags = Article::get_article_tags($id);
		$tags_str = $this->format_tags_string($tags, $id);
		$tags_str_full = join(", ", $tags);

		if (!$tags_str_full) $tags_str_full = __("no tags");

		print json_encode(array("id" => (int)$id,
				"content" => $tags_str, "content_full" => $tags_str_full));
	}


	function completeTags() {
		$search = clean($_REQUEST["search"]);

		$sth = $this->pdo->prepare("SELECT DISTINCT tag_name FROM ttrss_tags
				WHERE owner_uid = ? AND
				tag_name LIKE ? ORDER BY tag_name
				LIMIT 10");

		$sth->execute([$_SESSION['uid'], "$search%"]);

		print "<ul>";
		while ($line = $sth->fetch()) {
			print "<li>" . $line["tag_name"] . "</li>";
		}
		print "</ul>";
	}

	function assigntolabel() {
		return $this->labelops(true);
	}

	function removefromlabel() {
		return $this->labelops(false);
	}

	private function labelops($assign) {
		$reply = array();

		$ids = explode(",", clean($_REQUEST["ids"]));
		$label_id = clean($_REQUEST["lid"]);

		$label = Labels::find_caption($label_id, $_SESSION["uid"]);

		$reply["info-for-headlines"] = array();

		if ($label) {

			foreach ($ids as $id) {

				if ($assign)
					Labels::add_article($id, $label, $_SESSION["uid"]);
				else
					Labels::remove_article($id, $label, $_SESSION["uid"]);

				$labels = $this->get_article_labels($id, $_SESSION["uid"]);

				array_push($reply["info-for-headlines"],
				array("id" => $id, "labels" => $this->format_article_labels($labels)));

			}
		}

		$reply["message"] = "UPDATE_COUNTERS";

		print json_encode($reply);
	}

	function getArticleFeed($id) {
		$sth = $this->pdo->prepare("SELECT feed_id FROM ttrss_user_entries
			WHERE ref_id = ? AND owner_uid = ?");
		$sth->execute([$id, $_SESSION['uid']]);

		if ($row = $sth->fetch()) {
			return $row["feed_id"];
		} else {
			return 0;
		}
	}

	static function format_article_enclosures($id, $always_display_enclosures,
									   $article_content, $hide_images = false) {

		$result = Article::get_article_enclosures($id);
		$rv = '';

		foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_FORMAT_ENCLOSURES) as $plugin) {
			$retval = $plugin->hook_format_enclosures($rv, $result, $id, $always_display_enclosures, $article_content, $hide_images);
			if (is_array($retval)) {
				$rv = $retval[0];
				$result = $retval[1];
			} else {
				$rv = $retval;
			}
		}
		unset($retval); // Unset to prevent breaking render if there are no HOOK_RENDER_ENCLOSURE hooks below.

		if ($rv === '' && !empty($result)) {
			$entries_html = array();
			$entries = array();
			$entries_inline = array();

			foreach ($result as $line) {

				foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_ENCLOSURE_ENTRY) as $plugin) {
					$line = $plugin->hook_enclosure_entry($line);
				}

				$url = $line["content_url"];
				$ctype = $line["content_type"];
				$title = $line["title"];
				$width = $line["width"];
				$height = $line["height"];

				if (!$ctype) $ctype = __("unknown type");

				//$filename = substr($url, strrpos($url, "/")+1);
				$filename = basename($url);

				$player = format_inline_player($url, $ctype);

				if ($player) array_push($entries_inline, $player);

#				$entry .= " <a target=\"_blank\" href=\"" . htmlspecialchars($url) . "\" rel=\"noopener noreferrer\">" .
#					$filename . " (" . $ctype . ")" . "</a>";

				$entry = "<div onclick=\"popupOpenUrl('".htmlspecialchars($url)."')\"
					dojoType=\"dijit.MenuItem\">$filename ($ctype)</div>";

				array_push($entries_html, $entry);

				$entry = array();

				$entry["type"] = $ctype;
				$entry["filename"] = $filename;
				$entry["url"] = $url;
				$entry["title"] = $title;
				$entry["width"] = $width;
				$entry["height"] = $height;

				array_push($entries, $entry);
			}

			if ($_SESSION['uid'] && !get_pref("STRIP_IMAGES") && !$_SESSION["bw_limit"]) {
				if ($always_display_enclosures ||
					!preg_match("/<img/i", $article_content)) {

					foreach ($entries as $entry) {

						foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_RENDER_ENCLOSURE) as $plugin)
							$retval = $plugin->hook_render_enclosure($entry, $hide_images);


						if ($retval) {
							$rv .= $retval;
						} else {

							if (preg_match("/image/", $entry["type"])) {

								if (!$hide_images) {
									$encsize = '';
									if ($entry['height'] > 0)
										$encsize .= ' height="' . intval($entry['height']) . '"';
									if ($entry['width'] > 0)
										$encsize .= ' width="' . intval($entry['width']) . '"';
									$rv .= "<p><img
										alt=\"".htmlspecialchars($entry["filename"])."\"
										src=\"" .htmlspecialchars($entry["url"]) . "\"
										" . $encsize . " /></p>";
								} else {
									$rv .= "<p><a target=\"_blank\" rel=\"noopener noreferrer\"
										href=\"".htmlspecialchars($entry["url"])."\"
										>" .htmlspecialchars($entry["url"]) . "</a></p>";
								}

								if ($entry['title']) {
									$rv.= "<div class=\"enclosure_title\">${entry['title']}</div>";
								}
							}
						}
					}
				}
			}

			if (count($entries_inline) > 0) {
				//$rv .= "<hr clear='both'/>";
				foreach ($entries_inline as $entry) { $rv .= $entry; };
				$rv .= "<br clear='both'/>";
			}

			$rv .= "<div class=\"attachments\" dojoType=\"dijit.form.DropDownButton\">".
				"<span>" . __('Attachments')."</span>";

			$rv .= "<div dojoType=\"dijit.Menu\" style=\"display: none;\">";

			foreach ($entries as $entry) {
				if ($entry["title"])
					$title = " &mdash; " . truncate_string($entry["title"], 30);
				else
					$title = "";

				if ($entry["filename"])
					$filename = truncate_middle(htmlspecialchars($entry["filename"]), 60);
				else
					$filename = "";

				$rv .= "<div onclick='popupOpenUrl(\"".htmlspecialchars($entry["url"])."\")'
					dojoType=\"dijit.MenuItem\">".$filename . $title."</div>";

			};

			$rv .= "</div>";
			$rv .= "</div>";
		}

		return $rv;
	}

	static function get_article_tags($id, $owner_uid = 0, $tag_cache = false) {

		$a_id = $id;

		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		$pdo = Db::pdo();

		$sth = $pdo->prepare("SELECT DISTINCT tag_name,
			owner_uid as owner FROM	ttrss_tags
			WHERE post_int_id = (SELECT int_id FROM ttrss_user_entries WHERE
			ref_id = ? AND owner_uid = ? LIMIT 1) ORDER BY tag_name");

		$tags = array();

		/* check cache first */

		if ($tag_cache === false) {
			$csth = $pdo->prepare("SELECT tag_cache FROM ttrss_user_entries
				WHERE ref_id = ? AND owner_uid = ?");
			$csth->execute([$id, $owner_uid]);

			if ($row = $csth->fetch()) $tag_cache = $row["tag_cache"];
		}

		if ($tag_cache) {
			$tags = explode(",", $tag_cache);
		} else {

			/* do it the hard way */

			$sth->execute([$a_id, $owner_uid]);

			while ($tmp_line = $sth->fetch()) {
				array_push($tags, $tmp_line["tag_name"]);
			}

			/* update the cache */

			$tags_str = join(",", $tags);

			$sth = $pdo->prepare("UPDATE ttrss_user_entries
				SET tag_cache = ? WHERE ref_id = ?
				AND owner_uid = ?");
			$sth->execute([$tags_str, $id, $owner_uid]);
		}

		return $tags;
	}

	static function format_tags_string($tags) {
		if (!is_array($tags) || count($tags) == 0) {
			return __("no tags");
		} else {
			$maxtags = min(5, count($tags));
			$tags_str = "";

			for ($i = 0; $i < $maxtags; $i++) {
				$tags_str .= "<a class=\"tag\" href=\"#\" onclick=\"Feeds.open({feed:'".$tags[$i]."'})\">" . $tags[$i] . "</a>, ";
			}

			$tags_str = mb_substr($tags_str, 0, mb_strlen($tags_str)-2);

			if (count($tags) > $maxtags)
				$tags_str .= ", &hellip;";

			return $tags_str;
		}
	}

	static function format_article_labels($labels) {

		if (!is_array($labels)) return '';

		$labels_str = "";

		foreach ($labels as $l) {
			$labels_str .= sprintf("<div1 class='label'
				style='color : %s; background-color : %s'><i class='material-icons' style='color : %s'>label</i><div>%s</div></div1>",
				$l[2], $l[3], $l[2], $l[1]);
		}

		return $labels_str;

	}

	static function format_article_note($id, $note, $allow_edit = true) {

		if ($allow_edit) {
			$onclick = "onclick='Plugins.Note.edit($id)'";
			$note_class = 'editable';
		} else {
			$onclick = '';
			$note_class = '';
		}

		return "<div class='article-note $note_class'>
			<i class='material-icons'>note</i>
			<div $onclick class='body'>$note</div>			
			</div>";

		return $str;
	}

	static function get_article_enclosures($id) {

		$pdo = Db::pdo();

		$sth = $pdo->prepare("SELECT * FROM ttrss_enclosures
			WHERE post_id = ? AND content_url != ''");
		$sth->execute([$id]);

		$rv = array();

		while ($line = $sth->fetch()) {

			if (file_exists(CACHE_DIR . '/images/' . sha1($line["content_url"]))) {
				$line["content_url"] = get_self_url_prefix() . '/public.php?op=cached_url&hash=' . sha1($line["content_url"]);
			}

			array_push($rv, $line);
		}

		return $rv;
	}

	static function purge_orphans() {

        // purge orphaned posts in main content table

        if (DB_TYPE == "mysql")
            $limit_qpart = "LIMIT 5000";
        else
            $limit_qpart = "";

        $pdo = Db::pdo();
        $res = $pdo->query("DELETE FROM ttrss_entries WHERE
			NOT EXISTS (SELECT ref_id FROM ttrss_user_entries WHERE ref_id = id) $limit_qpart");

        if (Debug::enabled()) {
            $rows = $res->rowCount();
            Debug::log("Purged $rows orphaned posts.");
        }
    }

	static function catchupArticlesById($ids, $cmode, $owner_uid = false) {

		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		$pdo = Db::pdo();

		$ids_qmarks = arr_qmarks($ids);

		if ($cmode == 0) {
			$sth = $pdo->prepare("UPDATE ttrss_user_entries SET
			unread = false,last_read = NOW()
				WHERE ref_id IN ($ids_qmarks) AND owner_uid = ?");
		} else if ($cmode == 1) {
			$sth = $pdo->prepare("UPDATE ttrss_user_entries SET
			unread = true
				WHERE ref_id IN ($ids_qmarks) AND owner_uid = ?");
		} else {
			$sth = $pdo->prepare("UPDATE ttrss_user_entries SET
				unread = NOT unread,last_read = NOW()
					WHERE ref_id IN ($ids_qmarks) AND owner_uid = ?");
		}

		$sth->execute(array_merge($ids, [$owner_uid]));

		/* update ccache */

		$sth = $pdo->prepare("SELECT DISTINCT feed_id FROM ttrss_user_entries
			WHERE ref_id IN ($ids_qmarks) AND owner_uid = ?");
		$sth->execute(array_merge($ids, [$owner_uid]));

		while ($line = $sth->fetch()) {
			CCache::update($line["feed_id"], $owner_uid);
		}
	}

	static function getLastArticleId() {
		$pdo = DB::pdo();

		$sth = $pdo->prepare("SELECT ref_id AS id FROM ttrss_user_entries
			WHERE owner_uid = ? ORDER BY ref_id DESC LIMIT 1");
		$sth->execute([$_SESSION['uid']]);

		if ($row = $sth->fetch()) {
			return $row['id'];
		} else {
			return -1;
		}
	}

	static function get_article_labels($id, $owner_uid = false) {
		$rv = array();

		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		$pdo = Db::pdo();

		$sth = $pdo->prepare("SELECT label_cache FROM
			ttrss_user_entries WHERE ref_id = ? AND owner_uid = ?");
		$sth->execute([$id, $owner_uid]);

		if ($row = $sth->fetch()) {
			$label_cache = $row["label_cache"];

			if ($label_cache) {
				$tmp = json_decode($label_cache, true);

				if (!$tmp || $tmp["no-labels"] == 1)
					return $rv;
				else
					return $tmp;
			}
		}

		$sth = $pdo->prepare("SELECT DISTINCT label_id,caption,fg_color,bg_color
				FROM ttrss_labels2, ttrss_user_labels2
			WHERE id = label_id
				AND article_id = ?
				AND owner_uid = ?
			ORDER BY caption");
		$sth->execute([$id, $owner_uid]);

		while ($line = $sth->fetch()) {
			$rk = array(Labels::label_to_feed_id($line["label_id"]),
				$line["caption"], $line["fg_color"],
				$line["bg_color"]);
			array_push($rv, $rk);
		}

		if (count($rv) > 0)
			Labels::update_cache($owner_uid, $id, $rv);
		else
			Labels::update_cache($owner_uid, $id, array("no-labels" => 1));

		return $rv;
	}

}
