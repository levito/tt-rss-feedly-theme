<?php
use andreskrey\Readability\Readability;
use andreskrey\Readability\Configuration;

class Af_Readability extends Plugin {

	/* @var PluginHost $host */
	private $host;

	function about() {
		return array(1.0,
			"Try to inline article content using Readability",
			"fox");
	}

	function flags() {
		return array("needs_curl" => true);
	}

	function save() {
		$enable_share_anything = checkbox_to_sql_bool($_POST["enable_share_anything"]);

		$this->host->set($this, "enable_share_anything", $enable_share_anything);

		echo __("Data saved.");
	}

	function init($host)
	{
		$this->host = $host;

		if (version_compare(PHP_VERSION, '5.6.0', '<')) {
			return;
		}

		$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
		$host->add_hook($host::HOOK_PREFS_TAB, $this);
		$host->add_hook($host::HOOK_PREFS_EDIT_FEED, $this);
		$host->add_hook($host::HOOK_PREFS_SAVE_FEED, $this);

		$host->add_filter_action($this, "action_inline", __("Inline content"));
	}

	function hook_prefs_tab($args) {
		if ($args != "prefFeeds") return;

		print "<div dojoType=\"dijit.layout.AccordionPane\" 
			title=\"<i class='material-icons'>extension</i> ".__('Readability settings (af_readability)')."\">";

		if (version_compare(PHP_VERSION, '5.6.0', '<')) {
			print_error("This plugin requires PHP version 5.6.");
		}

		print_notice("Enable the plugin for specific feeds in the feed editor.");

		print "<form dojoType=\"dijit.form.Form\">";

		print "<script type=\"dojo/method\" event=\"onSubmit\" args=\"evt\">
			evt.preventDefault();
			if (this.validate()) {
				console.log(dojo.objectToQuery(this.getValues()));
				new Ajax.Request('backend.php', {
					parameters: dojo.objectToQuery(this.getValues()),
					onComplete: function(transport) {
						Notify.info(transport.responseText);
					}
				});
				//this.reset();
			}
			</script>";

		print_hidden("op", "pluginhandler");
		print_hidden("method", "save");
		print_hidden("plugin", "af_readability");

		$enable_share_anything = $this->host->get($this, "enable_share_anything");

		print_checkbox("enable_share_anything", $enable_share_anything);
		print "&nbsp;<label for=\"enable_share_anything\">" . __("Use Readability for pages shared via bookmarklet.") . "</label>";

		print "<p>"; print_button("submit", __("Save"));
		print "</form>";

		$enabled_feeds = $this->host->get($this, "enabled_feeds");
		if (!is_array($enabled_feeds)) $enabled_feeds = array();

		$enabled_feeds = $this->filter_unknown_feeds($enabled_feeds);
		$this->host->set($this, "enabled_feeds", $enabled_feeds);

		if (count($enabled_feeds) > 0) {
			print "<h3>" . __("Currently enabled for (click to edit):") . "</h3>";

			print "<ul class='panel panel-scrollable list list-unstyled'>";
			foreach ($enabled_feeds as $f) {
				print "<li>" .
					"<i class='material-icons'>rss_feed</i> <a href='#'
						onclick='CommonDialogs.editFeed($f)'>".
					Feeds::getFeedTitle($f) . "</a></li>";
			}
			print "</ul>";
		}

		print "</div>";
	}

	function hook_prefs_edit_feed($feed_id) {
		print "<div class=\"dlgSec\">".__("Readability")."</div>";
		print "<div class=\"dlgSecCont\">";

		$enabled_feeds = $this->host->get($this, "enabled_feeds");
		if (!is_array($enabled_feeds)) $enabled_feeds = array();

		$key = array_search($feed_id, $enabled_feeds);
		$checked = $key !== FALSE ? "checked" : "";

		print "<hr/><input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" id=\"af_readability_enabled\"
			name=\"af_readability_enabled\"
			$checked>&nbsp;<label for=\"af_readability_enabled\">".__('Inline article content')."</label>";

		print "</div>";
	}

	function hook_prefs_save_feed($feed_id) {
		$enabled_feeds = $this->host->get($this, "enabled_feeds");
		if (!is_array($enabled_feeds)) $enabled_feeds = array();

		$enable = checkbox_to_sql_bool($_POST["af_readability_enabled"]);
		$key = array_search($feed_id, $enabled_feeds);

		if ($enable) {
			if ($key === FALSE) {
				array_push($enabled_feeds, $feed_id);
			}
		} else {
			if ($key !== FALSE) {
				unset($enabled_feeds[$key]);
			}
		}

		$this->host->set($this, "enabled_feeds", $enabled_feeds);
	}

	/**
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	function hook_article_filter_action($article, $action) {
		return $this->process_article($article);
	}

	public function extract_content($url) {

		global $fetch_effective_url;

		$tmp = fetch_file_contents([
			"url" => $url,
			"http_accept" => "text/*",
			"type" => "text/html"]);

		if ($tmp && mb_strlen($tmp) < 1024 * 500) {
			$tmpdoc = new DOMDocument("1.0", "UTF-8");

			if (!$tmpdoc->loadHTML($tmp))
				return false;

			if (strtolower($tmpdoc->encoding) != 'utf-8') {
				$tmpxpath = new DOMXPath($tmpdoc);

				foreach ($tmpxpath->query("//meta") as $elem) {
					$elem->parentNode->removeChild($elem);
				}

				$tmp = $tmpdoc->saveHTML();
			}

			$r = new Readability(new Configuration());

			try {
				if ($r->parse($tmp)) {

					$tmpxpath = new DOMXPath($r->getDOMDOcument());
					$entries = $tmpxpath->query('(//a[@href]|//img[@src])');

					foreach ($entries as $entry) {
						if ($entry->hasAttribute("href")) {
							$entry->setAttribute("href",
									rewrite_relative_url($fetch_effective_url, $entry->getAttribute("href")));

						}

						if ($entry->hasAttribute("src")) {
							$entry->setAttribute("src",
									rewrite_relative_url($fetch_effective_url, $entry->getAttribute("src")));

						}
					}

					return $r->getContent();
				}

			} catch (Exception $e) {
				return false;
			}

		}

		return false;
	}

	function process_article($article) {

		$extracted_content = $this->extract_content($article["link"]);

		# let's see if there's anything of value in there
		$content_test = trim(strip_tags(sanitize($extracted_content)));

		if ($content_test) {
			$article["content"] = $extracted_content;
		}

		return $article;
	}

	function hook_article_filter($article) {

		$enabled_feeds = $this->host->get($this, "enabled_feeds");
		if (!is_array($enabled_feeds)) return $article;

		$key = array_search($article["feed"]["id"], $enabled_feeds);
		if ($key === FALSE) return $article;

		return $this->process_article($article);

	}

	function api_version() {
		return 2;
	}

	private function filter_unknown_feeds($enabled_feeds) {
		$tmp = array();

		foreach ($enabled_feeds as $feed) {

			$sth = $this->pdo->prepare("SELECT id FROM ttrss_feeds WHERE id = ? AND owner_uid = ?");
			$sth->execute([$feed, $_SESSION['uid']]);

			if ($row = $sth->fetch()) {
				array_push($tmp, $feed);
			}
		}

		return $tmp;
	}

}
