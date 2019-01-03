<?php
class RPC extends Handler_Protected {

	function csrf_ignore($method) {
		$csrf_ignored = array("sanitycheck", "completelabels", "saveprofile");

		return array_search($method, $csrf_ignored) !== false;
	}

	function setprofile() {
		$_SESSION["profile"] = (int) clean($_REQUEST["id"]);

		// default value
		if (!$_SESSION["profile"]) $_SESSION["profile"] = null;
	}

	function remprofiles() {
		$ids = explode(",", trim(clean($_REQUEST["ids"])));

		foreach ($ids as $id) {
			if ($_SESSION["profile"] != $id) {
				$sth = $this->pdo->prepare("DELETE FROM ttrss_settings_profiles WHERE id = ? AND
							owner_uid = ?");
				$sth->execute([$id, $_SESSION['uid']]);
			}
		}
	}

	// Silent
	function addprofile() {
		$title = trim(clean($_REQUEST["title"]));

		if ($title) {
			$this->pdo->beginTransaction();

			$sth = $this->pdo->prepare("SELECT id FROM ttrss_settings_profiles
				WHERE title = ? AND owner_uid = ?");
			$sth->execute([$title, $_SESSION['uid']]);

			if (!$sth->fetch()) {

				$sth = $this->pdo->prepare("INSERT INTO ttrss_settings_profiles (title, owner_uid)
							VALUES (?, ?)");

				$sth->execute([$title, $_SESSION['uid']]);

				$sth = $this->pdo->prepare("SELECT id FROM ttrss_settings_profiles WHERE
					title = ? AND owner_uid = ?");
				$sth->execute([$title, $_SESSION['uid']]);

				if ($row = $sth->fetch()) {
					$profile_id = $row['id'];

					if ($profile_id) {
						initialize_user_prefs($_SESSION["uid"], $profile_id);
					}
				}
			}

			$this->pdo->commit();
		}
	}

	function saveprofile() {
		$id = clean($_REQUEST["id"]);
		$title = trim(clean($_REQUEST["value"]));

		if ($id == 0) {
			print __("Default profile");
			return;
		}

		if ($title) {
			$sth = $this->pdo->prepare("UPDATE ttrss_settings_profiles
				SET title = ? WHERE id = ? AND
					owner_uid = ?");

			$sth->execute([$title, $id, $_SESSION['uid']]);
			print $title;
		}
	}

	// Silent
	function remarchive() {
		$ids = explode(",", clean($_REQUEST["ids"]));

		$sth = $this->pdo->prepare("DELETE FROM ttrss_archived_feeds WHERE
		  		(SELECT COUNT(*) FROM ttrss_user_entries
					WHERE orig_feed_id = :id) = 0 AND
						id = :id AND owner_uid = :uid");

		foreach ($ids as $id) {
			$sth->execute([":id" => $id, ":uid" => $_SESSION['uid']]);
		}
	}

	function addfeed() {
		$feed = clean($_REQUEST['feed']);
		$cat = clean($_REQUEST['cat']);
		$need_auth = isset($_REQUEST['need_auth']);
		$login = $need_auth ? clean($_REQUEST['login']) : '';
		$pass = $need_auth ? trim(clean($_REQUEST['pass'])) : '';

		$rc = Feeds::subscribe_to_feed($feed, $cat, $login, $pass);

		print json_encode(array("result" => $rc));
	}

	function togglepref() {
		$key = clean($_REQUEST["key"]);
		set_pref($key, !get_pref($key));
		$value = get_pref($key);

		print json_encode(array("param" =>$key, "value" => $value));
	}

	function setpref() {
		// set_pref escapes input, so no need to double escape it here
		$key = clean($_REQUEST['key']);
		$value = $_REQUEST['value'];

		set_pref($key, $value, false, $key != 'USER_STYLESHEET');

		print json_encode(array("param" =>$key, "value" => $value));
	}

	function mark() {
		$mark = clean($_REQUEST["mark"]);
		$id = clean($_REQUEST["id"]);

		$sth = $this->pdo->prepare("UPDATE ttrss_user_entries SET marked = ?,
					last_marked = NOW()
					WHERE ref_id = ? AND owner_uid = ?");

		$sth->execute([$mark, $id, $_SESSION['uid']]);

		print json_encode(array("message" => "UPDATE_COUNTERS"));
	}

	function delete() {
		$ids = explode(",", clean($_REQUEST["ids"]));
		$ids_qmarks = arr_qmarks($ids);

		$sth = $this->pdo->prepare("DELETE FROM ttrss_user_entries
			WHERE ref_id IN ($ids_qmarks) AND owner_uid = ?");
		$sth->execute(array_merge($ids, [$_SESSION['uid']]));

		Article::purge_orphans();

		print json_encode(array("message" => "UPDATE_COUNTERS"));
	}

	function unarchive() {
		$ids = explode(",", clean($_REQUEST["ids"]));

		foreach ($ids as $id) {
			$this->pdo->beginTransaction();

			$sth = $this->pdo->prepare("SELECT feed_url,site_url,title FROM ttrss_archived_feeds
				WHERE id = (SELECT orig_feed_id FROM ttrss_user_entries WHERE ref_id = :id
				AND owner_uid = :uid) AND owner_uid = :uid");
			$sth->execute([":uid" => $_SESSION['uid'], ":id" => $id]);

			if ($row = $sth->fetch()) {
				$feed_url = $row['feed_url'];
				$site_url = $row['site_url'];
				$title = $row['title'];

				$sth = $this->pdo->prepare("SELECT id FROM ttrss_feeds WHERE feed_url = ?
					AND owner_uid = ?");
				$sth->execute([$feed_url, $_SESSION['uid']]);

				if ($row = $sth->fetch()) {
					$feed_id = $row["id"];
				} else {
					if (!$title) $title = '[Unknown]';

					$sth = $this->pdo->prepare("INSERT INTO ttrss_feeds
							(owner_uid,feed_url,site_url,title,cat_id,auth_login,auth_pass,update_method)
							VALUES (?, ?, ?, ?, NULL, '', '', 0)");
					$sth->execute([$_SESSION['uid'], $feed_url, $site_url, $title]);

					$sth = $this->pdo->prepare("SELECT id FROM ttrss_feeds WHERE feed_url = ?
						AND owner_uid = ?");
					$sth->execute([$feed_url, $_SESSION['uid']]);

					if ($row = $sth->fetch()) {
						$feed_id = $row['id'];
					}
				}

				if ($feed_id) {
					$sth = $this->pdo->prepare("UPDATE ttrss_user_entries
						SET feed_id = ?, orig_feed_id = NULL
						WHERE ref_id = ? AND owner_uid = ?");
					$sth->execute([$feed_id, $id, $_SESSION['uid']]);
				}
			}

			$this->pdo->commit();
		}

		print json_encode(array("message" => "UPDATE_COUNTERS"));
	}

	function archive() {
		$ids = explode(",", clean($_REQUEST["ids"]));

		foreach ($ids as $id) {
			$this->archive_article($id, $_SESSION["uid"]);
		}

		print json_encode(array("message" => "UPDATE_COUNTERS"));
	}

	private function archive_article($id, $owner_uid) {
		$this->pdo->beginTransaction();

		if (!$owner_uid) $owner_uid = $_SESSION['uid'];

		$sth = $this->pdo->prepare("SELECT feed_id FROM ttrss_user_entries
			WHERE ref_id = ? AND owner_uid = ?");
		$sth->execute([$id, $owner_uid]);

		if ($row = $sth->fetch()) {

			/* prepare the archived table */

			$feed_id = (int) $row['feed_id'];

			if ($feed_id) {
				$sth = $this->pdo->prepare("SELECT id FROM ttrss_archived_feeds
					WHERE id = ? AND owner_uid = ?");
				$sth->execute([$feed_id, $owner_uid]);

				if ($row = $sth->fetch()) {
					$new_feed_id = $row['id'];
				} else {
					$row = $this->pdo->query("SELECT MAX(id) AS id FROM ttrss_archived_feeds")->fetch();
					$new_feed_id = (int)$row['id'] + 1;

					$sth = $this->pdo->prepare("INSERT INTO ttrss_archived_feeds
						(id, owner_uid, title, feed_url, site_url)
							SELECT ?, owner_uid, title, feed_url, site_url from ttrss_feeds
							  	WHERE id = ?");

					$sth->execute([$new_feed_id, $feed_id]);
				}

				$sth = $this->pdo->prepare("UPDATE ttrss_user_entries
					SET orig_feed_id = ?, feed_id = NULL
					WHERE ref_id = ? AND owner_uid = ?");
				$sth->execute([$new_feed_id, $id, $owner_uid]);
			}
		}

		$this->pdo->commit();
	}

	function publ() {
		$pub = clean($_REQUEST["pub"]);
		$id = clean($_REQUEST["id"]);

		$sth = $this->pdo->prepare("UPDATE ttrss_user_entries SET
			published = ?, last_published = NOW()
			WHERE ref_id = ? AND owner_uid = ?");

		$sth->execute([$pub, $id, $_SESSION['uid']]);

		print json_encode(array("message" => "UPDATE_COUNTERS"));
	}

	function getAllCounters() {
		@$seq = (int) $_REQUEST['seq'];

		$reply = [
			'counters' => Counters::getAllCounters(),
			'seq' => $seq
		];

		if ($seq % 2 == 0)
			$reply['runtime-info'] = make_runtime_info();

		print json_encode($reply);
	}

	/* GET["cmode"] = 0 - mark as read, 1 - as unread, 2 - toggle */
	function catchupSelected() {
		$ids = explode(",", clean($_REQUEST["ids"]));
		$cmode = sprintf("%d", clean($_REQUEST["cmode"]));

		Article::catchupArticlesById($ids, $cmode);

		print json_encode(array("message" => "UPDATE_COUNTERS", "ids" => $ids));
	}

	function markSelected() {
		$ids = explode(",", clean($_REQUEST["ids"]));
		$cmode = (int)clean($_REQUEST["cmode"]);

		$this->markArticlesById($ids, $cmode);

		print json_encode(array("message" => "UPDATE_COUNTERS"));
	}

	function publishSelected() {
		$ids = explode(",", clean($_REQUEST["ids"]));
		$cmode = (int)clean($_REQUEST["cmode"]);

		$this->publishArticlesById($ids, $cmode);

		print json_encode(array("message" => "UPDATE_COUNTERS"));
	}

	function sanityCheck() {
		$_SESSION["hasAudio"] = clean($_REQUEST["hasAudio"]) === "true";
		$_SESSION["hasSandbox"] = clean($_REQUEST["hasSandbox"]) === "true";
		$_SESSION["hasMp3"] = clean($_REQUEST["hasMp3"]) === "true";
		$_SESSION["clientTzOffset"] = clean($_REQUEST["clientTzOffset"]);

		$reply = array();

		$reply['error'] = sanity_check();

		if ($reply['error']['code'] == 0) {
			$reply['init-params'] = make_init_params();
			$reply['runtime-info'] = make_runtime_info();
		}

		print json_encode($reply);
	}

	function completeLabels() {
		$search = clean($_REQUEST["search"]);

		$sth = $this->pdo->prepare("SELECT DISTINCT caption FROM
				ttrss_labels2
				WHERE owner_uid = ? AND
				LOWER(caption) LIKE LOWER(?) ORDER BY caption
				LIMIT 5");
		$sth->execute([$_SESSION['uid'], "%$search%"]);

		print "<ul>";
		while ($line = $sth->fetch()) {
			print "<li>" . $line["caption"] . "</li>";
		}
		print "</ul>";
	}

	function updateFeedBrowser() {
		if (defined('_DISABLE_FEED_BROWSER') && _DISABLE_FEED_BROWSER) return;

		$search = clean($_REQUEST["search"]);
		$limit = clean($_REQUEST["limit"]);
		$mode = (int) clean($_REQUEST["mode"]);

		require_once "feedbrowser.php";

		print json_encode(array("content" =>
			make_feed_browser($search, $limit, $mode),
				"mode" => $mode));
	}

	// Silent
	function massSubscribe() {

		$payload = json_decode(clean($_REQUEST["payload"]), false);
		$mode = clean($_REQUEST["mode"]);

		if (!$payload || !is_array($payload)) return;

		if ($mode == 1) {
			foreach ($payload as $feed) {

				$title = $feed[0];
				$feed_url = $feed[1];

				$sth = $this->pdo->prepare("SELECT id FROM ttrss_feeds WHERE
					feed_url = ? AND owner_uid = ?");
				$sth->execute([$feed_url, $_SESSION['uid']]);

				if (!$sth->fetch()) {
					$sth = $this->pdo->prepare("INSERT INTO ttrss_feeds
									(owner_uid,feed_url,title,cat_id,site_url)
									VALUES (?, ?, ?, NULL, '')");

					$sth->execute([$_SESSION['uid'], $feed_url, $title]);
				}
			}
		} else if ($mode == 2) {
			// feed archive
			foreach ($payload as $id) {
				$sth = $this->pdo->prepare("SELECT * FROM ttrss_archived_feeds
					WHERE id = ? AND owner_uid = ?");
				$sth->execute([$id, $_SESSION['uid']]);

				if ($row = $sth->fetch()) {
					$site_url = $row['site_url'];
					$feed_url = $row['feed_url'];
					$title = $row['title'];

					$sth = $this->pdo->prepare("SELECT id FROM ttrss_feeds WHERE
						feed_url = ? AND owner_uid = ?");
					$sth->execute([$feed_url, $_SESSION['uid']]);

					if (!$sth->fetch()) {
						$sth = $this->pdo->prepare("INSERT INTO ttrss_feeds
								(owner_uid,feed_url,title,cat_id,site_url)
									VALUES (?, ?, ?, NULL, ?)");

						$sth->execute([$_SESSION['uid'], $feed_url, $title, $site_url]);
					}
				}
			}
		}
	}

	function catchupFeed() {
		$feed_id = clean($_REQUEST['feed_id']);
		$is_cat = clean($_REQUEST['is_cat']) == "true";
		$mode = clean($_REQUEST['mode']);
		$search_query = clean($_REQUEST['search_query']);
		$search_lang = clean($_REQUEST['search_lang']);

		Feeds::catchup_feed($feed_id, $is_cat, false, $mode, [$search_query, $search_lang]);

		// return counters here synchronously so that frontend can figure out next unread feed properly
		print json_encode(['counters' => Counters::getAllCounters()]);

		//print json_encode(array("message" => "UPDATE_COUNTERS"));
	}

	function setpanelmode() {
		$wide = (int) clean($_REQUEST["wide"]);

		setcookie("ttrss_widescreen", $wide,
			time() + COOKIE_LIFETIME_LONG);

		print json_encode(array("wide" => $wide));
	}

	static function updaterandomfeed_real() {

		// Test if the feed need a update (update interval exceded).
		if (DB_TYPE == "pgsql") {
			$update_limit_qpart = "AND ((
					ttrss_feeds.update_interval = 0
					AND ttrss_feeds.last_updated < NOW() - CAST((ttrss_user_prefs.value || ' minutes') AS INTERVAL)
				) OR (
					ttrss_feeds.update_interval > 0
					AND ttrss_feeds.last_updated < NOW() - CAST((ttrss_feeds.update_interval || ' minutes') AS INTERVAL)
				) OR ttrss_feeds.last_updated IS NULL
				OR last_updated = '1970-01-01 00:00:00')";
		} else {
			$update_limit_qpart = "AND ((
					ttrss_feeds.update_interval = 0
					AND ttrss_feeds.last_updated < DATE_SUB(NOW(), INTERVAL CONVERT(ttrss_user_prefs.value, SIGNED INTEGER) MINUTE)
				) OR (
					ttrss_feeds.update_interval > 0
					AND ttrss_feeds.last_updated < DATE_SUB(NOW(), INTERVAL ttrss_feeds.update_interval MINUTE)
				) OR ttrss_feeds.last_updated IS NULL
				OR last_updated = '1970-01-01 00:00:00')";
		}

		// Test if feed is currently being updated by another process.
		if (DB_TYPE == "pgsql") {
			$updstart_thresh_qpart = "AND (ttrss_feeds.last_update_started IS NULL OR ttrss_feeds.last_update_started < NOW() - INTERVAL '5 minutes')";
		} else {
			$updstart_thresh_qpart = "AND (ttrss_feeds.last_update_started IS NULL OR ttrss_feeds.last_update_started < DATE_SUB(NOW(), INTERVAL 5 MINUTE))";
		}

		$random_qpart = sql_random_function();

		$pdo = Db::pdo();

		// we could be invoked from public.php with no active session
		if ($_SESSION["uid"]) {
			$owner_check_qpart = "AND ttrss_feeds.owner_uid = ".$pdo->quote($_SESSION["uid"]);
		} else {
			$owner_check_qpart = "";
		}

		// We search for feed needing update.
		$res = $pdo->query("SELECT ttrss_feeds.feed_url,ttrss_feeds.id
			FROM
				ttrss_feeds, ttrss_users, ttrss_user_prefs
			WHERE
				ttrss_feeds.owner_uid = ttrss_users.id
				AND ttrss_users.id = ttrss_user_prefs.owner_uid
				AND ttrss_user_prefs.pref_name = 'DEFAULT_UPDATE_INTERVAL'
				$owner_check_qpart
				$update_limit_qpart
				$updstart_thresh_qpart
			ORDER BY $random_qpart LIMIT 30");

		$num_updated = 0;

		$tstart = time();

		while ($line = $res->fetch()) {
			$feed_id = $line["id"];

			if (time() - $tstart < ini_get("max_execution_time") * 0.7) {
				RSSUtils::update_rss_feed($feed_id, true);
				++$num_updated;
			} else {
				break;
			}
		}

		// Purge orphans and cleanup tags
		Article::purge_orphans();
		//cleanup_tags(14, 50000);

		if ($num_updated > 0) {
			print json_encode(array("message" => "UPDATE_COUNTERS",
				"num_updated" => $num_updated));
		} else {
			print json_encode(array("message" => "NOTHING_TO_UPDATE"));
		}

	}

	function updaterandomfeed() {
		RPC::updaterandomfeed_real();
	}

	private function markArticlesById($ids, $cmode) {

		$ids_qmarks = arr_qmarks($ids);

		if ($cmode == 0) {
			$sth = $this->pdo->prepare("UPDATE ttrss_user_entries SET
				marked = false, last_marked = NOW()
					WHERE ref_id IN ($ids_qmarks) AND owner_uid = ?");
		} else if ($cmode == 1) {
			$sth = $this->pdo->prepare("UPDATE ttrss_user_entries SET
				marked = true, last_marked = NOW()
					WHERE ref_id IN ($ids_qmarks) AND owner_uid = ?");
		} else {
			$sth = $this->pdo->prepare("UPDATE ttrss_user_entries SET
				marked = NOT marked,last_marked = NOW()
					WHERE ref_id IN ($ids_qmarks) AND owner_uid = ?");
		}

		$sth->execute(array_merge($ids, [$_SESSION['uid']]));
	}

	private function publishArticlesById($ids, $cmode) {

		$ids_qmarks = arr_qmarks($ids);

		if ($cmode == 0) {
			$sth = $this->pdo->prepare("UPDATE ttrss_user_entries SET
				published = false, last_published = NOW()
					WHERE ref_id IN ($ids_qmarks) AND owner_uid = ?");
		} else if ($cmode == 1) {
			$sth = $this->pdo->prepare("UPDATE ttrss_user_entries SET
				published = true, last_published = NOW()
					WHERE ref_id IN ($ids_qmarks) AND owner_uid = ?");
		} else {
			$sth = $this->pdo->prepare("UPDATE ttrss_user_entries SET
				published = NOT published,last_published = NOW()
					WHERE ref_id IN ($ids_qmarks) AND owner_uid = ?");
		}

		$sth->execute(array_merge($ids, [$_SESSION['uid']]));
	}

	function getlinktitlebyid() {
		$id = clean($_REQUEST['id']);

		$sth = $this->pdo->prepare("SELECT link, title FROM ttrss_entries, ttrss_user_entries
			WHERE ref_id = ? AND ref_id = id AND owner_uid = ?");
		$sth->execute([$id, $_SESSION['uid']]);

		if ($row = $sth->fetch()) {
			$link = $row['link'];
			$title = $row['title'];

			echo json_encode(array("link" => $link, "title" => $title));
		} else {
			echo json_encode(array("error" => "ARTICLE_NOT_FOUND"));
		}
	}

	function log() {
		$msg = clean($_REQUEST['msg']);
		$file = basename(clean($_REQUEST['file']));
		$line = (int) clean($_REQUEST['line']);
		$context = clean($_REQUEST['context']);

		if ($msg) {
			Logger::get()->log_error(E_USER_WARNING,
				$msg, 'client-js:' . $file, $line, $context);

			echo json_encode(array("message" => "HOST_ERROR_LOGGED"));
		} else {
			echo json_encode(array("error" => "MESSAGE_NOT_FOUND"));
		}

	}

	function checkforupdates() {
		$rv = [];

		if (CHECK_FOR_UPDATES && $_SESSION["access_level"] >= 10 && defined("GIT_VERSION_TIMESTAMP")) {
			$content = @fetch_file_contents(["url" => "https://tt-rss.org/version.json"]);

			if ($content) {
				$content = json_decode($content, true);

				if ($content && isset($content["changeset"])) {
					if ((int)GIT_VERSION_TIMESTAMP < (int)$content["changeset"]["timestamp"] &&
						GIT_VERSION_HEAD != $content["changeset"]["id"]) {

						$rv = $content["changeset"];
					}
				}
			}
		}

		print json_encode($rv);
	}

}
