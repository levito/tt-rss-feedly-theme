<?php
class NSFW extends Plugin {
	private $host;

	function about() {
		return array(1.0,
			"Hide article content based on tags",
			"fox",
			false);
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_RENDER_ARTICLE, $this);
		$host->add_hook($host::HOOK_RENDER_ARTICLE_CDM, $this);
		$host->add_hook($host::HOOK_PREFS_TAB, $this);

	}

	function get_js() {
		return file_get_contents(dirname(__FILE__) . "/init.js");
	}

	function hook_render_article($article) {
		$tags = array_map("trim", explode(",", $this->host->get($this, "tags")));
		$a_tags = array_map("trim", explode(",", $article["tag_cache"]));

		if (count(array_intersect($tags, $a_tags)) > 0) {
			$article["content"] = "<div class='nswf wrapper'><button onclick=\"nsfwShow(this)\">".__("Not work safe (click to toggle)")."</button>
				<div class='nswf content' style='display : none'>".$article["content"]."</div></div>";
		}

		return $article;
	}

	function hook_render_article_cdm($article) {
		$tags = array_map("trim", explode(",", $this->host->get($this, "tags")));
		$a_tags = array_map("trim", explode(",", $article["tag_cache"]));

		if (count(array_intersect($tags, $a_tags)) > 0) {
			$article["content"] = "<div class='nswf wrapper'><button onclick=\"nsfwShow(this)\">".__("Not work safe (click to toggle)")."</button>
				<div class='nswf content' style='display : none'>".$article["content"]."</div></div>";
		}

		return $article;
	}

	function hook_prefs_tab($args) {
		if ($args != "prefPrefs") return;

		print "<div dojoType=\"dijit.layout.AccordionPane\" 
			title=\"<i class='material-icons'>extension</i> ".__("NSFW Plugin")."\">";

		print "<br/>";

		$tags = $this->host->get($this, "tags");

		print "<form dojoType=\"dijit.form.Form\">";

		print "<script type=\"dojo/method\" event=\"onSubmit\" args=\"evt\">
			evt.preventDefault();
			if (this.validate()) {
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
			print_hidden("plugin", "nsfw");

			print "<table width=\"100%\" class=\"prefPrefsList\">";

			print "<tr><td width=\"40%\">".__("Tags to consider NSFW (comma-separated)")."</td>";
			print "<td class=\"prefValue\"><input dojoType=\"dijit.form.ValidationTextBox\" required=\"1\" name=\"tags\" value=\"$tags\"></td></tr>";

			print "</table>";

			print "<p><button dojoType=\"dijit.form.Button\" type=\"submit\">".
				__("Save")."</button>";

			print "</form>";

			print "</div>"; #pane
	}

	function save() {
		$tags = explode(",", $_POST["tags"]);
		$tags = array_map("trim", $tags);
		$tags = array_map("mb_strtolower", $tags);
		$tags = join(", ", $tags);

		$this->host->set($this, "tags", $tags);

		echo __("Configuration saved.");
	}

	function api_version() {
		return 2;
	}

}