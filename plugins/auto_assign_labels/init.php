<?php
class Auto_Assign_Labels extends Plugin {

	/* @var PluginHost $host */
	private $host;

	function about() {
		return array(1.0,
			"Assign labels automatically based on article title, content, and tags",
			"fox");
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
	}

	function get_all_labels_filter_format($owner_uid) {
		$rv = array();

		$sth = $this->pdo->prepare("SELECT id, fg_color, bg_color, caption FROM ttrss_labels2 WHERE owner_uid = ?");
		$sth->execute([$owner_uid]);

		while ($line = $sth->fetch()) {
			array_push($rv, array(Labels::label_to_feed_id($line["id"]),
				$line["caption"], $line["fg_color"], $line["bg_color"]));
		}

		return $rv;
	}


	function hook_article_filter($article) {

		$owner_uid = $article["owner_uid"];
		$labels = $this->get_all_labels_filter_format($owner_uid);
		$tags_str = join(",", $article["tags"]);

		foreach ($labels as $label) {
			$caption = preg_quote($label[1], "/");

			if ($caption && preg_match("/\b$caption\b/i", "$tags_str " . strip_tags($article["content"]) . " " . $article["title"])) {

				if (!RSSUtils::labels_contains_caption($article["labels"], $caption)) {
					array_push($article["labels"], $label);
				}
			}
		}

		return $article;
	}

	function api_version() {
		return 2;
	}
}
