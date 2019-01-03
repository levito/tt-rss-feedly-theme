<?php
class FeedItem_RSS extends FeedItem_Common {
	function get_id() {
		$id = $this->elem->getElementsByTagName("guid")->item(0);

		if ($id) {
			return clean($id->nodeValue);
		} else {
			return clean($this->get_link());
		}
	}

	function get_date() {
		$pubDate = $this->elem->getElementsByTagName("pubDate")->item(0);

		if ($pubDate) {
			return strtotime($pubDate->nodeValue);
		}

		$date = $this->xpath->query("dc:date", $this->elem)->item(0);

		if ($date) {
			return strtotime($date->nodeValue);
		}
	}

	function get_link() {
		$links = $this->xpath->query("atom:link", $this->elem);

		foreach ($links as $link) {
			if ($link && $link->hasAttribute("href") &&
				(!$link->hasAttribute("rel")
					|| $link->getAttribute("rel") == "alternate"
					|| $link->getAttribute("rel") == "standout")) {

				return clean(trim($link->getAttribute("href")));
			}
		}

		$link = $this->elem->getElementsByTagName("guid")->item(0);

		if ($link && $link->hasAttributes() && $link->getAttribute("isPermaLink") == "true") {
			return clean(trim($link->nodeValue));
		}

		$link = $this->elem->getElementsByTagName("link")->item(0);

		if ($link) {
			return clean(trim($link->nodeValue));
		}
	}

	function get_title() {
		$title = $this->xpath->query("title", $this->elem)->item(0);

		if ($title) {
			return clean(trim($title->nodeValue));
		}

		// if the document has a default namespace then querying for
		// title would fail because of reasons so let's try the old way
		$title = $this->elem->getElementsByTagName("title")->item(0);

		if ($title) {
			return clean(trim($title->nodeValue));
		}
	}

	function get_content() {
		$contentA = $this->xpath->query("content:encoded", $this->elem)->item(0);
		$contentB = $this->elem->getElementsByTagName("description")->item(0);

		if ($contentA && !$contentB) {
			return $this->subtree_or_text($contentA);
		}


		if ($contentB && !$contentA) {
			return $this->subtree_or_text($contentB);
		}

		if ($contentA && $contentB) {
			$resultA = $this->subtree_or_text($contentA);
			$resultB = $this->subtree_or_text($contentB);

			return mb_strlen($resultA) > mb_strlen($resultB) ? $resultA : $resultB;
		}
	}

	function get_description() {
		$summary = $this->elem->getElementsByTagName("description")->item(0);

		if ($summary) {
			return $summary->nodeValue;
		}
	}

	function get_categories() {
		$categories = $this->elem->getElementsByTagName("category");
		$cats = array();

		foreach ($categories as $cat) {
			array_push($cats, trim($cat->nodeValue));
		}

		$categories = $this->xpath->query("dc:subject", $this->elem);

		foreach ($categories as $cat) {
			array_push($cats, clean(trim($cat->nodeValue)));
		}

		return $cats;
	}

	function get_enclosures() {
		$enclosures = $this->elem->getElementsByTagName("enclosure");

		$encs = array();

		foreach ($enclosures as $enclosure) {
			$enc = new FeedEnclosure();

			$enc->type = clean($enclosure->getAttribute("type"));
			$enc->link = clean($enclosure->getAttribute("url"));
			$enc->length = clean($enclosure->getAttribute("length"));
			$enc->height = clean($enclosure->getAttribute("height"));
			$enc->width = clean($enclosure->getAttribute("width"));

			array_push($encs, $enc);
		}

		$encs = array_merge($encs, parent::get_enclosures());

		return $encs;
	}

	function get_language() {
		$languages = $this->doc->getElementsByTagName('language');

		if (count($languages) == 0) {
			return "";
		}

		return clean($languages[0]->textContent);
	}

}
