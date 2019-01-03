<?php
class Cache_Starred_Images extends Plugin implements IHandler {

	/* @var PluginHost $host */
	private $host;
	private $cache_dir;
    private $max_cache_attempts = 5; // per-article

	function about() {
		return array(1.0,
			"Automatically cache Starred articles' images and HTML5 video files",
			"fox",
			true);
	}

	/**
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	function csrf_ignore($method) {
		return false;
	}

	/**
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	function before($method) {
		return true;
	}

	function after() {
		return true;
	}

	function init($host) {
		$this->host = $host;

		$this->cache_dir = CACHE_DIR . "/starred-images/";

		if (!is_dir($this->cache_dir)) {
			mkdir($this->cache_dir);
		}

		if (is_dir($this->cache_dir)) {

			if (!is_writable($this->cache_dir))
				chmod($this->cache_dir, 0777);

			if (is_writable($this->cache_dir)) {
				$host->add_hook($host::HOOK_UPDATE_TASK, $this);
				$host->add_hook($host::HOOK_HOUSE_KEEPING, $this);
				$host->add_hook($host::HOOK_SANITIZE, $this);
				$host->add_handler("public", "cache_starred_images_getimage", $this);

			} else {
				user_error("Starred cache directory is not writable.", E_USER_WARNING);
			}

		} else {
			user_error("Unable to create starred cache directory.", E_USER_WARNING);
		}
	}

	function cache_starred_images_getimage() {
		ob_end_clean();

		$hash = basename($_REQUEST["hash"]);

		if ($hash) {

			$filename = $this->cache_dir . "/" . basename($hash);

			if (file_exists($filename)) {
				header("Content-Disposition: attachment; filename=\"$hash\"");

				send_local_file($filename);
			} else {
				header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
				echo "File not found.";
			}
		}
	}

	/**
	 * @SuppressWarnings(PHPMD.UnusedLocalVariable)
	 */
	function hook_house_keeping() {
		$files = glob($this->cache_dir . "/*.{png,mp4,status}", GLOB_BRACE);

		$last_article_id = 0;
		$article_exists = 1;

		foreach ($files as $file) {
			list ($article_id, $hash) = explode("-", basename($file));

			if ($article_id != $last_article_id) {
				$last_article_id = $article_id;

				$sth = $this->pdo->prepare("SELECT id FROM ttrss_entries WHERE id = ?");
				$sth->execute([$article_id]);

				$article_exists = $sth->fetch();
			}

			if (!$article_exists) {
				unlink($file);
			}
		}
	}

	/**
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	function hook_sanitize($doc, $site_url, $allowed_elements, $disallowed_attributes, $article_id) {
		$xpath = new DOMXpath($doc);

		if ($article_id) {
			$entries = $xpath->query('(//img[@src])|(//video/source[@src])');

			foreach ($entries as $entry) {
				if ($entry->hasAttribute('src')) {
					$src = rewrite_relative_url($site_url, $entry->getAttribute('src'));

					$extension = $entry->tagName == 'source' ? '.mp4' : '.png';
					$local_filename = $this->cache_dir . $article_id . "-" . sha1($src) . $extension;

					if (file_exists($local_filename)) {
						$entry->setAttribute("src", get_self_url_prefix() .
							"/public.php?op=cache_starred_images_getimage&method=image&hash=" .
							$article_id . "-" . sha1($src) . $extension);
					}

				}
			}
		}

		return $doc;
	}

	function hook_update_task() {
		$res = $this->pdo->query("SELECT content, ttrss_user_entries.owner_uid, link, site_url, ttrss_entries.id, plugin_data
			FROM ttrss_entries, ttrss_user_entries LEFT JOIN ttrss_feeds ON
				(ttrss_user_entries.feed_id = ttrss_feeds.id)
			WHERE ref_id = ttrss_entries.id AND
				marked = true AND
				(UPPER(content) LIKE '%<IMG%' OR UPPER(content) LIKE '%<VIDEO%') AND
				site_url != '' AND
				plugin_data NOT LIKE '%starred_cache_images%'
			ORDER BY ".sql_random_function()." LIMIT 100");

		$usth = $this->pdo->prepare("UPDATE ttrss_entries SET plugin_data = ? WHERE id = ?");

		while ($line = $res->fetch()) {
			if ($line["site_url"]) {
				$success = $this->cache_article_images($line["content"], $line["site_url"], $line["owner_uid"], $line["id"]);

				if ($success) {
					$plugin_data = "starred_cache_images,${line['owner_uid']}:" . $line["plugin_data"];

					$usth->execute([$plugin_data, $line['id']]);
				}
			}
		}
	}

	/**
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	function cache_article_images($content, $site_url, $owner_uid, $article_id) {
		libxml_use_internal_errors(true);

		$status_filename = $this->cache_dir . $article_id . "-" . sha1($site_url) . ".status";

		Debug::log("status: $status_filename", Debug::$LOG_EXTENDED);

        if (file_exists($status_filename))
            $status = json_decode(file_get_contents($status_filename), true);
        else
            $status = [];

        $status["attempt"] += 1;

        // only allow several download attempts for article
        if ($status["attempt"] > $this->max_cache_attempts) {
            Debug::log("too many attempts for $site_url", Debug::$LOG_VERBOSE);
            return;
        }

        if (!file_put_contents($status_filename, json_encode($status))) {
            user_error("unable to write status file: $status_filename", E_USER_WARNING);
            return;
        }

		$charset_hack = '<head>
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		</head>';

		$doc = new DOMDocument();
		$doc->loadHTML($charset_hack . $content);
		$xpath = new DOMXPath($doc);

		$entries = $xpath->query('(//img[@src])|(//video/source[@src])');

		$success = false;
		$has_images = false;

		foreach ($entries as $entry) {

			if ($entry->hasAttribute('src') && strpos($entry->getAttribute('src'), "data:") !== 0) {

				$has_images = true;
				$src = rewrite_relative_url($site_url, $entry->getAttribute('src'));

				$extension = $entry->tagName == 'source' ? '.mp4' : '.png';

				$local_filename = $this->cache_dir . $article_id . "-" . sha1($src) . $extension;

				Debug::log("cache_images: downloading: $src to $local_filename", Debug::$LOG_VERBOSE);

				if (!file_exists($local_filename)) {
					$file_content = fetch_file_contents(["url" => $src, "max_size" => MAX_CACHE_FILE_SIZE]);

					if ($file_content) {
                        if (strlen($file_content) > MIN_CACHE_FILE_SIZE) {
                            file_put_contents($local_filename, $file_content);
                        }

						$success = true;
					}
				} else {
					$success = true;
				}
			}
		}

		return $success || !$has_images;
	}

	function api_version() {
		return 2;
	}
}
