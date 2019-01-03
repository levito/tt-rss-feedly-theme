<?php
class Af_Comics_ComicClass extends Af_ComicFilter {

	function supported() {
		return array("Loading Artist");
	}

	function process(&$article) {
		if (strpos($article["guid"], "loadingartist.com") !== FALSE) {

				// lol at people who block clients by user agent
				// oh noes my ad revenue Q_Q

				$res = fetch_file_contents($article["link"], false, false, false,
					 false, false, 0,
					 "Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; WOW64; Trident/6.0)");

				$doc = new DOMDocument();

				if (@$doc->loadHTML($res)) {
					$xpath = new DOMXPath($doc);
					$basenode = $xpath->query('//div[@class="comic"]')->item(0);

					if ($basenode) {
						$article["content"] = $doc->saveHTML($basenode);
					}
				}

			 return true;
		}

		return false;
	}
}
