<?php
class API extends Handler {

	const API_LEVEL  = 14;

	const STATUS_OK  = 0;
	const STATUS_ERR = 1;

	private $seq;

	static function param_to_bool($p) {
		return $p && ($p !== "f" && $p !== "false");
	}

	function before($method) {
		if (parent::before($method)) {
			header("Content-Type: text/json");

			if (!$_SESSION["uid"] && $method != "login" && $method != "isloggedin") {
				$this->wrap(self::STATUS_ERR, array("error" => 'NOT_LOGGED_IN'));
				return false;
			}

			if ($_SESSION["uid"] && $method != "logout" && !get_pref('ENABLE_API_ACCESS')) {
				$this->wrap(self::STATUS_ERR, array("error" => 'API_DISABLED'));
				return false;
			}

			$this->seq = (int) clean($_REQUEST['seq']);

			return true;
		}
		return false;
	}

	function wrap($status, $reply) {
		print json_encode(array("seq" => $this->seq,
			"status" => $status,
			"content" => $reply));
	}

	function getVersion() {
		$rv = array("version" => VERSION);
		$this->wrap(self::STATUS_OK, $rv);
	}

	function getApiLevel() {
		$rv = array("level" => self::API_LEVEL);
		$this->wrap(self::STATUS_OK, $rv);
	}

	function login() {
		@session_destroy();
		@session_start();

		$login = clean($_REQUEST["user"]);
		$password = clean($_REQUEST["password"]);
		$password_base64 = base64_decode(clean($_REQUEST["password"]));

		if (SINGLE_USER_MODE) $login = "admin";

		$sth = $this->pdo->prepare("SELECT id FROM ttrss_users WHERE login = ?");
		$sth->execute([$login]);

		if ($row = $sth->fetch()) {
			$uid = $row["id"];
		} else {
			$uid = 0;
		}

		if (!$uid) {
			$this->wrap(self::STATUS_ERR, array("error" => "LOGIN_ERROR"));
			return;
		}

		if (get_pref("ENABLE_API_ACCESS", $uid)) {
			if (authenticate_user($login, $password)) {               // try login with normal password
				$this->wrap(self::STATUS_OK, array("session_id" => session_id(),
					"api_level" => self::API_LEVEL));
			} else if (authenticate_user($login, $password_base64)) { // else try with base64_decoded password
				$this->wrap(self::STATUS_OK,	array("session_id" => session_id(),
					"api_level" => self::API_LEVEL));
			} else {                                                         // else we are not logged in
				user_error("Failed login attempt for $login from {$_SERVER['REMOTE_ADDR']}", E_USER_WARNING);
				$this->wrap(self::STATUS_ERR, array("error" => "LOGIN_ERROR"));
			}
		} else {
			$this->wrap(self::STATUS_ERR, array("error" => "API_DISABLED"));
		}

	}

	function logout() {
		logout_user();
		$this->wrap(self::STATUS_OK, array("status" => "OK"));
	}

	function isLoggedIn() {
		$this->wrap(self::STATUS_OK, array("status" => $_SESSION["uid"] != ''));
	}

	function getUnread() {
		$feed_id = clean($_REQUEST["feed_id"]);
		$is_cat = clean($_REQUEST["is_cat"]);

		if ($feed_id) {
			$this->wrap(self::STATUS_OK, array("unread" => getFeedUnread($feed_id, $is_cat)));
		} else {
			$this->wrap(self::STATUS_OK, array("unread" => Feeds::getGlobalUnread()));
		}
	}

	/* Method added for ttrss-reader for Android */
	function getCounters() {
		$this->wrap(self::STATUS_OK, Counters::getAllCounters());
	}

	function getFeeds() {
		$cat_id = clean($_REQUEST["cat_id"]);
		$unread_only = API::param_to_bool(clean($_REQUEST["unread_only"]));
		$limit = (int) clean($_REQUEST["limit"]);
		$offset = (int) clean($_REQUEST["offset"]);
		$include_nested = API::param_to_bool(clean($_REQUEST["include_nested"]));

		$feeds = $this->api_get_feeds($cat_id, $unread_only, $limit, $offset, $include_nested);

		$this->wrap(self::STATUS_OK, $feeds);
	}

	function getCategories() {
		$unread_only = API::param_to_bool(clean($_REQUEST["unread_only"]));
		$enable_nested = API::param_to_bool(clean($_REQUEST["enable_nested"]));
		$include_empty = API::param_to_bool(clean($_REQUEST['include_empty']));

		// TODO do not return empty categories, return Uncategorized and standard virtual cats

		if ($enable_nested)
			$nested_qpart = "parent_cat IS NULL";
		else
			$nested_qpart = "true";

		$sth = $this->pdo->prepare("SELECT
				id, title, order_id, (SELECT COUNT(id) FROM
				ttrss_feeds WHERE
				ttrss_feed_categories.id IS NOT NULL AND cat_id = ttrss_feed_categories.id) AS num_feeds,
			(SELECT COUNT(id) FROM
				ttrss_feed_categories AS c2 WHERE
				c2.parent_cat = ttrss_feed_categories.id) AS num_cats
			FROM ttrss_feed_categories
			WHERE $nested_qpart AND owner_uid = ?");
		$sth->execute([$_SESSION['uid']]);

		$cats = array();

		while ($line = $sth->fetch()) {
			if ($include_empty || $line["num_feeds"] > 0 || $line["num_cats"] > 0) {
				$unread = getFeedUnread($line["id"], true);

				if ($enable_nested)
					$unread += Feeds::getCategoryChildrenUnread($line["id"]);

				if ($unread || !$unread_only) {
					array_push($cats, array("id" => $line["id"],
						"title" => $line["title"],
						"unread" => $unread,
						"order_id" => (int) $line["order_id"],
					));
				}
			}
		}

		foreach (array(-2,-1,0) as $cat_id) {
			if ($include_empty || !$this->isCategoryEmpty($cat_id)) {
				$unread = getFeedUnread($cat_id, true);

				if ($unread || !$unread_only) {
					array_push($cats, array("id" => $cat_id,
						"title" => Feeds::getCategoryTitle($cat_id),
						"unread" => $unread));
				}
			}
		}

		$this->wrap(self::STATUS_OK, $cats);
	}

	function getHeadlines() {
		$feed_id = clean($_REQUEST["feed_id"]);
		if ($feed_id !== "") {

			if (is_numeric($feed_id)) $feed_id = (int) $feed_id;

			$limit = (int)clean($_REQUEST["limit"]);

			if (!$limit || $limit >= 200) $limit = 200;

			$offset = (int)clean($_REQUEST["skip"]);
			$filter = clean($_REQUEST["filter"]);
			$is_cat = API::param_to_bool(clean($_REQUEST["is_cat"]));
			$show_excerpt = API::param_to_bool(clean($_REQUEST["show_excerpt"]));
			$show_content = API::param_to_bool(clean($_REQUEST["show_content"]));
			/* all_articles, unread, adaptive, marked, updated */
			$view_mode = clean($_REQUEST["view_mode"]);
			$include_attachments = API::param_to_bool(clean($_REQUEST["include_attachments"]));
			$since_id = (int)clean($_REQUEST["since_id"]);
			$include_nested = API::param_to_bool(clean($_REQUEST["include_nested"]));
			$sanitize_content = !isset($_REQUEST["sanitize"]) ||
				API::param_to_bool($_REQUEST["sanitize"]);
			$force_update = API::param_to_bool(clean($_REQUEST["force_update"]));
			$has_sandbox = API::param_to_bool(clean($_REQUEST["has_sandbox"]));
			$excerpt_length = (int)clean($_REQUEST["excerpt_length"]);
			$check_first_id = (int)clean($_REQUEST["check_first_id"]);
			$include_header = API::param_to_bool(clean($_REQUEST["include_header"]));

			$_SESSION['hasSandbox'] = $has_sandbox;

			$skip_first_id_check = false;

			$override_order = false;
			switch (clean($_REQUEST["order_by"])) {
				case "title":
					$override_order = "ttrss_entries.title, date_entered, updated";
					break;
				case "date_reverse":
					$override_order = "score DESC, date_entered, updated";
					$skip_first_id_check = true;
					break;
				case "feed_dates":
					$override_order = "updated DESC";
					break;
			}

			/* do not rely on params below */

			$search = clean($_REQUEST["search"]);

			list($headlines, $headlines_header) = $this->api_get_headlines($feed_id, $limit, $offset,
				$filter, $is_cat, $show_excerpt, $show_content, $view_mode, $override_order,
				$include_attachments, $since_id, $search,
				$include_nested, $sanitize_content, $force_update, $excerpt_length, $check_first_id, $skip_first_id_check);

			if ($include_header) {
				$this->wrap(self::STATUS_OK, array($headlines_header, $headlines));
			} else {
				$this->wrap(self::STATUS_OK, $headlines);
			}
		} else {
			$this->wrap(self::STATUS_ERR, array("error" => 'INCORRECT_USAGE'));
		}
	}

	function updateArticle() {
		$article_ids = explode(",", clean($_REQUEST["article_ids"]));
		$mode = (int) clean($_REQUEST["mode"]);
		$data = clean($_REQUEST["data"]);
		$field_raw = (int)clean($_REQUEST["field"]);

		$field = "";
		$set_to = "";

		switch ($field_raw) {
			case 0:
				$field = "marked";
				$additional_fields = ",last_marked = NOW()";
				break;
			case 1:
				$field = "published";
				$additional_fields = ",last_published = NOW()";
				break;
			case 2:
				$field = "unread";
				$additional_fields = ",last_read = NOW()";
				break;
			case 3:
				$field = "note";
		};

		switch ($mode) {
			case 1:
				$set_to = "true";
				break;
			case 0:
				$set_to = "false";
				break;
			case 2:
				$set_to = "NOT $field";
				break;
		}

		if ($field == "note") $set_to = $this->pdo->quote($data);

		if ($field && $set_to && count($article_ids) > 0) {

			$article_qmarks = arr_qmarks($article_ids);

			$sth = $this->pdo->prepare("UPDATE ttrss_user_entries SET
				$field = $set_to $additional_fields
				WHERE ref_id IN ($article_qmarks) AND owner_uid = ?");
			$sth->execute(array_merge($article_ids, [$_SESSION['uid']]));

			$num_updated = $sth->rowCount();

			if ($num_updated > 0 && $field == "unread") {
				$sth = $this->pdo->prepare("SELECT DISTINCT feed_id FROM ttrss_user_entries
					WHERE ref_id IN ($article_qmarks)");
				$sth->execute($article_ids);

				while ($line = $sth->fetch()) {
					CCache::update($line["feed_id"], $_SESSION["uid"]);
				}
			}

			$this->wrap(self::STATUS_OK, array("status" => "OK",
				"updated" => $num_updated));

		} else {
			$this->wrap(self::STATUS_ERR, array("error" => 'INCORRECT_USAGE'));
		}

	}

	function getArticle() {

		$article_ids = explode(",", clean($_REQUEST["article_id"]));
		$sanitize_content = !isset($_REQUEST["sanitize"]) ||
			API::param_to_bool($_REQUEST["sanitize"]);

		if ($article_ids) {

			$article_qmarks = arr_qmarks($article_ids);

			$sth = $this->pdo->prepare("SELECT id,guid,title,link,content,feed_id,comments,int_id,
				marked,unread,published,score,note,lang,
				".SUBSTRING_FOR_DATE."(updated,1,16) as updated,
				author,(SELECT title FROM ttrss_feeds WHERE id = feed_id) AS feed_title,
				(SELECT site_url FROM ttrss_feeds WHERE id = feed_id) AS site_url,
				(SELECT hide_images FROM ttrss_feeds WHERE id = feed_id) AS hide_images
				FROM ttrss_entries,ttrss_user_entries
				WHERE id IN ($article_qmarks) AND ref_id = id AND owner_uid = ?");

			$sth->execute(array_merge($article_ids, [$_SESSION['uid']]));

			$articles = array();

			while ($line = $sth->fetch()) {

				$attachments = Article::get_article_enclosures($line['id']);

				$article = array(
					"id" => $line["id"],
					"guid" => $line["guid"],
					"title" => $line["title"],
					"link" => $line["link"],
					"labels" => Article::get_article_labels($line['id']),
					"unread" => API::param_to_bool($line["unread"]),
					"marked" => API::param_to_bool($line["marked"]),
					"published" => API::param_to_bool($line["published"]),
					"comments" => $line["comments"],
					"author" => $line["author"],
					"updated" => (int) strtotime($line["updated"]),
					"feed_id" => $line["feed_id"],
					"attachments" => $attachments,
					"score" => (int)$line["score"],
					"feed_title" => $line["feed_title"],
					"note" => $line["note"],
					"lang" => $line["lang"]
				);

				if ($sanitize_content) {
					$article["content"] = sanitize(
						$line["content"],
						API::param_to_bool($line['hide_images']),
						false, $line["site_url"], false, $line["id"]);
				} else {
					$article["content"] = $line["content"];
				}

				foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_RENDER_ARTICLE_API) as $p) {
					$article = $p->hook_render_article_api(array("article" => $article));
				}

				$article['content'] = rewrite_cached_urls($article['content']);

				array_push($articles, $article);

			}

			$this->wrap(self::STATUS_OK, $articles);
		} else {
			$this->wrap(self::STATUS_ERR, array("error" => 'INCORRECT_USAGE'));
		}
	}

	function getConfig() {
		$config = array(
			"icons_dir" => ICONS_DIR,
			"icons_url" => ICONS_URL);

		$config["daemon_is_running"] = file_is_locked("update_daemon.lock");

		$sth = $this->pdo->prepare("SELECT COUNT(*) AS cf FROM
			ttrss_feeds WHERE owner_uid = ?");
		$sth->execute([$_SESSION['uid']]);
		$row = $sth->fetch();

		$config["num_feeds"] = $row["cf"];

		$this->wrap(self::STATUS_OK, $config);
	}

	function updateFeed() {
		$feed_id = (int) clean($_REQUEST["feed_id"]);

		if (!ini_get("open_basedir")) {
			RSSUtils::update_rss_feed($feed_id);
		}

		$this->wrap(self::STATUS_OK, array("status" => "OK"));
	}

	function catchupFeed() {
		$feed_id = clean($_REQUEST["feed_id"]);
		$is_cat = clean($_REQUEST["is_cat"]);

		Feeds::catchup_feed($feed_id, $is_cat);

		$this->wrap(self::STATUS_OK, array("status" => "OK"));
	}

	function getPref() {
		$pref_name = clean($_REQUEST["pref_name"]);

		$this->wrap(self::STATUS_OK, array("value" => get_pref($pref_name)));
	}

	function getLabels() {
		$article_id = (int)clean($_REQUEST['article_id']);

		$rv = array();

		$sth = $this->pdo->prepare("SELECT id, caption, fg_color, bg_color
			FROM ttrss_labels2
			WHERE owner_uid = ? ORDER BY caption");
		$sth->execute([$_SESSION['uid']]);

		if ($article_id)
			$article_labels = Article::get_article_labels($article_id);
		else
			$article_labels = array();

		while ($line = $sth->fetch()) {

			$checked = false;
			foreach ($article_labels as $al) {
				if (Labels::feed_to_label_id($al[0]) == $line['id']) {
					$checked = true;
					break;
				}
			}

			array_push($rv, array(
				"id" => (int)Labels::label_to_feed_id($line['id']),
				"caption" => $line['caption'],
				"fg_color" => $line['fg_color'],
				"bg_color" => $line['bg_color'],
				"checked" => $checked));
		}

		$this->wrap(self::STATUS_OK, $rv);
	}

	function setArticleLabel() {

		$article_ids = explode(",", clean($_REQUEST["article_ids"]));
		$label_id = (int) clean($_REQUEST['label_id']);
		$assign = API::param_to_bool(clean($_REQUEST['assign']));

		$label = Labels::find_caption(Labels::feed_to_label_id($label_id), $_SESSION["uid"]);

		$num_updated = 0;

		if ($label) {

			foreach ($article_ids as $id) {

				if ($assign)
					Labels::add_article($id, $label, $_SESSION["uid"]);
				else
					Labels::remove_article($id, $label, $_SESSION["uid"]);

				++$num_updated;

			}
		}

		$this->wrap(self::STATUS_OK, array("status" => "OK",
			"updated" => $num_updated));

	}

	function index($method) {
		$plugin = PluginHost::getInstance()->get_api_method(strtolower($method));

		if ($plugin && method_exists($plugin, $method)) {
			$reply = $plugin->$method();

			$this->wrap($reply[0], $reply[1]);

		} else {
			$this->wrap(self::STATUS_ERR, array("error" => 'UNKNOWN_METHOD', "method" => $method));
		}
	}

	function shareToPublished() {
		$title = strip_tags(clean($_REQUEST["title"]));
		$url = strip_tags(clean($_REQUEST["url"]));
		$content = strip_tags(clean($_REQUEST["content"]));

		if (Article::create_published_article($title, $url, $content, "", $_SESSION["uid"])) {
			$this->wrap(self::STATUS_OK, array("status" => 'OK'));
		} else {
			$this->wrap(self::STATUS_ERR, array("error" => 'Publishing failed'));
		}
	}

	static function api_get_feeds($cat_id, $unread_only, $limit, $offset, $include_nested = false) {

			$feeds = array();

			$pdo = Db::pdo();

			$limit = (int) $limit;
			$offset = (int) $offset;
			$cat_id = (int) $cat_id;

			/* Labels */

			if ($cat_id == -4 || $cat_id == -2) {
				$counters = Counters::getLabelCounters(true);

				foreach (array_values($counters) as $cv) {

					$unread = $cv["counter"];

					if ($unread || !$unread_only) {

						$row = array(
								"id" => (int) $cv["id"],
								"title" => $cv["description"],
								"unread" => $cv["counter"],
								"cat_id" => -2,
							);

						array_push($feeds, $row);
					}
				}
			}

			/* Virtual feeds */

			if ($cat_id == -4 || $cat_id == -1) {
				foreach (array(-1, -2, -3, -4, -6, 0) as $i) {
					$unread = getFeedUnread($i);

					if ($unread || !$unread_only) {
						$title = Feeds::getFeedTitle($i);

						$row = array(
								"id" => $i,
								"title" => $title,
								"unread" => $unread,
								"cat_id" => -1,
							);
						array_push($feeds, $row);
					}

				}
			}

			/* Child cats */

			if ($include_nested && $cat_id) {
				$sth = $pdo->prepare("SELECT
					id, title, order_id FROM ttrss_feed_categories
					WHERE parent_cat = ? AND owner_uid = ? ORDER BY id, title");

				$sth->execute([$cat_id, $_SESSION['uid']]);

				while ($line = $sth->fetch()) {
					$unread = getFeedUnread($line["id"], true) +
						Feeds::getCategoryChildrenUnread($line["id"]);

					if ($unread || !$unread_only) {
						$row = array(
								"id" => (int) $line["id"],
								"title" => $line["title"],
								"unread" => $unread,
								"is_cat" => true,
                                "order_id" => (int) $line["order_id"]
							);
						array_push($feeds, $row);
					}
				}
			}

			/* Real feeds */

			if ($limit) {
				$limit_qpart = "LIMIT $limit OFFSET $offset";
			} else {
				$limit_qpart = "";
			}

			if ($cat_id == -4 || $cat_id == -3) {
				$sth = $pdo->prepare("SELECT
					id, feed_url, cat_id, title, order_id, ".
						SUBSTRING_FOR_DATE."(last_updated,1,19) AS last_updated
						FROM ttrss_feeds WHERE owner_uid = ?
						ORDER BY cat_id, title " . $limit_qpart);
				$sth->execute([$_SESSION['uid']]);

			} else {

				$sth = $pdo->prepare("SELECT
					id, feed_url, cat_id, title, order_id, ".
						SUBSTRING_FOR_DATE."(last_updated,1,19) AS last_updated
						FROM ttrss_feeds WHERE
						(cat_id = :cat OR (:cat = 0 AND cat_id IS NULL))
						AND owner_uid = :uid
						ORDER BY cat_id, title " . $limit_qpart);
				$sth->execute([":uid" => $_SESSION['uid'], ":cat" => $cat_id]);
			}

			while ($line = $sth->fetch()) {

				$unread = getFeedUnread($line["id"]);

				$has_icon = Feeds::feedHasIcon($line['id']);

				if ($unread || !$unread_only) {

					$row = array(
							"feed_url" => $line["feed_url"],
							"title" => $line["title"],
							"id" => (int)$line["id"],
							"unread" => (int)$unread,
							"has_icon" => $has_icon,
							"cat_id" => (int)$line["cat_id"],
							"last_updated" => (int) strtotime($line["last_updated"]),
							"order_id" => (int) $line["order_id"],
						);

					array_push($feeds, $row);
				}
			}

		return $feeds;
	}

	/**
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	static function api_get_headlines($feed_id, $limit, $offset,
				$filter, $is_cat, $show_excerpt, $show_content, $view_mode, $order,
				$include_attachments, $since_id,
				$search = "", $include_nested = false, $sanitize_content = true,
				$force_update = false, $excerpt_length = 100, $check_first_id = false, $skip_first_id_check = false) {

			$pdo = Db::pdo();

			if ($force_update && $feed_id > 0 && is_numeric($feed_id)) {
				// Update the feed if required with some basic flood control

				$sth = $pdo->prepare(
					"SELECT cache_images,".SUBSTRING_FOR_DATE."(last_updated,1,19) AS last_updated
						FROM ttrss_feeds WHERE id = ?");
				$sth->execute([$feed_id]);

				if ($row = $sth->fetch()) {
					$last_updated = strtotime($row["last_updated"]);
					$cache_images = API::param_to_bool($row["cache_images"]);

					if (!$cache_images && time() - $last_updated > 120) {
						RSSUtils::update_rss_feed($feed_id, true);
					} else {
						$sth = $pdo->prepare("UPDATE ttrss_feeds SET last_updated = '1970-01-01', last_update_started = '1970-01-01'
							WHERE id = ?");
						$sth->execute([$feed_id]);
					}
				}
			}

			$params = array(
				"feed" => $feed_id,
				"limit" => $limit,
				"view_mode" => $view_mode,
				"cat_view" => $is_cat,
				"search" => $search,
				"override_order" => $order,
				"offset" => $offset,
				"since_id" => $since_id,
				"include_children" => $include_nested,
				"check_first_id" => $check_first_id,
				"skip_first_id_check" => $skip_first_id_check
			);

			$qfh_ret = Feeds::queryFeedHeadlines($params);

			$result = $qfh_ret[0];
			$feed_title = $qfh_ret[1];
			$first_id = $qfh_ret[6];

			$headlines = array();

			$headlines_header = array(
				'id' => $feed_id,
				'first_id' => $first_id,
				'is_cat' => $is_cat);

			if (!is_numeric($result)) {
				while ($line = $result->fetch()) {
					$line["content_preview"] = truncate_string(strip_tags($line["content"]), $excerpt_length);
					foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_QUERY_HEADLINES) as $p) {
						$line = $p->hook_query_headlines($line, $excerpt_length, true);
					}

					$is_updated = ($line["last_read"] == "" &&
						($line["unread"] != "t" && $line["unread"] != "1"));

					$tags = explode(",", $line["tag_cache"]);

					$label_cache = $line["label_cache"];
					$labels = false;

					if ($label_cache) {
						$label_cache = json_decode($label_cache, true);

						if ($label_cache) {
							if ($label_cache["no-labels"] == 1)
								$labels = array();
							else
								$labels = $label_cache;
						}
					}

					if (!is_array($labels)) $labels = Article::get_article_labels($line["id"]);

					$headline_row = array(
						"id" => (int)$line["id"],
						"guid" => $line["guid"],
						"unread" => API::param_to_bool($line["unread"]),
						"marked" => API::param_to_bool($line["marked"]),
						"published" => API::param_to_bool($line["published"]),
						"updated" => (int)strtotime($line["updated"]),
						"is_updated" => $is_updated,
						"title" => $line["title"],
						"link" => $line["link"],
						"feed_id" => $line["feed_id"] ? $line['feed_id'] : 0,
						"tags" => $tags,
					);

					if ($include_attachments)
						$headline_row['attachments'] = Article::get_article_enclosures(
							$line['id']);

					if ($show_excerpt)
						$headline_row["excerpt"] = $line["content_preview"];

					if ($show_content) {

						if ($sanitize_content) {
							$headline_row["content"] = sanitize(
								$line["content"],
								API::param_to_bool($line['hide_images']),
								false, $line["site_url"], false, $line["id"]);
						} else {
							$headline_row["content"] = $line["content"];
						}
					}

					// unify label output to ease parsing
					if ($labels["no-labels"] == 1) $labels = array();

					$headline_row["labels"] = $labels;

					$headline_row["feed_title"] = $line["feed_title"] ? $line["feed_title"] :
						$feed_title;

					$headline_row["comments_count"] = (int)$line["num_comments"];
					$headline_row["comments_link"] = $line["comments"];

					$headline_row["always_display_attachments"] = API::param_to_bool($line["always_display_enclosures"]);

					$headline_row["author"] = $line["author"];

					$headline_row["score"] = (int)$line["score"];
					$headline_row["note"] = $line["note"];
					$headline_row["lang"] = $line["lang"];

					foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_RENDER_ARTICLE_API) as $p) {
						$headline_row = $p->hook_render_article_api(array("headline" => $headline_row));
					}

					$headline_row['content'] = rewrite_cached_urls($headline_row['content']);

					array_push($headlines, $headline_row);
				}
			} else if (is_numeric($result) && $result == -1) {
				$headlines_header['first_id_changed'] = true;
			}

			return array($headlines, $headlines_header);
	}

	function unsubscribeFeed() {
		$feed_id = (int) clean($_REQUEST["feed_id"]);

		$sth = $this->pdo->prepare("SELECT id FROM ttrss_feeds WHERE
			id = ? AND owner_uid = ?");
		$sth->execute([$feed_id, $_SESSION['uid']]);

		if ($row = $sth->fetch()) {
			Pref_Feeds::remove_feed($feed_id, $_SESSION["uid"]);
			$this->wrap(self::STATUS_OK, array("status" => "OK"));
		} else {
			$this->wrap(self::STATUS_ERR, array("error" => "FEED_NOT_FOUND"));
		}
	}

	function subscribeToFeed() {
		$feed_url = clean($_REQUEST["feed_url"]);
		$category_id = (int) clean($_REQUEST["category_id"]);
		$login = clean($_REQUEST["login"]);
		$password = clean($_REQUEST["password"]);

		if ($feed_url) {
			$rc = Feeds::subscribe_to_feed($feed_url, $category_id, $login, $password);

			$this->wrap(self::STATUS_OK, array("status" => $rc));
		} else {
			$this->wrap(self::STATUS_ERR, array("error" => 'INCORRECT_USAGE'));
		}
	}

	function getFeedTree() {
		$include_empty = API::param_to_bool(clean($_REQUEST['include_empty']));

		$pf = new Pref_Feeds($_REQUEST);

		$_REQUEST['mode'] = 2;
		$_REQUEST['force_show_empty'] = $include_empty;

		if ($pf){
			$data = $pf->makefeedtree();
			$this->wrap(self::STATUS_OK, array("categories" => $data));
		} else {
			$this->wrap(self::STATUS_ERR, array("error" =>
				'UNABLE_TO_INSTANTIATE_OBJECT'));
		}

	}

	// only works for labels or uncategorized for the time being
	private function isCategoryEmpty($id) {

		if ($id == -2) {
			$sth = $this->pdo->prepare("SELECT COUNT(id) AS count FROM ttrss_labels2
				WHERE owner_uid = ?");
			$sth->execute([$_SESSION['uid']]);
			$row = $sth->fetch();

			return $row["count"] == 0;

		} else if ($id == 0) {
			$sth = $this->pdo->prepare("SELECT COUNT(id) AS count FROM ttrss_feeds
				WHERE cat_id IS NULL AND owner_uid = ?");
			$sth->execute([$_SESSION['uid']]);
			$row = $sth->fetch();

			return $row["count"] == 0;

		}

		return false;
	}


}
