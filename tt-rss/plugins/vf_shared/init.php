<?php
class VF_Shared extends Plugin {

	/* @var PluginHost $host */
	private $host;

	function about() {
		return array(1.0,
			"Feed for all articles actively shared by URL",
			"fox",
			false);
	}

	function init($host) {
		$this->host = $host;

		$host->add_feed(-1, __("Shared articles"), 'link', $this);
	}

	function api_version() {
		return 2;
	}

	/**
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	function get_unread($feed_id) {
		$sth = $this->pdo->prepare("select count(int_id) AS count
			from ttrss_user_entries where owner_uid = ? and unread = true and uuid != ''");
		$sth->execute([$_SESSION['uid']]);

		if ($row = $sth->fetch()) {
			return $row['count'];
		}

		return 0;
	}

	/**
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	function get_total($feed_id) {
		$sth = $this->pdo->prepare("select count(int_id) AS count
			from ttrss_user_entries where owner_uid = ? and uuid != ''");
		$sth->execute([$_SESSION['uid']]);

		if ($row = $sth->fetch()) {
			return $row['count'];
		}

		return 0;
	}

	/**
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	function get_headlines($feed_id, $options) {
		$params = array(
			"feed" => -4,
			"limit" => $options["limit"],
			"view_mode" => $this->get_unread(-1) > 0 ? "adaptive" : "all_articles",
			"search" => $options['search'],
			"override_order" => $options['override_order'],
			"offset" => $options["offset"],
			"filter" => $options["filter"],
			"since_id" => $options["since_id"],
			"include_children" => $options["include_children"],
			"override_strategy" => "uuid != ''",
			"override_vfeed" => "ttrss_feeds.title AS feed_title,"
		);

		$qfh_ret = Feeds::queryFeedHeadlines($params);
		$qfh_ret[1] = __("Shared articles");

		return $qfh_ret;
	}

}