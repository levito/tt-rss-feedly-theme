<?php
class CCache {
	static function zero_all($owner_uid) {
		$pdo = Db::pdo();

		$sth = $pdo->prepare("UPDATE ttrss_counters_cache SET
			value = 0 WHERE owner_uid = ?");
		$sth->execute([$owner_uid]);

		$sth = $pdo->prepare("UPDATE ttrss_cat_counters_cache SET
			value = 0 WHERE owner_uid = ?");
		$sth->execute([$owner_uid]);
	}

	static function remove($feed_id, $owner_uid, $is_cat = false) {

		$feed_id = (int) $feed_id;

		if (!$is_cat) {
			$table = "ttrss_counters_cache";
		} else {
			$table = "ttrss_cat_counters_cache";
		}

		$pdo = Db::pdo();

		$sth = $pdo->prepare("DELETE FROM $table WHERE
			feed_id = ? AND owner_uid = ?");
		$sth->execute([$feed_id, $owner_uid]);

	}

	static function update_all($owner_uid) {

		$pdo = Db::pdo();

		if (get_pref('ENABLE_FEED_CATS', $owner_uid)) {

			$sth = $pdo->prepare("SELECT feed_id FROM ttrss_cat_counters_cache
				WHERE feed_id > 0 AND owner_uid = ?");
			$sth->execute([$owner_uid]);

			while ($line = $sth->fetch()) {
				CCache::update($line["feed_id"], $owner_uid, true);
			}

			/* We have to manually include category 0 */

			CCache::update(0, $owner_uid, true);

		} else {
			$sth = $pdo->prepare("SELECT feed_id FROM ttrss_counters_cache
				WHERE feed_id > 0 AND owner_uid = ?");
			$sth->execute([$owner_uid]);

			while ($line = $sth->fetch()) {
				print CCache::update($line["feed_id"], $owner_uid);

			}

		}
	}

	static function find($feed_id, $owner_uid, $is_cat = false,
						 $no_update = false) {

		// "" (null) is valid and should be cast to 0 (uncategorized)
		// everything else i.e. tags are not
		if (!is_numeric($feed_id) && $feed_id)
			return;

		$feed_id = (int) $feed_id;

		if (!$is_cat) {
			$table = "ttrss_counters_cache";
		} else {
			$table = "ttrss_cat_counters_cache";
		}

		$pdo = Db::pdo();

		$sth = $pdo->prepare("SELECT value FROM $table
			WHERE owner_uid = ? AND feed_id = ?
			LIMIT 1");

		$sth->execute([$owner_uid, $feed_id]);

		if ($row = $sth->fetch()) {
			return $row["value"];
		} else {
			if ($no_update) {
				return -1;
			} else {
				return CCache::update($feed_id, $owner_uid, $is_cat);
			}
		}

	}

	static function update($feed_id, $owner_uid, $is_cat = false,
						   $update_pcat = true, $pcat_fast = false) {

		// "" (null) is valid and should be cast to 0 (uncategorized)
		// everything else i.e. tags are not
		if (!is_numeric($feed_id) && $feed_id)
			return;

		$feed_id = (int) $feed_id;

		$prev_unread = CCache::find($feed_id, $owner_uid, $is_cat, true);

		/* When updating a label, all we need to do is recalculate feed counters
		 * because labels are not cached */

		if ($feed_id < 0) {
			CCache::update_all($owner_uid);
			return;
		}

		if (!$is_cat) {
			$table = "ttrss_counters_cache";
		} else {
			$table = "ttrss_cat_counters_cache";
		}

		$pdo = Db::pdo();

		if ($is_cat && $feed_id >= 0) {
			/* Recalculate counters for child feeds */

			if (!$pcat_fast) {
				$sth = $pdo->prepare("SELECT id FROM ttrss_feeds
						WHERE owner_uid = :uid AND
							(cat_id = :cat OR (:cat = 0 AND cat_id IS NULL))");
				$sth->execute([":uid" => $owner_uid, ":cat" => $feed_id]);

				while ($line = $sth->fetch()) {
					CCache::update((int)$line["id"], $owner_uid, false, false);
				}
			}

			$sth = $pdo->prepare("SELECT SUM(value) AS sv
				FROM ttrss_counters_cache, ttrss_feeds
				WHERE ttrss_feeds.id = feed_id AND
				(cat_id = :cat OR (:cat = 0 AND cat_id IS NULL)) AND
				ttrss_counters_cache.owner_uid = :uid AND
				ttrss_feeds.owner_uid = :uid");
			$sth->execute([":uid" => $owner_uid, ":cat" => $feed_id]);
			$row = $sth->fetch();

			$unread = (int) $row["sv"];

		} else {
			$unread = (int) Feeds::getFeedArticles($feed_id, $is_cat, true, $owner_uid);
		}

		$tr_in_progress = false;

		try {
			$pdo->beginTransaction();
		} catch (Exception $e) {
			$tr_in_progress = true;
		}

		$sth = $pdo->prepare("SELECT feed_id FROM $table
			WHERE owner_uid = ? AND feed_id = ? LIMIT 1");
		$sth->execute([$owner_uid, $feed_id]);

		if ($sth->fetch()) {

			$sth = $pdo->prepare("UPDATE $table SET
				value = ?, updated = NOW() WHERE
				feed_id = ? AND owner_uid = ?");

			$sth->execute([$unread, $feed_id, $owner_uid]);

		} else {
			$sth = $pdo->prepare("INSERT INTO $table
				(feed_id, value, owner_uid, updated)
				VALUES
				(?, ?, ?, NOW())");
			$sth->execute([$feed_id, $unread, $owner_uid]);
		}

		if (!$tr_in_progress) $pdo->commit();

		if ($feed_id > 0 && $prev_unread != $unread) {

			if (!$is_cat) {

				/* Update parent category */

				if ($update_pcat) {

					$sth = $pdo->prepare("SELECT cat_id FROM ttrss_feeds
						WHERE owner_uid = ? AND id = ?");
					$sth->execute([$owner_uid, $feed_id]);

					if ($row = $sth->fetch()) {
						CCache::update((int)$row["cat_id"], $owner_uid, true, true, true);
					}
				}
			}
		} else if ($feed_id < 0) {
			CCache::update_all($owner_uid);
		}

		return $unread;
	}

}