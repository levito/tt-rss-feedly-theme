<?php
class RSSUtils {
	static function calculate_article_hash($article, $pluginhost) {
		$tmp = "";

		foreach ($article as $k => $v) {
			if ($k != "feed" && isset($v)) {
				$x = strip_tags(is_array($v) ? implode(",", $v) : $v);

				$tmp .= sha1("$k:" . sha1($x));
			}
		}

		return sha1(implode(",", $pluginhost->get_plugin_names()) . $tmp);
	}

	// Strips utf8mb4 characters (i.e. emoji) for mysql
	static function strip_utf8mb4($str) {
		return preg_replace('/[\x{10000}-\x{10FFFF}]/u', "\xEF\xBF\xBD", $str);
	}

	static function update_feedbrowser_cache() {

		$pdo = Db::pdo();

		$sth = $pdo->query("SELECT feed_url, site_url, title, COUNT(id) AS subscribers
			FROM ttrss_feeds WHERE feed_url NOT IN (SELECT feed_url FROM ttrss_feeds
				WHERE private IS true OR auth_login != '' OR auth_pass != '' OR feed_url LIKE '%:%@%/%')
				GROUP BY feed_url, site_url, title ORDER BY subscribers DESC LIMIT 1000");

		$pdo->beginTransaction();

		$pdo->query("DELETE FROM ttrss_feedbrowser_cache");

		$count = 0;

		while ($line = $sth->fetch()) {

			$subscribers = $line["subscribers"];
			$feed_url = $line["feed_url"];
			$title = $line["title"];
			$site_url = $line["site_url"];

			$tmph = $pdo->prepare("SELECT subscribers FROM
				ttrss_feedbrowser_cache WHERE feed_url = ?");
			$tmph->execute([$feed_url]);

			if (!$tmph->fetch()) {

				$tmph = $pdo->prepare("INSERT INTO ttrss_feedbrowser_cache
					(feed_url, site_url, title, subscribers)
					VALUES
					(?, ?, ?, ?)");

				$tmph->execute([$feed_url, $site_url, $title, $subscribers]);

				++$count;

			}

		}

		$pdo->commit();

		return $count;

	}

	static function update_daemon_common($limit = DAEMON_FEED_LIMIT) {
		$schema_version = get_schema_version();

		if ($schema_version != SCHEMA_VERSION) {
			die("Schema version is wrong, please upgrade the database.\n");
		}

		$pdo = Db::pdo();

		if (!SINGLE_USER_MODE && DAEMON_UPDATE_LOGIN_LIMIT > 0) {
			if (DB_TYPE == "pgsql") {
				$login_thresh_qpart = "AND ttrss_users.last_login >= NOW() - INTERVAL '".DAEMON_UPDATE_LOGIN_LIMIT." days'";
			} else {
				$login_thresh_qpart = "AND ttrss_users.last_login >= DATE_SUB(NOW(), INTERVAL ".DAEMON_UPDATE_LOGIN_LIMIT." DAY)";
			}
		} else {
			$login_thresh_qpart = "";
		}

		if (DB_TYPE == "pgsql") {
			$update_limit_qpart = "AND ((
					ttrss_feeds.update_interval = 0
					AND ttrss_user_prefs.value != '-1'
					AND ttrss_feeds.last_updated < NOW() - CAST((ttrss_user_prefs.value || ' minutes') AS INTERVAL)
				) OR (
					ttrss_feeds.update_interval > 0
					AND ttrss_feeds.last_updated < NOW() - CAST((ttrss_feeds.update_interval || ' minutes') AS INTERVAL)
				) OR (ttrss_feeds.last_updated IS NULL
					AND ttrss_user_prefs.value != '-1')
				OR (last_updated = '1970-01-01 00:00:00'
					AND ttrss_user_prefs.value != '-1'))";
		} else {
			$update_limit_qpart = "AND ((
					ttrss_feeds.update_interval = 0
					AND ttrss_user_prefs.value != '-1'
					AND ttrss_feeds.last_updated < DATE_SUB(NOW(), INTERVAL CONVERT(ttrss_user_prefs.value, SIGNED INTEGER) MINUTE)
				) OR (
					ttrss_feeds.update_interval > 0
					AND ttrss_feeds.last_updated < DATE_SUB(NOW(), INTERVAL ttrss_feeds.update_interval MINUTE)
				) OR (ttrss_feeds.last_updated IS NULL
					AND ttrss_user_prefs.value != '-1')
				OR (last_updated = '1970-01-01 00:00:00'
					AND ttrss_user_prefs.value != '-1'))";
		}

		// Test if feed is currently being updated by another process.
		if (DB_TYPE == "pgsql") {
			$updstart_thresh_qpart = "AND (ttrss_feeds.last_update_started IS NULL OR ttrss_feeds.last_update_started < NOW() - INTERVAL '10 minutes')";
		} else {
			$updstart_thresh_qpart = "AND (ttrss_feeds.last_update_started IS NULL OR ttrss_feeds.last_update_started < DATE_SUB(NOW(), INTERVAL 10 MINUTE))";
		}

		$query_limit = $limit ? sprintf("LIMIT %d", $limit) : "";

		// Update the least recently updated feeds first
		$query_order = "ORDER BY last_updated";
		if (DB_TYPE == "pgsql") $query_order .= " NULLS FIRST";

		$query = "SELECT DISTINCT ttrss_feeds.feed_url, ttrss_feeds.last_updated
			FROM
				ttrss_feeds, ttrss_users, ttrss_user_prefs
			WHERE
				ttrss_feeds.owner_uid = ttrss_users.id
				AND ttrss_user_prefs.profile IS NULL
				AND ttrss_users.id = ttrss_user_prefs.owner_uid
				AND ttrss_user_prefs.pref_name = 'DEFAULT_UPDATE_INTERVAL'
				$login_thresh_qpart $update_limit_qpart
				$updstart_thresh_qpart
				$query_order $query_limit";

		$res = $pdo->query($query);

		$feeds_to_update = array();
		while ($line = $res->fetch()) {
			array_push($feeds_to_update, $line['feed_url']);
		}

		Debug::log(sprintf("Scheduled %d feeds to update...", count($feeds_to_update)));

		// Update last_update_started before actually starting the batch
		// in order to minimize collision risk for parallel daemon tasks
		if (count($feeds_to_update) > 0) {
			$feeds_qmarks = arr_qmarks($feeds_to_update);

			$tmph = $pdo->prepare("UPDATE ttrss_feeds SET last_update_started = NOW()
				WHERE feed_url IN ($feeds_qmarks)");
			$tmph->execute($feeds_to_update);
		}

		$nf = 0;
		$bstarted = microtime(true);

		$batch_owners = array();

		// since we have the data cached, we can deal with other feeds with the same url
		$usth = $pdo->prepare("SELECT DISTINCT ttrss_feeds.id,last_updated,ttrss_feeds.owner_uid
			FROM ttrss_feeds, ttrss_users, ttrss_user_prefs WHERE
				ttrss_user_prefs.owner_uid = ttrss_feeds.owner_uid AND
				ttrss_users.id = ttrss_user_prefs.owner_uid AND
				ttrss_user_prefs.pref_name = 'DEFAULT_UPDATE_INTERVAL' AND
				ttrss_user_prefs.profile IS NULL AND
				feed_url = ?
				$update_limit_qpart
				$login_thresh_qpart
			ORDER BY ttrss_feeds.id $query_limit");

		foreach ($feeds_to_update as $feed) {
			Debug::log("Base feed: $feed");

			$usth->execute([$feed]);
			//update_rss_feed($line["id"], true);

			if ($tline = $usth->fetch()) {
				Debug::log(" => " . $tline["last_updated"] . ", " . $tline["id"] . " " . $tline["owner_uid"]);

				if (array_search($tline["owner_uid"], $batch_owners) === FALSE)
					array_push($batch_owners, $tline["owner_uid"]);

				$fstarted = microtime(true);

				try {
					RSSUtils::update_rss_feed($tline["id"], true, false);
				} catch (PDOException $e) {
					Logger::get()->log_error(E_USER_NOTICE, $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());

					try {
						$pdo->rollback();
					} catch (PDOException $e) {
						// it doesn't matter if there wasn't actually anything to rollback, PDO Exception can be
						// thrown outside of an active transaction during feed update
					}
				}

				Debug::log(sprintf("    %.4f (sec)", microtime(true) - $fstarted));

				++$nf;
			}
		}

		if ($nf > 0) {
			Debug::log(sprintf("Processed %d feeds in %.4f (sec), %.4f (sec/feed avg)", $nf,
				microtime(true) - $bstarted, (microtime(true) - $bstarted) / $nf));
		}

		foreach ($batch_owners as $owner_uid) {
			Debug::log("Running housekeeping tasks for user $owner_uid...");

			RSSUtils::housekeeping_user($owner_uid);
		}

		// Send feed digests by email if needed.
		Digest::send_headlines_digests();

		return $nf;
	}

	// this is used when subscribing
	static function set_basic_feed_info($feed) {

		$pdo = Db::pdo();

		$sth = $pdo->prepare("SELECT owner_uid,feed_url,auth_pass,auth_login
				FROM ttrss_feeds WHERE id = ?");
		$sth->execute([$feed]);

		if ($row = $sth->fetch()) {

			$owner_uid = $row["owner_uid"];
			$auth_login = $row["auth_login"];
			$auth_pass = $row["auth_pass"];
			$fetch_url = $row["feed_url"];

			$pluginhost = new PluginHost();
			$user_plugins = get_pref("_ENABLED_PLUGINS", $owner_uid);

			$pluginhost->load(PLUGINS, PluginHost::KIND_ALL);
			$pluginhost->load($user_plugins, PluginHost::KIND_USER, $owner_uid);
			$pluginhost->load_data();

			$basic_info = array();
			foreach ($pluginhost->get_hooks(PluginHost::HOOK_FEED_BASIC_INFO) as $plugin) {
				$basic_info = $plugin->hook_feed_basic_info($basic_info, $fetch_url, $owner_uid, $feed, $auth_login, $auth_pass);
			}

			if (!$basic_info) {
				$feed_data = fetch_file_contents($fetch_url, false,
					$auth_login, $auth_pass, false,
					FEED_FETCH_TIMEOUT,
					0);

				$feed_data = trim($feed_data);

				$rss = new FeedParser($feed_data);
				$rss->init();

				if (!$rss->error()) {
					$basic_info = array(
						'title' => mb_substr(clean($rss->get_title()), 0, 199),
						'site_url' => mb_substr(rewrite_relative_url($fetch_url, clean($rss->get_link())), 0, 245)
					);
				}
			}

			if ($basic_info && is_array($basic_info)) {
				$sth = $pdo->prepare("SELECT title, site_url FROM ttrss_feeds WHERE id = ?");
				$sth->execute([$feed]);

				if ($row = $sth->fetch()) {

					$registered_title = $row["title"];
					$orig_site_url = $row["site_url"];

					if ($basic_info['title'] && (!$registered_title || $registered_title == "[Unknown]")) {

						$sth = $pdo->prepare("UPDATE ttrss_feeds SET
							title = ? WHERE id = ?");
						$sth->execute([$basic_info['title'], $feed]);
					}

					if ($basic_info['site_url'] && $orig_site_url != $basic_info['site_url']) {
						$sth = $pdo->prepare("UPDATE ttrss_feeds SET
							site_url = ? WHERE id = ?");
						$sth->execute([$basic_info['site_url'], $feed]);
					}

				}
			}
		}
	}

	/**
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	static function update_rss_feed($feed, $no_cache = false) {

		Debug::log("start", Debug::$LOG_VERBOSE);

		$pdo = Db::pdo();

		$sth = $pdo->prepare("SELECT title FROM ttrss_feeds WHERE id = ?");
		$sth->execute([$feed]);

		if (!$row = $sth->fetch()) {
			Debug::log("feed $feed not found, skipping.");
			user_error("Attempt to update unknown/invalid feed $feed", E_USER_WARNING);
			return false;
		}

		$title = $row["title"];

		// feed was batch-subscribed or something, we need to get basic info
		// this is not optimal currently as it fetches stuff separately TODO: optimize
		if ($title == "[Unknown]") {
			Debug::log("setting basic feed info for $feed...");
			RSSUtils::set_basic_feed_info($feed);
		}

		$sth = $pdo->prepare("SELECT id,update_interval,auth_login,
			feed_url,auth_pass,cache_images,
			mark_unread_on_update, owner_uid,
			auth_pass_encrypted, feed_language,
			last_modified,
			".SUBSTRING_FOR_DATE."(last_unconditional, 1, 19) AS last_unconditional
			FROM ttrss_feeds WHERE id = ?");
		$sth->execute([$feed]);

		if ($row = $sth->fetch()) {

			$owner_uid = $row["owner_uid"];
			$mark_unread_on_update = $row["mark_unread_on_update"];

			$sth = $pdo->prepare("UPDATE ttrss_feeds SET last_update_started = NOW()
				WHERE id = ?");
			$sth->execute([$feed]);

			$auth_login = $row["auth_login"];
			$auth_pass = $row["auth_pass"];
			$stored_last_modified = $row["last_modified"];
			$last_unconditional = $row["last_unconditional"];
			$cache_images = $row["cache_images"];
			$fetch_url = $row["feed_url"];

			$feed_language = mb_strtolower($row["feed_language"]);
			if (!$feed_language) $feed_language = 'english';

		} else {
			return false;
		}

		$date_feed_processed = date('Y-m-d H:i');

		$cache_filename = CACHE_DIR . "/feeds/" . sha1($fetch_url) . ".xml";

		$pluginhost = new PluginHost();
		$user_plugins = get_pref("_ENABLED_PLUGINS", $owner_uid);

		$pluginhost->load(PLUGINS, PluginHost::KIND_ALL);
		$pluginhost->load($user_plugins, PluginHost::KIND_USER, $owner_uid);
		$pluginhost->load_data();

		$rss_hash = false;

		$force_refetch = isset($_REQUEST["force_refetch"]);
		$feed_data = "";

		foreach ($pluginhost->get_hooks(PluginHost::HOOK_FETCH_FEED) as $plugin) {
			$feed_data = $plugin->hook_fetch_feed($feed_data, $fetch_url, $owner_uid, $feed, 0, $auth_login, $auth_pass);
		}

		// try cache
		if (!$feed_data &&
			file_exists($cache_filename) &&
			is_readable($cache_filename) &&
			!$auth_login && !$auth_pass &&
			filemtime($cache_filename) > time() - 30) {

			Debug::log("using local cache [$cache_filename].", Debug::$LOG_VERBOSE);

			@$feed_data = file_get_contents($cache_filename);

			if ($feed_data) {
				$rss_hash = sha1($feed_data);
			}

		} else {
			Debug::log("local cache will not be used for this feed", Debug::$LOG_VERBOSE);
		}

		global $fetch_last_modified;

		// fetch feed from source
		if (!$feed_data) {
			Debug::log("last unconditional update request: $last_unconditional", Debug::$LOG_VERBOSE);

			if (ini_get("open_basedir") && function_exists("curl_init")) {
				Debug::log("not using CURL due to open_basedir restrictions", Debug::$LOG_VERBOSE);
			}

			if (time() - strtotime($last_unconditional) > MAX_CONDITIONAL_INTERVAL) {
				Debug::log("maximum allowed interval for conditional requests exceeded, forcing refetch", Debug::$LOG_VERBOSE);

				$force_refetch = true;
			} else {
				Debug::log("stored last modified for conditional request: $stored_last_modified", Debug::$LOG_VERBOSE);
			}

			Debug::log("fetching [$fetch_url] (force_refetch: $force_refetch)...", Debug::$LOG_VERBOSE);

			$feed_data = fetch_file_contents([
				"url" => $fetch_url,
				"login" => $auth_login,
				"pass" => $auth_pass,
				"timeout" => $no_cache ? FEED_FETCH_NO_CACHE_TIMEOUT : FEED_FETCH_TIMEOUT,
				"last_modified" => $force_refetch ? "" : $stored_last_modified
			]);

			$feed_data = trim($feed_data);

			Debug::log("fetch done.", Debug::$LOG_VERBOSE);
			Debug::log("source last modified: " . $fetch_last_modified, Debug::$LOG_VERBOSE);

			if ($feed_data && $fetch_last_modified != $stored_last_modified) {
				$sth = $pdo->prepare("UPDATE ttrss_feeds SET last_modified = ? WHERE id = ?");
				$sth->execute([substr($fetch_last_modified, 0, 245), $feed]);
			}

			// cache vanilla feed data for re-use
			if ($feed_data && !$auth_pass && !$auth_login && is_writable(CACHE_DIR . "/feeds")) {
				$new_rss_hash = sha1($feed_data);

				if ($new_rss_hash != $rss_hash) {
					Debug::log("saving $cache_filename", Debug::$LOG_VERBOSE);
					@file_put_contents($cache_filename, $feed_data);
				}
			}
		}

		if (!$feed_data) {
			global $fetch_last_error;
			global $fetch_last_error_code;

			Debug::log("unable to fetch: $fetch_last_error [$fetch_last_error_code]", Debug::$LOG_VERBOSE);

			// If-Modified-Since
			if ($fetch_last_error_code != 304) {
				$error_message = $fetch_last_error;
			} else {
				Debug::log("source claims data not modified, nothing to do.", Debug::$LOG_VERBOSE);
				$error_message = "";
			}

			$sth = $pdo->prepare("UPDATE ttrss_feeds SET last_error = ?,
					last_updated = NOW() WHERE id = ?");
			$sth->execute([$error_message, $feed]);

			return;
		}

		foreach ($pluginhost->get_hooks(PluginHost::HOOK_FEED_FETCHED) as $plugin) {
			$feed_data = $plugin->hook_feed_fetched($feed_data, $fetch_url, $owner_uid, $feed);
		}

		$rss = new FeedParser($feed_data);
		$rss->init();

		if (!$rss->error()) {

			// We use local pluginhost here because we need to load different per-user feed plugins
			$pluginhost->run_hooks(PluginHost::HOOK_FEED_PARSED, "hook_feed_parsed", $rss);

			Debug::log("language: $feed_language", Debug::$LOG_VERBOSE);
			Debug::log("processing feed data...", Debug::$LOG_VERBOSE);

			if (DB_TYPE == "pgsql") {
				$favicon_interval_qpart = "favicon_last_checked < NOW() - INTERVAL '12 hour'";
			} else {
				$favicon_interval_qpart = "favicon_last_checked < DATE_SUB(NOW(), INTERVAL 12 HOUR)";
			}

			$sth = $pdo->prepare("SELECT owner_uid,favicon_avg_color,
				(favicon_last_checked IS NULL OR $favicon_interval_qpart) AS
						favicon_needs_check
				FROM ttrss_feeds WHERE id = ?");
			$sth->execute([$feed]);

			if ($row = $sth->fetch()) {
				$favicon_needs_check = $row["favicon_needs_check"];
				$favicon_avg_color = $row["favicon_avg_color"];
				$owner_uid = $row["owner_uid"];
			} else {
				return false;
			}

			$site_url = mb_substr(rewrite_relative_url($fetch_url, clean($rss->get_link())), 0, 245);

			Debug::log("site_url: $site_url", Debug::$LOG_VERBOSE);
			Debug::log("feed_title: " . clean($rss->get_title()), Debug::$LOG_VERBOSE);

			if ($favicon_needs_check || $force_refetch) {

				/* terrible hack: if we crash on floicon shit here, we won't check
				 * the icon avgcolor again (unless the icon got updated) */

				$favicon_file = ICONS_DIR . "/$feed.ico";
				$favicon_modified = @filemtime($favicon_file);

				Debug::log("checking favicon...", Debug::$LOG_VERBOSE);

				RSSUtils::check_feed_favicon($site_url, $feed);
				$favicon_modified_new = @filemtime($favicon_file);

				if ($favicon_modified_new > $favicon_modified)
					$favicon_avg_color = '';

				$favicon_colorstring = "";
				if (file_exists($favicon_file) && function_exists("imagecreatefromstring") && $favicon_avg_color == '') {
					require_once "colors.php";

					$sth = $pdo->prepare("UPDATE ttrss_feeds SET favicon_avg_color = 'fail' WHERE
							id = ?");
					$sth->execute([$feed]);

					$favicon_color = calculate_avg_color($favicon_file);

					$favicon_colorstring = ",favicon_avg_color = " . $pdo->quote($favicon_color);

				} else if ($favicon_avg_color == 'fail') {
					Debug::log("floicon failed on this file, not trying to recalculate avg color", Debug::$LOG_VERBOSE);
				}

				$sth = $pdo->prepare("UPDATE ttrss_feeds SET favicon_last_checked = NOW()
					$favicon_colorstring WHERE id = ?");
				$sth->execute([$feed]);
			}

			Debug::log("loading filters & labels...", Debug::$LOG_VERBOSE);

			$filters = load_filters($feed, $owner_uid);

			if (Debug::get_loglevel() >= Debug::$LOG_EXTENDED) {
				print_r($filters);
			}

			Debug::log("" . count($filters) . " filters loaded.", Debug::$LOG_VERBOSE);

			$items = $rss->get_items();

			if (!is_array($items)) {
				Debug::log("no articles found.", Debug::$LOG_VERBOSE);

				$sth = $pdo->prepare("UPDATE ttrss_feeds
					SET last_updated = NOW(), last_unconditional = NOW(), last_error = '' WHERE id = ?");
				$sth->execute([$feed]);

				return true; // no articles
			}

			Debug::log("processing articles...", Debug::$LOG_VERBOSE);

			$tstart = time();

			foreach ($items as $item) {
				$pdo->beginTransaction();

				if (Debug::get_loglevel() >= 3) {
					print_r($item);
				}

				if (ini_get("max_execution_time") > 0 && time() - $tstart >= ini_get("max_execution_time") * 0.7) {
					Debug::log("looks like there's too many articles to process at once, breaking out", Debug::$LOG_VERBOSE);
					$pdo->commit();
					break;
				}

				$entry_guid = strip_tags($item->get_id());
				if (!$entry_guid) $entry_guid = strip_tags($item->get_link());
				if (!$entry_guid) $entry_guid = RSSUtils::make_guid_from_title($item->get_title());

				if (!$entry_guid) {
					$pdo->commit();
					continue;
				}

				$entry_guid = "$owner_uid,$entry_guid";

				$entry_guid_hashed = 'SHA1:' . sha1($entry_guid);

				Debug::log("guid $entry_guid / $entry_guid_hashed", Debug::$LOG_VERBOSE);

				$entry_timestamp = strip_tags($item->get_date());

				Debug::log("orig date: " . $item->get_date(), Debug::$LOG_VERBOSE);

				if ($entry_timestamp == -1 || !$entry_timestamp || $entry_timestamp > time()) {
					$entry_timestamp = time();
				}

				$entry_timestamp_fmt = strftime("%Y/%m/%d %H:%M:%S", $entry_timestamp);

				Debug::log("date $entry_timestamp [$entry_timestamp_fmt]", Debug::$LOG_VERBOSE);

				$entry_title = strip_tags($item->get_title());

				$entry_link = rewrite_relative_url($site_url, clean($item->get_link()));

				$entry_language = mb_substr(trim($item->get_language()), 0, 2);

				Debug::log("title $entry_title", Debug::$LOG_VERBOSE);
				Debug::log("link $entry_link", Debug::$LOG_VERBOSE);
				Debug::log("language $entry_language", Debug::$LOG_VERBOSE);

				if (!$entry_title) $entry_title = date("Y-m-d H:i:s", $entry_timestamp);;

				$entry_content = $item->get_content();
				if (!$entry_content) $entry_content = $item->get_description();

				if (Debug::get_loglevel() >= 3) {
					print "content: ";
					print htmlspecialchars($entry_content);
					print "\n";
				}

				$entry_comments = mb_substr(strip_tags($item->get_comments_url()), 0, 245);
				$num_comments = (int) $item->get_comments_count();

				$entry_author = strip_tags($item->get_author());
				$entry_guid = mb_substr($entry_guid, 0, 245);

				Debug::log("author $entry_author", Debug::$LOG_VERBOSE);
				Debug::log("num_comments: $num_comments", Debug::$LOG_VERBOSE);
				Debug::log("looking for tags...", Debug::$LOG_VERBOSE);

				// parse <category> entries into tags

				$additional_tags = array();

				$additional_tags_src = $item->get_categories();

				if (is_array($additional_tags_src)) {
					foreach ($additional_tags_src as $tobj) {
						array_push($additional_tags, $tobj);
					}
				}

				$entry_tags = array_unique($additional_tags);

				for ($i = 0; $i < count($entry_tags); $i++) {
					$entry_tags[$i] = mb_strtolower($entry_tags[$i], 'utf-8');

					// we don't support numeric tags, let's prefix them
					if (is_numeric($entry_tags[$i])) $entry_tags[$i] = 't:' . $entry_tags[$i];
				}

				Debug::log("tags found: " . join(",", $entry_tags), Debug::$LOG_VERBOSE);

				Debug::log("done collecting data.", Debug::$LOG_VERBOSE);

				$sth = $pdo->prepare("SELECT id, content_hash, lang FROM ttrss_entries
					WHERE guid = ? OR guid = ?");
				$sth->execute([$entry_guid, $entry_guid_hashed]);

				if ($row = $sth->fetch()) {
					$base_entry_id = $row["id"];
					$entry_stored_hash = $row["content_hash"];
					$article_labels = Article::get_article_labels($base_entry_id, $owner_uid);

					$existing_tags = Article::get_article_tags($base_entry_id, $owner_uid);
					$entry_tags = array_unique(array_merge($entry_tags, $existing_tags));
				} else {
					$base_entry_id = false;
					$entry_stored_hash = "";
					$article_labels = array();
				}

				$article = array("owner_uid" => $owner_uid, // read only
					"guid" => $entry_guid, // read only
					"guid_hashed" => $entry_guid_hashed, // read only
					"title" => $entry_title,
					"content" => $entry_content,
					"link" => $entry_link,
					"labels" => $article_labels, // current limitation: can add labels to article, can't remove them
					"tags" => $entry_tags,
					"author" => $entry_author,
					"force_catchup" => false, // ugly hack for the time being
					"score_modifier" => 0, // no previous value, plugin should recalculate score modifier based on content if needed
					"language" => $entry_language,
					"num_comments" => $num_comments, // read only
					"feed" => array("id" => $feed,
						"fetch_url" => $fetch_url,
						"site_url" => $site_url,
						"cache_images" => $cache_images)
				);

				$entry_plugin_data = "";
				$entry_current_hash = RSSUtils::calculate_article_hash($article, $pluginhost);

				Debug::log("article hash: $entry_current_hash [stored=$entry_stored_hash]", Debug::$LOG_VERBOSE);

				if ($entry_current_hash == $entry_stored_hash && !isset($_REQUEST["force_rehash"])) {
					Debug::log("stored article seems up to date [IID: $base_entry_id], updating timestamp only", Debug::$LOG_VERBOSE);

					// we keep encountering the entry in feeds, so we need to
					// update date_updated column so that we don't get horrible
					// dupes when the entry gets purged and reinserted again e.g.
					// in the case of SLOW SLOW OMG SLOW updating feeds

					$sth = $pdo->prepare("UPDATE ttrss_entries SET date_updated = NOW()
						WHERE id = ?");
					$sth->execute([$base_entry_id]);

					$pdo->commit();
					continue;
				}

				Debug::log("hash differs, applying plugin filters:", Debug::$LOG_VERBOSE);

				foreach ($pluginhost->get_hooks(PluginHost::HOOK_ARTICLE_FILTER) as $plugin) {
					Debug::log("... " . get_class($plugin), Debug::$LOG_VERBOSE);

					$start = microtime(true);
					$article = $plugin->hook_article_filter($article);

					Debug::log(sprintf("=== %.4f (sec)", microtime(true) - $start), Debug::$LOG_VERBOSE);

					$entry_plugin_data .= mb_strtolower(get_class($plugin)) . ",";
				}

                if (Debug::get_loglevel() >= 3) {
					print "processed content: ";
					print htmlspecialchars($article["content"]);
					print "\n";
				}

				Debug::log("plugin data: $entry_plugin_data", Debug::$LOG_VERBOSE);

				// Workaround: 4-byte unicode requires utf8mb4 in MySQL. See https://tt-rss.org/forum/viewtopic.php?f=1&t=3377&p=20077#p20077
				if (DB_TYPE == "mysql" && MYSQL_CHARSET != "UTF8MB4") {
					foreach ($article as $k => $v) {
						// i guess we'll have to take the risk of 4byte unicode labels & tags here
						if (is_string($article[$k])) {
							$article[$k] = RSSUtils::strip_utf8mb4($v);
						}
					}
				}

				/* Collect article tags here so we could filter by them: */

				$matched_rules = [];
				$matched_filters = [];

				$article_filters = RSSUtils::get_article_filters($filters, $article["title"],
					$article["content"], $article["link"], $article["author"],
					$article["tags"], $matched_rules, $matched_filters);

				// $article_filters should be renamed to something like $filter_actions; actual filter objects are in $matched_filters
				foreach ($pluginhost->get_hooks(PluginHost::HOOK_FILTER_TRIGGERED) as $plugin) {
					$plugin->hook_filter_triggered($feed, $owner_uid, $article, $matched_filters, $matched_rules, $article_filters);
				}

				$matched_filter_ids = array_map(function($f) { return $f['id']; }, $matched_filters);

				if (count($matched_filter_ids) > 0) {
					$filter_ids_qmarks = arr_qmarks($matched_filter_ids);

					$fsth = $pdo->prepare("UPDATE ttrss_filters2 SET last_triggered = NOW() WHERE 
							   id IN ($filter_ids_qmarks) AND owner_uid = ?");

					$fsth->execute(array_merge($matched_filter_ids, [$owner_uid]));
				}

				if (Debug::get_loglevel() >= Debug::$LOG_EXTENDED) {
					Debug::log("matched filters: ", Debug::$LOG_VERBOSE);

					if (count($matched_filters != 0)) {
						print_r($matched_filters);
					}

					Debug::log("matched filter rules: ", Debug::$LOG_VERBOSE);

					if (count($matched_rules) != 0) {
						print_r($matched_rules);
					}

					Debug::log("filter actions: ", Debug::$LOG_VERBOSE);

					if (count($article_filters) != 0) {
						print_r($article_filters);
					}
				}

				$plugin_filter_names = RSSUtils::find_article_filters($article_filters, "plugin");
				$plugin_filter_actions = $pluginhost->get_filter_actions();

				if (count($plugin_filter_names) > 0) {
					Debug::log("applying plugin filter actions...", Debug::$LOG_VERBOSE);

					foreach ($plugin_filter_names as $pfn) {
						list($pfclass,$pfaction) = explode(":", $pfn["param"]);

						if (isset($plugin_filter_actions[$pfclass])) {
							$plugin = $pluginhost->get_plugin($pfclass);

							Debug::log("... $pfclass: $pfaction", Debug::$LOG_VERBOSE);

							if ($plugin) {
								$start = microtime(true);
								$article = $plugin->hook_article_filter_action($article, $pfaction);

								Debug::log(sprintf("=== %.4f (sec)", microtime(true) - $start), Debug::$LOG_VERBOSE);
							} else {
								Debug::log("??? $pfclass: plugin object not found.", Debug::$LOG_VERBOSE);
							}
						} else {
							Debug::log("??? $pfclass: filter plugin not registered.", Debug::$LOG_VERBOSE);
						}
					}
				}

				$entry_tags = $article["tags"];
				$entry_title = strip_tags($article["title"]);
				$entry_author = mb_substr(strip_tags($article["author"]), 0, 245);
				$entry_link = strip_tags($article["link"]);
				$entry_content = $article["content"]; // escaped below
				$entry_force_catchup = $article["force_catchup"];
				$article_labels = $article["labels"];
				$entry_score_modifier = (int) $article["score_modifier"];
				$entry_language = $article["language"];

				if (Debug::get_loglevel() >= Debug::$LOG_EXTENDED) {
					Debug::log("article labels:", Debug::$LOG_VERBOSE);

					if (count($article_labels) != 0) {
						print_r($article_labels);
					}
				}

				Debug::log("force catchup: $entry_force_catchup", Debug::$LOG_VERBOSE);

				if ($cache_images && is_writable(CACHE_DIR . '/images'))
					RSSUtils::cache_media($entry_content, $site_url);

				$csth = $pdo->prepare("SELECT id FROM ttrss_entries
					WHERE guid = ? OR guid = ?");
				$csth->execute([$entry_guid, $entry_guid_hashed]);

				if (!$row = $csth->fetch()) {

					Debug::log("base guid [$entry_guid or $entry_guid_hashed] not found, creating...", Debug::$LOG_VERBOSE);

					// base post entry does not exist, create it

					$usth = $pdo->prepare(
						"INSERT INTO ttrss_entries
							(title,
							guid,
							link,
							updated,
							content,
							content_hash,
							no_orig_date,
							date_updated,
							date_entered,
							comments,
							num_comments,
							plugin_data,
							lang,
							author)
						VALUES
							(?, ?, ?, ?, ?, ?,
							false,
							NOW(),
							?, ?, ?, ?,	?, ?)");

						$usth->execute([$entry_title,
							$entry_guid_hashed,
							$entry_link,
							$entry_timestamp_fmt,
							"$entry_content",
							$entry_current_hash,
							$date_feed_processed,
							$entry_comments,
							(int)$num_comments,
							$entry_plugin_data,
							"$entry_language",
							"$entry_author"]);

				}

				$csth->execute([$entry_guid, $entry_guid_hashed]);

				$entry_ref_id = 0;
				$entry_int_id = 0;

				if ($row = $csth->fetch()) {

					Debug::log("base guid found, checking for user record", Debug::$LOG_VERBOSE);

					$ref_id = $row['id'];
					$entry_ref_id = $ref_id;

					if (RSSUtils::find_article_filter($article_filters, "filter")) {
						Debug::log("article is filtered out, nothing to do.");
						$pdo->commit();
						continue;
					}

					$score = RSSUtils::calculate_article_score($article_filters) + $entry_score_modifier;

					Debug::log("initial score: $score [including plugin modifier: $entry_score_modifier]", Debug::$LOG_VERBOSE);

					// check for user post link to main table

					$sth = $pdo->prepare("SELECT ref_id, int_id FROM ttrss_user_entries WHERE
							ref_id = ? AND owner_uid = ?");
					$sth->execute([$ref_id, $owner_uid]);

					// okay it doesn't exist - create user entry
					if ($row = $sth->fetch()) {
						$entry_ref_id = $row["ref_id"];
						$entry_int_id = $row["int_id"];

						Debug::log("user record FOUND: RID: $entry_ref_id, IID: $entry_int_id", Debug::$LOG_VERBOSE);
					} else {

						Debug::log("user record not found, creating...", Debug::$LOG_VERBOSE);

						if ($score >= -500 && !RSSUtils::find_article_filter($article_filters, 'catchup') && !$entry_force_catchup) {
							$unread = 1;
							$last_read_qpart = null;
						} else {
							$unread = 0;
							$last_read_qpart = date("Y-m-d H:i"); // we can't use NOW() here because it gets quoted
						}

						if (RSSUtils::find_article_filter($article_filters, 'mark') || $score > 1000) {
							$marked = 1;
						} else {
							$marked = 0;
						}

						if (RSSUtils::find_article_filter($article_filters, 'publish')) {
							$published = 1;
						} else {
							$published = 0;
						}

						$last_marked = ($marked == 1) ? 'NOW()' : 'NULL';
						$last_published = ($published == 1) ? 'NOW()' : 'NULL';

						$sth = $pdo->prepare(
							"INSERT INTO ttrss_user_entries
								(ref_id, owner_uid, feed_id, unread, last_read, marked,
								published, score, tag_cache, label_cache, uuid,
								last_marked, last_published)
							VALUES (?, ?, ?, ?, ?, ?, ?, ?, '', '', '', ".$last_marked.", ".$last_published.")");

						$sth->execute([$ref_id, $owner_uid, $feed, $unread, $last_read_qpart, $marked,
							$published, $score]);

						$sth = $pdo->prepare("SELECT int_id FROM ttrss_user_entries WHERE
								ref_id = ? AND owner_uid = ? AND
								feed_id = ? LIMIT 1");

						$sth->execute([$ref_id, $owner_uid, $feed]);

						if ($row = $sth->fetch())
							$entry_int_id = $row['int_id'];
					}

					Debug::log("resulting RID: $entry_ref_id, IID: $entry_int_id", Debug::$LOG_VERBOSE);

					if (DB_TYPE == "pgsql")
						$tsvector_qpart = "tsvector_combined = to_tsvector(:ts_lang, :ts_content),";
					else
						$tsvector_qpart = "";

					$sth = $pdo->prepare("UPDATE ttrss_entries
						SET title = :title,
							$tsvector_qpart
							content = :content,
							content_hash = :content_hash,
							updated = :updated,
							date_updated = NOW(),
							num_comments = :num_comments,
							plugin_data = :plugin_data,
							author = :author,
							lang = :lang
						WHERE id = :id");

					$params = [":title" => $entry_title,
						":content" => "$entry_content",
						":content_hash" => $entry_current_hash,
						":updated" => $entry_timestamp_fmt,
						":num_comments" => (int)$num_comments,
						":plugin_data" => $entry_plugin_data,
						":author" => "$entry_author",
						":lang" => $entry_language,
						":id" => $ref_id];

					if (DB_TYPE == "pgsql") {
						$params[":ts_lang"] = $feed_language;
						$params[":ts_content"] = mb_substr(strip_tags($entry_title . " " . $entry_content), 0, 900000);
					}

					$sth->execute($params);

					// update aux data
					$sth = $pdo->prepare("UPDATE ttrss_user_entries
							SET score = ? WHERE ref_id = ?");
					$sth->execute([$score, $ref_id]);

					if ($mark_unread_on_update) {
						Debug::log("article updated, marking unread as requested.", Debug::$LOG_VERBOSE);

						$sth = $pdo->prepare("UPDATE ttrss_user_entries
							SET last_read = null, unread = true WHERE ref_id = ?");
						$sth->execute([$ref_id]);
					}
				}

				Debug::log("assigning labels [other]...", Debug::$LOG_VERBOSE);

				foreach ($article_labels as $label) {
					Labels::add_article($entry_ref_id, $label[1], $owner_uid);
				}

				Debug::log("assigning labels [filters]...", Debug::$LOG_VERBOSE);

				RSSUtils::assign_article_to_label_filters($entry_ref_id, $article_filters,
					$owner_uid, $article_labels);

				Debug::log("looking for enclosures...", Debug::$LOG_VERBOSE);

				// enclosures

				$enclosures = array();

				$encs = $item->get_enclosures();

				if (is_array($encs)) {
					foreach ($encs as $e) {
						$e_item = array(
							rewrite_relative_url($site_url, $e->link),
							$e->type, $e->length, $e->title, $e->width, $e->height);

						// Yet another episode of "mysql utf8_general_ci is gimped"
						if (DB_TYPE == "mysql" && MYSQL_CHARSET != "UTF8MB4") {
							for ($i = 0; $i < count($e_item); $i++) {
								if (is_string($e_item[$i])) {
									$e_item[$i] = RSSUtils::strip_utf8mb4($e_item[$i]);
								}
							}
						}

						array_push($enclosures, $e_item);
					}
				}

				if ($cache_images && is_writable(CACHE_DIR . '/images'))
					RSSUtils::cache_enclosures($enclosures, $site_url);

				if (Debug::get_loglevel() >= Debug::$LOG_EXTENDED) {
					Debug::log("article enclosures:", Debug::$LOG_VERBOSE);
					print_r($enclosures);
				}

				$esth = $pdo->prepare("SELECT id FROM ttrss_enclosures
						WHERE content_url = ? AND content_type = ? AND post_id = ?");

				$usth = $pdo->prepare("INSERT INTO ttrss_enclosures
							(content_url, content_type, title, duration, post_id, width, height) VALUES
							(?, ?, ?, ?, ?, ?, ?)");

				foreach ($enclosures as $enc) {
					$enc_url = $enc[0];
					$enc_type = $enc[1];
					$enc_dur = (int)$enc[2];
					$enc_title = $enc[3];
					$enc_width = intval($enc[4]);
					$enc_height = intval($enc[5]);

					$esth->execute([$enc_url, $enc_type, $entry_ref_id]);

					if (!$esth->fetch()) {
						$usth->execute([$enc_url, $enc_type, (string)$enc_title, $enc_dur, $entry_ref_id, $enc_width, $enc_height]);
					}
				}

				// check for manual tags (we have to do it here since they're loaded from filters)

				foreach ($article_filters as $f) {
					if ($f["type"] == "tag") {

						$manual_tags = trim_array(explode(",", $f["param"]));

						foreach ($manual_tags as $tag) {
							if (tag_is_valid($tag)) {
								array_push($entry_tags, $tag);
							}
						}
					}
				}

				// Skip boring tags

				$boring_tags = trim_array(explode(",", mb_strtolower(get_pref(
					'BLACKLISTED_TAGS', $owner_uid, ''), 'utf-8')));

				$filtered_tags = array();
				$tags_to_cache = array();

				if ($entry_tags && is_array($entry_tags)) {
					foreach ($entry_tags as $tag) {
						if (array_search($tag, $boring_tags) === false) {
							array_push($filtered_tags, $tag);
						}
					}
				}

				$filtered_tags = array_unique($filtered_tags);

				if (Debug::get_loglevel() >= Debug::$LOG_EXTENDED) {
					Debug::log("filtered article tags:", Debug::$LOG_VERBOSE);
					print_r($filtered_tags);
				}

				// Save article tags in the database

				if (count($filtered_tags) > 0) {

					$tsth = $pdo->prepare("SELECT id FROM ttrss_tags
							WHERE tag_name = ? AND post_int_id = ? AND
							owner_uid = ? LIMIT 1");

					$usth = $pdo->prepare("INSERT INTO ttrss_tags
									(owner_uid,tag_name,post_int_id)
									VALUES (?, ?, ?)");

					foreach ($filtered_tags as $tag) {

						$tag = sanitize_tag($tag);

						if (!tag_is_valid($tag)) continue;

						$tsth->execute([$tag, $entry_int_id, $owner_uid]);

						if (!$tsth->fetch()) {
							$usth->execute([$owner_uid, $tag, $entry_int_id]);
						}

						array_push($tags_to_cache, $tag);
					}

					/* update the cache */

					$tags_to_cache = array_unique($tags_to_cache);

					$tags_str = join(",", $tags_to_cache);

					$tsth = $pdo->prepare("UPDATE ttrss_user_entries
						SET tag_cache = ? WHERE ref_id = ?
						AND owner_uid = ?");
					$tsth->execute([$tags_str, $entry_ref_id, $owner_uid]);
				}

				Debug::log("article processed", Debug::$LOG_VERBOSE);

				$pdo->commit();
			}

			Debug::log("purging feed...", Debug::$LOG_VERBOSE);

			purge_feed($feed, 0);

			$sth = $pdo->prepare("UPDATE ttrss_feeds
				SET last_updated = NOW(), last_unconditional = NOW(), last_error = '' WHERE id = ?");
			$sth->execute([$feed]);

		} else {

			$error_msg = mb_substr($rss->error(), 0, 245);

			Debug::log("fetch error: $error_msg", Debug::$LOG_VERBOSE);

			if (count($rss->errors()) > 1) {
				foreach ($rss->errors() as $error) {
					Debug::log("+ $error", Debug::$LOG_VERBOSE);
				}
			}

			$sth = $pdo->prepare("UPDATE ttrss_feeds SET last_error = ?,
				last_updated = NOW(), last_unconditional = NOW() WHERE id = ?");
			$sth->execute([$error_msg, $feed]);

			unset($rss);
			return false;
		}

		Debug::log("done", Debug::$LOG_VERBOSE);

		return true;
	}

	static function cache_enclosures($enclosures, $site_url) {
		foreach ($enclosures as $enc) {

			if (preg_match("/(image|audio|video)/", $enc[1])) {

				$src = rewrite_relative_url($site_url, $enc[0]);

				$local_filename = CACHE_DIR . "/images/" . sha1($src);

				Debug::log("cache_enclosures: downloading: $src to $local_filename", Debug::$LOG_VERBOSE);

				if (!file_exists($local_filename)) {
					$file_content = fetch_file_contents($src);

					if ($file_content && strlen($file_content) > MIN_CACHE_FILE_SIZE) {
						file_put_contents($local_filename, $file_content);
					}
				} else if (is_writable($local_filename)) {
					touch($local_filename);
				}
			}
		}
	}

	static function cache_media($html, $site_url) {
		libxml_use_internal_errors(true);

		$charset_hack = '<head>
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		</head>';

		$doc = new DOMDocument();
		$doc->loadHTML($charset_hack . $html);
		$xpath = new DOMXPath($doc);

		$entries = $xpath->query('(//img[@src])|(//video/source[@src])|(//audio/source[@src])');

		foreach ($entries as $entry) {
			if ($entry->hasAttribute('src') && strpos($entry->getAttribute('src'), "data:") !== 0) {
				$src = rewrite_relative_url($site_url, $entry->getAttribute('src'));

				$local_filename = CACHE_DIR . "/images/" . sha1($src);

				Debug::log("cache_media: checking $src", Debug::$LOG_VERBOSE);

				if (!file_exists($local_filename)) {
					Debug::log("cache_media: downloading: $src to $local_filename", Debug::$LOG_VERBOSE);

					$file_content = fetch_file_contents($src);

					if ($file_content && strlen($file_content) > MIN_CACHE_FILE_SIZE) {
						file_put_contents($local_filename, $file_content);
					}
				} else if (is_writable($local_filename)) {
					touch($local_filename);
				}
			}
		}
	}

	static function expire_error_log() {
		Debug::log("Removing old error log entries...");

		$pdo = Db::pdo();

		if (DB_TYPE == "pgsql") {
			$pdo->query("DELETE FROM ttrss_error_log
				WHERE created_at < NOW() - INTERVAL '7 days'");
		} else {
			$pdo->query("DELETE FROM ttrss_error_log
				WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
		}
	}

	static function expire_lock_files() {
		Debug::log("Removing old lock files...", Debug::$LOG_VERBOSE);

		$num_deleted = 0;

		if (is_writable(LOCK_DIRECTORY)) {
			$files = glob(LOCK_DIRECTORY . "/*.lock");

			if ($files) {
				foreach ($files as $file) {
					if (!file_is_locked(basename($file)) && time() - filemtime($file) > 86400*2) {
						unlink($file);
						++$num_deleted;
					}
				}
			}
		}

		Debug::log("Removed $num_deleted old lock files.");
	}

	static function expire_cached_files() {
		foreach (array("feeds", "images", "export", "upload") as $dir) {
			$cache_dir = CACHE_DIR . "/$dir";

			Debug::log("Expiring $cache_dir", Debug::$LOG_VERBOSE);

			$num_deleted = 0;

			if (is_writable($cache_dir)) {
				$files = glob("$cache_dir/*");

				if ($files) {
					foreach ($files as $file) {
						if (time() - filemtime($file) > 86400*CACHE_MAX_DAYS) {
							unlink($file);

							++$num_deleted;
						}
					}
				}
			}

			Debug::log("$cache_dir: removed $num_deleted files.");
		}
	}

	/**
	 * Source: http://www.php.net/manual/en/function.parse-url.php#104527
	 * Returns the url query as associative array
	 *
	 * @param    string    query
	 * @return    array    params
	 */
	static function convertUrlQuery($query) {
		$queryParts = explode('&', $query);

		$params = array();

		foreach ($queryParts as $param) {
			$item = explode('=', $param);
			$params[$item[0]] = $item[1];
		}

		return $params;
	}

	static function get_article_filters($filters, $title, $content, $link, $author, $tags, &$matched_rules = false, &$matched_filters = false) {
		$matches = array();

		foreach ($filters as $filter) {
			$match_any_rule = $filter["match_any_rule"];
			$inverse = $filter["inverse"];
			$filter_match = false;

			foreach ($filter["rules"] as $rule) {
				$match = false;
				$reg_exp = str_replace('/', '\/', $rule["reg_exp"]);
				$rule_inverse = $rule["inverse"];

				if (!$reg_exp)
					continue;

				switch ($rule["type"]) {
					case "title":
						$match = @preg_match("/$reg_exp/iu", $title);
						break;
					case "content":
						// we don't need to deal with multiline regexps
						$content = preg_replace("/[\r\n\t]/", "", $content);

						$match = @preg_match("/$reg_exp/iu", $content);
						break;
					case "both":
						// we don't need to deal with multiline regexps
						$content = preg_replace("/[\r\n\t]/", "", $content);

						$match = (@preg_match("/$reg_exp/iu", $title) || @preg_match("/$reg_exp/iu", $content));
						break;
					case "link":
						$match = @preg_match("/$reg_exp/iu", $link);
						break;
					case "author":
						$match = @preg_match("/$reg_exp/iu", $author);
						break;
					case "tag":
						foreach ($tags as $tag) {
							if (@preg_match("/$reg_exp/iu", $tag)) {
								$match = true;
								break;
							}
						}
						break;
				}

				if ($rule_inverse) $match = !$match;

				if ($match_any_rule) {
					if ($match) {
						$filter_match = true;
						break;
					}
				} else {
					$filter_match = $match;
					if (!$match) {
						break;
					}
				}
			}

			if ($inverse) $filter_match = !$filter_match;

			if ($filter_match) {
				if (is_array($matched_rules)) array_push($matched_rules, $rule);
				if (is_array($matched_filters)) array_push($matched_filters, $filter);

				foreach ($filter["actions"] AS $action) {
					array_push($matches, $action);

					// if Stop action encountered, perform no further processing
					if (isset($action["type"]) && $action["type"] == "stop") return $matches;
				}
			}
		}

		return $matches;
	}

	static function find_article_filter($filters, $filter_name) {
		foreach ($filters as $f) {
			if ($f["type"] == $filter_name) {
				return $f;
			};
		}
		return false;
	}

	static function find_article_filters($filters, $filter_name) {
		$results = array();

		foreach ($filters as $f) {
			if ($f["type"] == $filter_name) {
				array_push($results, $f);
			};
		}
		return $results;
	}

	static function calculate_article_score($filters) {
		$score = 0;

		foreach ($filters as $f) {
			if ($f["type"] == "score") {
				$score += $f["param"];
			};
		}
		return $score;
	}

	static function labels_contains_caption($labels, $caption) {
		foreach ($labels as $label) {
			if ($label[1] == $caption) {
				return true;
			}
		}

		return false;
	}

	static function assign_article_to_label_filters($id, $filters, $owner_uid, $article_labels) {
		foreach ($filters as $f) {
			if ($f["type"] == "label") {
				if (!RSSUtils::labels_contains_caption($article_labels, $f["param"])) {
					Labels::add_article($id, $f["param"], $owner_uid);
				}
			}
		}
	}

	static function make_guid_from_title($title) {
		return preg_replace("/[ \"\',.:;]/", "-",
			mb_strtolower(strip_tags($title), 'utf-8'));
	}

	static function cleanup_counters_cache() {
		$pdo = Db::pdo();

		$res = $pdo->query("DELETE FROM ttrss_counters_cache
			WHERE feed_id > 0 AND
			(SELECT COUNT(id) FROM ttrss_feeds WHERE
				id = feed_id AND
				ttrss_counters_cache.owner_uid = ttrss_feeds.owner_uid) = 0");

		$frows = $res->rowCount();

		$res = $pdo->query("DELETE FROM ttrss_cat_counters_cache
			WHERE feed_id > 0 AND
			(SELECT COUNT(id) FROM ttrss_feed_categories WHERE
				id = feed_id AND
				ttrss_cat_counters_cache.owner_uid = ttrss_feed_categories.owner_uid) = 0");

		$crows = $res->rowCount();

		Debug::log("Removed $frows (feeds) $crows (cats) orphaned counter cache entries.");
	}

	static function housekeeping_user($owner_uid) {
		$tmph = new PluginHost();

		load_user_plugins($owner_uid, $tmph);

		$tmph->run_hooks(PluginHost::HOOK_HOUSE_KEEPING, "hook_house_keeping", "");
	}

	static function housekeeping_common() {
		RSSUtils::expire_cached_files();
		RSSUtils::expire_lock_files();
		RSSUtils::expire_error_log();

		$count = RSSUtils::update_feedbrowser_cache();
		Debug::log("Feedbrowser updated, $count feeds processed.");

		Article::purge_orphans();
		RSSUtils::cleanup_counters_cache();

		PluginHost::getInstance()->run_hooks(PluginHost::HOOK_HOUSE_KEEPING, "hook_house_keeping", "");
	}

	static function check_feed_favicon($site_url, $feed) {
		#		print "FAVICON [$site_url]: $favicon_url\n";

		$icon_file = ICONS_DIR . "/$feed.ico";

		if (!file_exists($icon_file)) {
			$favicon_url = get_favicon_url($site_url);

			if ($favicon_url) {
				// Limiting to "image" type misses those served with text/plain
				$contents = fetch_file_contents($favicon_url); // , "image");

				if ($contents) {
					// Crude image type matching.
					// Patterns gleaned from the file(1) source code.
					if (preg_match('/^\x00\x00\x01\x00/', $contents)) {
						// 0       string  \000\000\001\000        MS Windows icon resource
						//error_log("check_feed_favicon: favicon_url=$favicon_url isa MS Windows icon resource");
					}
					elseif (preg_match('/^GIF8/', $contents)) {
						// 0       string          GIF8            GIF image data
						//error_log("check_feed_favicon: favicon_url=$favicon_url isa GIF image");
					}
					elseif (preg_match('/^\x89PNG\x0d\x0a\x1a\x0a/', $contents)) {
						// 0       string          \x89PNG\x0d\x0a\x1a\x0a         PNG image data
						//error_log("check_feed_favicon: favicon_url=$favicon_url isa PNG image");
					}
					elseif (preg_match('/^\xff\xd8/', $contents)) {
						// 0       beshort         0xffd8          JPEG image data
						//error_log("check_feed_favicon: favicon_url=$favicon_url isa JPG image");
					}
					elseif (preg_match('/^BM/', $contents)) {
						// 0	string		BM	PC bitmap (OS2, Windows BMP files)
						//error_log("check_feed_favicon, favicon_url=$favicon_url isa BMP image");
					}
					else {
						//error_log("check_feed_favicon: favicon_url=$favicon_url isa UNKNOWN type");
						$contents = "";
					}
				}

				if ($contents) {
					$fp = @fopen($icon_file, "w");

					if ($fp) {
						fwrite($fp, $contents);
						fclose($fp);
						chmod($icon_file, 0644);
					}
				}
			}
			return $icon_file;
		}
	}

	static function is_gzipped($feed_data) {
		return mb_strpos($feed_data, "\x1f" . "\x8b" . "\x08", 0, "US-ASCII") === 0;
	}

}
