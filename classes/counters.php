<?php
class Counters {

	static function getAllCounters() {
		$data = Counters::getGlobalCounters();

		$data = array_merge($data, Counters::getVirtCounters());
		$data = array_merge($data, Counters::getLabelCounters());
		$data = array_merge($data, Counters::getFeedCounters());
		$data = array_merge($data, Counters::getCategoryCounters());

		return $data;
	}

	static function getCategoryCounters() {
		$ret_arr = array();

		/* Labels category */

		$cv = array("id" => -2, "kind" => "cat",
			"counter" => Feeds::getCategoryUnread(-2));

		array_push($ret_arr, $cv);

		$pdo = DB::pdo();

		$sth = $pdo->prepare("SELECT ttrss_feed_categories.id AS cat_id, value AS unread,
			(SELECT COUNT(id) FROM ttrss_feed_categories AS c2
				WHERE c2.parent_cat = ttrss_feed_categories.id) AS num_children
			FROM ttrss_feed_categories, ttrss_cat_counters_cache
			WHERE ttrss_cat_counters_cache.feed_id = ttrss_feed_categories.id AND
			ttrss_cat_counters_cache.owner_uid = ttrss_feed_categories.owner_uid AND
			ttrss_feed_categories.owner_uid = ?");
		$sth->execute([$_SESSION['uid']]);

		while ($line = $sth->fetch()) {
			$line["cat_id"] = (int) $line["cat_id"];

			if ($line["num_children"] > 0) {
				$child_counter = Feeds::getCategoryChildrenUnread($line["cat_id"], $_SESSION["uid"]);
			} else {
				$child_counter = 0;
			}

			$cv = array("id" => $line["cat_id"], "kind" => "cat",
				"counter" => $line["unread"] + $child_counter);

			array_push($ret_arr, $cv);
		}

		/* Special case: NULL category doesn't actually exist in the DB */

		$cv = array("id" => 0, "kind" => "cat",
			"counter" => (int) CCache::find(0, $_SESSION["uid"], true));

		array_push($ret_arr, $cv);

		return $ret_arr;
	}

	static function getGlobalCounters($global_unread = -1) {
		$ret_arr = array();

		if ($global_unread == -1) {
			$global_unread = Feeds::getGlobalUnread();
		}

		$cv = array("id" => "global-unread",
			"counter" => (int) $global_unread);

		array_push($ret_arr, $cv);

		$pdo = Db::pdo();

		$sth = $pdo->prepare("SELECT COUNT(id) AS fn FROM
			ttrss_feeds WHERE owner_uid = ?");
		$sth->execute([$_SESSION['uid']]);
		$row = $sth->fetch();

		$subscribed_feeds = $row["fn"];

		$cv = array("id" => "subscribed-feeds",
			"counter" => (int) $subscribed_feeds);

		array_push($ret_arr, $cv);

		return $ret_arr;
	}

	static function getVirtCounters() {

		$ret_arr = array();

		for ($i = 0; $i >= -4; $i--) {

			$count = getFeedUnread($i);

			if ($i == 0 || $i == -1 || $i == -2)
				$auxctr = Feeds::getFeedArticles($i, false);
			else
				$auxctr = 0;

			$cv = array("id" => $i,
				"counter" => (int) $count,
				"auxcounter" => (int) $auxctr);

//			if (get_pref('EXTENDED_FEEDLIST'))
//				$cv["xmsg"] = getFeedArticles($i)." ".__("total");

			array_push($ret_arr, $cv);
		}

		$feeds = PluginHost::getInstance()->get_feeds(-1);

		if (is_array($feeds)) {
			foreach ($feeds as $feed) {
				$cv = array("id" => PluginHost::pfeed_to_feed_id($feed['id']),
					"counter" => $feed['sender']->get_unread($feed['id']));

				if (method_exists($feed['sender'], 'get_total'))
					$cv["auxcounter"] = $feed['sender']->get_total($feed['id']);

				array_push($ret_arr, $cv);
			}
		}

		return $ret_arr;
	}

	static function getLabelCounters($descriptions = false) {

		$ret_arr = array();

		$pdo = Db::pdo();

		$sth = $pdo->prepare("SELECT id,caption,SUM(CASE WHEN u1.unread = true THEN 1 ELSE 0 END) AS unread, COUNT(u1.unread) AS total
			FROM ttrss_labels2 LEFT JOIN ttrss_user_labels2 ON
				(ttrss_labels2.id = label_id)
				LEFT JOIN ttrss_user_entries AS u1 ON u1.ref_id = article_id
				WHERE ttrss_labels2.owner_uid = :uid AND u1.owner_uid = :uid
				GROUP BY ttrss_labels2.id,
					ttrss_labels2.caption");
		$sth->execute([":uid" => $_SESSION['uid']]);

		while ($line = $sth->fetch()) {

			$id = Labels::label_to_feed_id($line["id"]);

			$cv = array("id" => $id,
				"counter" => (int) $line["unread"],
				"auxcounter" => (int) $line["total"]);

			if ($descriptions)
				$cv["description"] = $line["caption"];

			array_push($ret_arr, $cv);
		}

		return $ret_arr;
	}

	static function getFeedCounters($active_feed = false) {

		$ret_arr = array();

		$pdo = Db::pdo();

		$sth = $pdo->prepare("SELECT ttrss_feeds.id,
				ttrss_feeds.title,
				".SUBSTRING_FOR_DATE."(ttrss_feeds.last_updated,1,19) AS last_updated,
				last_error, value AS count
			FROM ttrss_feeds, ttrss_counters_cache
			WHERE ttrss_feeds.owner_uid = ?
				AND ttrss_counters_cache.owner_uid = ttrss_feeds.owner_uid
				AND ttrss_counters_cache.feed_id = ttrss_feeds.id");
		$sth->execute([$_SESSION['uid']]);

		while ($line = $sth->fetch()) {

			$id = $line["id"];
			$count = $line["count"];
			$last_error = htmlspecialchars($line["last_error"]);

			$last_updated = make_local_datetime($line['last_updated'], false);

			if (Feeds::feedHasIcon($id)) {
				$has_img = filemtime(Feeds::getIconFile($id));
			} else {
				$has_img = false;
			}

			if (date('Y') - date('Y', strtotime($line['last_updated'])) > 2)
				$last_updated = '';

			$cv = array("id" => $id,
				"updated" => $last_updated,
				"counter" => (int) $count,
				"has_img" => (int) $has_img);

			if ($last_error)
				$cv["error"] = $last_error;

//			if (get_pref('EXTENDED_FEEDLIST'))
//				$cv["xmsg"] = getFeedArticles($id)." ".__("total");

			if ($active_feed && $id == $active_feed)
				$cv["title"] = truncate_string($line["title"], 30);

			array_push($ret_arr, $cv);

		}

		return $ret_arr;
	}

}