<?php
class Digest
{

	/**
	 * Send by mail a digest of last articles.
	 *
	 * @param mixed $link The database connection.
	 * @param integer $limit The maximum number of articles by digest.
	 * @return boolean Return false if digests are not enabled.
	 */
	static function send_headlines_digests() {

		$user_limit = 15; // amount of users to process (e.g. emails to send out)
		$limit = 1000; // maximum amount of headlines to include

		Debug::log("Sending digests, batch of max $user_limit users, headline limit = $limit");

		if (DB_TYPE == "pgsql") {
			$interval_qpart = "last_digest_sent < NOW() - INTERVAL '1 days'";
		} else if (DB_TYPE == "mysql") {
			$interval_qpart = "last_digest_sent < DATE_SUB(NOW(), INTERVAL 1 DAY)";
		}

		$pdo = Db::pdo();

		$res = $pdo->query("SELECT id,email FROM ttrss_users
				WHERE email != '' AND (last_digest_sent IS NULL OR $interval_qpart)");

		while ($line = $res->fetch()) {

			if (@get_pref('DIGEST_ENABLE', $line['id'], false)) {
				$preferred_ts = strtotime(get_pref('DIGEST_PREFERRED_TIME', $line['id'], '00:00'));

				// try to send digests within 2 hours of preferred time
				if ($preferred_ts && time() >= $preferred_ts &&
					time() - $preferred_ts <= 7200
				) {

					Debug::log("Sending digest for UID:" . $line['id'] . " - " . $line["email"]);

					$do_catchup = get_pref('DIGEST_CATCHUP', $line['id'], false);

					global $tz_offset;

					// reset tz_offset global to prevent tz cache clash between users
					$tz_offset = -1;

					$tuple = Digest::prepare_headlines_digest($line["id"], 1, $limit);
					$digest = $tuple[0];
					$headlines_count = $tuple[1];
					$affected_ids = $tuple[2];
					$digest_text = $tuple[3];

					if ($headlines_count > 0) {

						$mailer = new Mailer();

						//$rc = $mail->quickMail($line["email"], $line["login"], DIGEST_SUBJECT, $digest, $digest_text);

						$rc = $mailer->mail(["to_name" => $line["login"],
							"to_address" => $line["email"],
							"subject" => DIGEST_SUBJECT,
							"message" => $digest_text,
							"message_html" => $digest]);

						//if (!$rc && $debug) Debug::log("ERROR: " . $mailer->lastError());

						Debug::log("RC=$rc");

						if ($rc && $do_catchup) {
							Debug::log("Marking affected articles as read...");
							Article::catchupArticlesById($affected_ids, 0, $line["id"]);
						}
					} else {
						Debug::log("No headlines");
					}

					$sth = $pdo->prepare("UPDATE ttrss_users SET last_digest_sent = NOW()
						WHERE id = ?");
					$sth->execute([$line["id"]]);

				}
			}
		}

		Debug::log("All done.");

	}

	static function prepare_headlines_digest($user_id, $days = 1, $limit = 1000) {

		require_once "lib/MiniTemplator.class.php";

		$tpl = new MiniTemplator;
		$tpl_t = new MiniTemplator;

		$tpl->readTemplateFromFile("templates/digest_template_html.txt");
		$tpl_t->readTemplateFromFile("templates/digest_template.txt");

		$user_tz_string = get_pref('USER_TIMEZONE', $user_id);
		$local_ts = convert_timestamp(time(), 'UTC', $user_tz_string);

		$tpl->setVariable('CUR_DATE', date('Y/m/d', $local_ts));
		$tpl->setVariable('CUR_TIME', date('G:i', $local_ts));

		$tpl_t->setVariable('CUR_DATE', date('Y/m/d', $local_ts));
		$tpl_t->setVariable('CUR_TIME', date('G:i', $local_ts));

		$affected_ids = array();

		$days = (int) $days;

		if (DB_TYPE == "pgsql") {
			$interval_qpart = "ttrss_entries.date_updated > NOW() - INTERVAL '$days days'";
		} else if (DB_TYPE == "mysql") {
			$interval_qpart = "ttrss_entries.date_updated > DATE_SUB(NOW(), INTERVAL $days DAY)";
		}

		$pdo = Db::pdo();

		$sth = $pdo->prepare("SELECT ttrss_entries.title,
				ttrss_feeds.title AS feed_title,
				COALESCE(ttrss_feed_categories.title, '" . __('Uncategorized') . "') AS cat_title,
				date_updated,
				ttrss_user_entries.ref_id,
				link,
				score,
				content,
				" . SUBSTRING_FOR_DATE . "(last_updated,1,19) AS last_updated
			FROM
				ttrss_user_entries,ttrss_entries,ttrss_feeds
			LEFT JOIN
				ttrss_feed_categories ON (cat_id = ttrss_feed_categories.id)
			WHERE
				ref_id = ttrss_entries.id AND feed_id = ttrss_feeds.id
				AND include_in_digest = true
				AND $interval_qpart
				AND ttrss_user_entries.owner_uid = :user_id
				AND unread = true
				AND score >= 0
			ORDER BY ttrss_feed_categories.title, ttrss_feeds.title, score DESC, date_updated DESC
			LIMIT :limit");
		$sth->bindParam(':user_id', intval($user_id, 10), PDO::PARAM_INT);
		$sth->bindParam(':limit', intval($limit, 10), PDO::PARAM_INT);
		$sth->execute();

		$headlines_count = 0;
		$headlines = array();

		while ($line = $sth->fetch()) {
			array_push($headlines, $line);
			$headlines_count++;
		}

		for ($i = 0; $i < sizeof($headlines); $i++) {

			$line = $headlines[$i];

			array_push($affected_ids, $line["ref_id"]);

			$updated = make_local_datetime($line['last_updated'], false,
				$user_id);

			if (get_pref('ENABLE_FEED_CATS', $user_id)) {
				$line['feed_title'] = $line['cat_title'] . " / " . $line['feed_title'];
			}

			$tpl->setVariable('FEED_TITLE', $line["feed_title"]);
			$tpl->setVariable('ARTICLE_TITLE', $line["title"]);
			$tpl->setVariable('ARTICLE_LINK', $line["link"]);
			$tpl->setVariable('ARTICLE_UPDATED', $updated);
			$tpl->setVariable('ARTICLE_EXCERPT',
				truncate_string(strip_tags($line["content"]), 300));
//			$tpl->setVariable('ARTICLE_CONTENT',
//				strip_tags($article_content));

			$tpl->addBlock('article');

			$tpl_t->setVariable('FEED_TITLE', $line["feed_title"]);
			$tpl_t->setVariable('ARTICLE_TITLE', $line["title"]);
			$tpl_t->setVariable('ARTICLE_LINK', $line["link"]);
			$tpl_t->setVariable('ARTICLE_UPDATED', $updated);
//			$tpl_t->setVariable('ARTICLE_EXCERPT',
//				truncate_string(strip_tags($line["excerpt"]), 100));

			$tpl_t->addBlock('article');

			if ($headlines[$i]['feed_title'] != $headlines[$i + 1]['feed_title']) {
				$tpl->addBlock('feed');
				$tpl_t->addBlock('feed');
			}

		}

		$tpl->addBlock('digest');
		$tpl->generateOutputToString($tmp);

		$tpl_t->addBlock('digest');
		$tpl_t->generateOutputToString($tmp_t);

		return array($tmp, $headlines_count, $affected_ids, $tmp_t);
	}

}
