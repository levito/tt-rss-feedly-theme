<?php
class Af_Comics_Whomp extends Af_ComicFilter {

	function supported() {
		return array("Whomp!");
	}

	function process(&$article) {
		if (strpos($article["guid"], "whompcomic.com") !== FALSE) {

			$res = fetch_file_contents($article["link"], false, false, false,
				 false, false, 0,
				 "Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; WOW64; Trident/6.0)");

			global $fetch_last_error_content;

			if (!$res && $fetch_last_error_content)
				$res = $fetch_last_error_content;

			$doc = new DOMDocument();

			if (@$doc->loadHTML($res)) {
				$xpath = new DOMXPath($doc);
				$basenode = $xpath->query('//img[@id="cc-comic"]')->item(0);

				if ($basenode) {
					$article["content"] = $doc->saveHTML($basenode);
				}
			}

			return true;
		}

		return false;
	}
}
