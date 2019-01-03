<?php
class Af_Comics_Explosm extends Af_ComicFilter {

	function supported() {
		return array("Cyanide and Happiness");
	}

	function process(&$article) {

		if (strpos($article["link"], "explosm.net/comics") !== FALSE) {

				$doc = new DOMDocument();

				if (@$doc->loadHTML(fetch_file_contents($article["link"]))) {
					$xpath = new DOMXPath($doc);
					$basenode = $xpath->query('(//img[@id="main-comic"])')->item(0);

					if ($basenode) {
						$article["content"] = $doc->saveHTML($basenode);
					}
				}

			return true;
		}

		return false;
	}
}
