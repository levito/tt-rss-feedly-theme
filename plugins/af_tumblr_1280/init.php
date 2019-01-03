<?php
class Af_Tumblr_1280 extends Plugin {
	private $host;

	function about() {
		return array(1.0,
			"Replace Tumblr pictures and videos with largest size if available (requires CURL)",
			"fox");
	}

	function flags() {
		return array("needs_curl" => true);
	}

	function init($host) {
		$this->host = $host;

		if (function_exists("curl_init")) {
			$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
		}
	}

	function hook_article_filter($article) {

		if (!function_exists("curl_init") || ini_get("open_basedir"))
			return $article;

		$charset_hack = '<head>
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		</head>';

		$doc = new DOMDocument();
		$doc->loadHTML($charset_hack . $article["content"]);

		$found = false;

		if ($doc) {
			$xpath = new DOMXpath($doc);

			$images = $xpath->query('(//img[contains(@src, \'media.tumblr.com\')])');

			foreach ($images as $img) {
				$src = $img->getAttribute("src");

				$test_src = preg_replace("/_\d{3}.(jpg|gif|png)/", "_1280.$1", $src);

				if ($src != $test_src) {

					$ch = curl_init($test_src);
					curl_setopt($ch, CURLOPT_TIMEOUT, 5);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
					curl_setopt($ch, CURLOPT_HEADER, true);
					curl_setopt($ch, CURLOPT_NOBODY, true);
					curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
					curl_setopt($ch, CURLOPT_USERAGENT, SELF_USER_AGENT);

					@$result = curl_exec($ch);
					$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

					if ($result && $http_code == 200) {
						$img->setAttribute("src", $test_src);
						$found = true;
					}
				}
			}

			$video_sources = $xpath->query('//video/source[contains(@src, \'.tumblr.com/video_file\')]');

			foreach ($video_sources as $source) {
				$src = $source->getAttribute("src");

				$new_src = preg_replace("/\/\d{3}$/", "", $src);

				if ($src != $new_src) {
					$source->setAttribute("src", $new_src);
					$found = true;
				}
			}

			if ($found) {
				$doc->removeChild($doc->firstChild); //remove doctype
				$article["content"] = $doc->saveHTML();
			}
		}

		return $article;

	}


	function api_version() {
		return 2;
	}

}