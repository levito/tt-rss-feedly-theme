<?php
class Af_Comics_Tfd extends Af_ComicFilter {

	function supported() {
		return array("Toothpaste For Dinner", "Married to the Sea");
	}

	function process(&$article) {
		if (strpos($article["link"], "toothpastefordinner.com") !== FALSE ||
		    strpos($article["link"], "marriedtothesea.com") !== FALSE) {
			$res = fetch_file_contents($article["link"], false, false, false,
				false, false, 0,
				"Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; WOW64; Trident/6.0)");

			if (!$res) return $article;

			$doc = new DOMDocument();

			if (@$doc->loadHTML(fetch_file_contents($article["link"]))) {
				$xpath = new DOMXPath($doc);
				$basenode = $xpath->query('//img[contains(@src, ".gif")]')->item(0);

				if ($basenode) {
					$article["content"] = $doc->saveHTML($basenode);
					return true;
				}
			}
		}

		return false;
	}
}
