<?php
class Labels
{
	static function label_to_feed_id($label) {
		return LABEL_BASE_INDEX - 1 - abs($label);
	}

	static function feed_to_label_id($feed) {
		return LABEL_BASE_INDEX - 1 + abs($feed);
	}

	static function find_id($label, $owner_uid) {
		$pdo = Db::pdo();

		$sth = $pdo->prepare("SELECT id FROM ttrss_labels2 WHERE caption = ?
				AND owner_uid = ? LIMIT 1");
		$sth->execute([$label, $owner_uid]);

		if ($row = $sth->fetch()) {
			return $row['id'];
		} else {
			return 0;
		}
	}

	static function find_caption($label, $owner_uid) {
		$pdo = Db::pdo();

		$sth = $pdo->prepare("SELECT caption FROM ttrss_labels2 WHERE id = ?
				AND owner_uid = ? LIMIT 1");
		$sth->execute([$label, $owner_uid]);

		if ($row = $sth->fetch()) {
			return $row['caption'];
		} else {
			return "";
		}
	}

	static function get_all_labels($owner_uid)	{
		$rv = array();

		$pdo = Db::pdo();

		$sth = $pdo->prepare("SELECT id, fg_color, bg_color, caption FROM ttrss_labels2
			WHERE owner_uid = ? ORDER BY caption");
		$sth->execute([$owner_uid]);

		while ($line = $sth->fetch()) {
			array_push($rv, $line);
		}

		return $rv;
	}

	static function update_cache($owner_uid, $id, $labels = false, $force = false) {
		$pdo = Db::pdo();

		if ($force)
			Labels::clear_cache($id);

		if (!$labels)
			$labels = Article::get_article_labels($id);

		$labels = json_encode($labels);

		$sth = $pdo->prepare("UPDATE ttrss_user_entries SET
			label_cache = ? WHERE ref_id = ? AND owner_uid = ?");
		$sth->execute([$labels, $id, $owner_uid]);

	}

	static function clear_cache($id)	{

		$pdo = Db::pdo();

		$sth = $pdo->prepare("UPDATE ttrss_user_entries SET
			label_cache = '' WHERE ref_id = ?");
		$sth->execute([$id]);

	}

	static function remove_article($id, $label, $owner_uid) {

		$label_id = Labels::find_id($label, $owner_uid);

		if (!$label_id) return;

		$pdo = Db::pdo();

		$sth = $pdo->prepare("DELETE FROM ttrss_user_labels2
			WHERE
				label_id = ? AND
				article_id = ?");

		$sth->execute([$label_id, $id]);

		Labels::clear_cache($id);
	}

	static function add_article($id, $label, $owner_uid)	{

		$label_id = Labels::find_id($label, $owner_uid);

		if (!$label_id) return;

		$pdo = Db::pdo();

		$sth = $pdo->prepare("SELECT
				article_id FROM ttrss_labels2, ttrss_user_labels2
			WHERE
				label_id = id AND
				label_id = ? AND
				article_id = ? AND owner_uid = ?
			LIMIT 1");

		$sth->execute([$label_id, $id, $owner_uid]);

		if (!$sth->fetch()) {
			$sth = $pdo->prepare("INSERT INTO ttrss_user_labels2
				(label_id, article_id) VALUES (?, ?)");

			$sth->execute([$label_id, $id]);
		}

		Labels::clear_cache($id);

	}

	static function remove($id, $owner_uid) {
		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		$pdo = Db::pdo();
		$tr_in_progress = false;

		try {
			$pdo->beginTransaction();
		} catch (Exception $e) {
			$tr_in_progress = true;
		}

		$sth = $pdo->prepare("SELECT caption FROM ttrss_labels2
			WHERE id = ?");
		$sth->execute([$id]);

		$row = $sth->fetch();
		$caption = $row['caption'];

		$sth = $pdo->prepare("DELETE FROM ttrss_labels2 WHERE id = ?
			AND owner_uid = ?");
		$sth->execute([$id, $owner_uid]);

		if ($sth->rowCount() != 0 && $caption) {

			/* Remove access key for the label */

			$ext_id = LABEL_BASE_INDEX - 1 - $id;

			$sth = $pdo->prepare("DELETE FROM ttrss_access_keys WHERE
				feed_id = ? AND owner_uid = ?");
			$sth->execute([$ext_id, $owner_uid]);

			/* Remove cached data */

			$sth = $pdo->prepare("UPDATE ttrss_user_entries SET label_cache = ''
				WHERE owner_uid = ?");
			$sth->execute([$owner_uid]);

		}

		if (!$tr_in_progress) $pdo->commit();
	}

	static function create($caption, $fg_color = '', $bg_color = '', $owner_uid = false)	{

		if (!$owner_uid) $owner_uid = $_SESSION['uid'];

		$pdo = Db::pdo();

		$tr_in_progress = false;

		try {
			$pdo->beginTransaction();
		} catch (Exception $e) {
			$tr_in_progress = true;
		}

		$sth = $pdo->prepare("SELECT id FROM ttrss_labels2
			WHERE caption = ? AND owner_uid = ?");
		$sth->execute([$caption, $owner_uid]);

		if (!$sth->fetch()) {
			$sth = $pdo->prepare("INSERT INTO ttrss_labels2
				(caption,owner_uid,fg_color,bg_color) VALUES (?, ?, ?, ?)");

			$sth->execute([$caption, $owner_uid, $fg_color, $bg_color]);

			$result = $sth->rowCount();
		}

		if (!$tr_in_progress) $pdo->commit();

		return $result;
	}
}