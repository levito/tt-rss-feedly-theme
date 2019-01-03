<?php
class Af_Comics_Pa extends Af_ComicFilter {

	function supported() {
		return array("Penny Arcade");
	}

	function process(&$article) {
		if (strpos($article["link"], "penny-arcade.com") !== FALSE && strpos($article["title"], "Comic:") !== FALSE) {

				$doc = new DOMDocument();

				if ($doc->loadHTML(fetch_file_contents($article["link"]))) {
					$xpath = new DOMXPath($doc);
					$basenode = $xpath->query('(//div[@id="comicFrame"])')->item(0);

					if ($basenode) {
						$article["content"] = $doc->saveHTML($basenode);
					}
				}

			return true;
		}

		if (strpos($article["link"], "penny-arcade.com") !== FALSE && strpos($article["title"], "News Post:") !== FALSE) {
				$doc = new DOMDocument();

				if ($doc->loadHTML(fetch_file_contents($article["link"]))) {
					$xpath = new DOMXPath($doc);
					$entries = $xpath->query('(//div[@class="post"])');

					$basenode = false;

					foreach ($entries as $entry) {
						$basenode = $entry;
					}

					$meta = $xpath->query('(//div[@class="meta"])')->item(0);
					if ($meta->parentNode) { $meta->parentNode->removeChild($meta); }

					$header = $xpath->query('(//div[@class="postBody"]/h2)')->item(0);
					if ($header->parentNode) { $header->parentNode->removeChild($header); }

					$header = $xpath->query('(//div[@class="postBody"]/div[@class="comicPost"])')->item(0);
					if ($header->parentNode) { $header->parentNode->removeChild($header); }

					$avatar = $xpath->query('(//div[@class="avatar"]//img)')->item(0);

					if ($basenode)
						$basenode->insertBefore($avatar, $basenode->firstChild);

					$uninteresting = $xpath->query('(//div[@class="avatar"])');
					foreach ($uninteresting as $i) {
						$i->parentNode->removeChild($i);
					}

					if ($basenode){
						$article["content"] = $doc->saveHTML($basenode);
					}
				}

			return true;
		}

		return false;
	}
}
