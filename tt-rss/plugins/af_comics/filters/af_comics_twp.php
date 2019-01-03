<?php
class Af_Comics_Twp extends Af_ComicFilter {

	function supported() {
		return array("Three Word Phrase");
	}

	function process(&$article) {

		if (strpos($article["link"], "threewordphrase.com") !== FALSE) {

				$doc = new DOMDocument();

				if (@$doc->loadHTML(fetch_file_contents($article["link"]))) {
					$xpath = new DOMXpath($doc);

					$basenode = $xpath->query("//td/center/img")->item(0);

					if ($basenode) {
						$article["content"] = $doc->saveHTML($basenode);
					}
				}

			return true;
		}

		return false;
	}
}
