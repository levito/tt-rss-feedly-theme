<?php
class Dlg extends Handler_Protected {
	private $param;
    private $params;

    function before($method) {
		if (parent::before($method)) {
			header("Content-Type: text/html"); # required for iframe

			$this->param = $_REQUEST["param"];
			return true;
		}
		return false;
	}

	function importOpml() {
		print __("If you have imported labels and/or filters, you might need to reload preferences to see your new data.") . "</p>";

		print "<div class='panel panel-scrollable'>";

		$opml = new Opml($_REQUEST);

		$opml->opml_import($_SESSION["uid"]);

		print "</div>";

		print "<div align='center'>";
		print "<button dojoType=\"dijit.form.Button\"
			onclick=\"dijit.byId('opmlImportDlg').execute()\">".
			__('Close this window')."</button>";
		print "</div>";

		print "</div>";

		//return;
	}

	function pubOPMLUrl() {
		$url_path = Opml::opml_publish_url();

		print __("Your Public OPML URL is:");

		print "<div class='panel text-center'>";
		print "<a id='pub_opml_url' href='$url_path' target='_blank'>$url_path</a>";
		print "</div>";

		print "<div align='center'>";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return Helpers.OPML.changeKey()\">".
			__('Generate new URL')."</button> ";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return CommonDialogs.closeInfoBox()\">".
			__('Close this window')."</button>";

		print "</div>";

		//return;
	}

	function explainError() {
		print "<div class=\"errorExplained\">";

		if ($this->param == 1) {
			print __("Update daemon is enabled in configuration, but daemon process is not running, which prevents all feeds from updating. Please start the daemon process or contact instance owner.");

			$stamp = (int) file_get_contents(LOCK_DIRECTORY . "/update_daemon.stamp");

			print "<p>" . __("Last update:") . " " . date("Y.m.d, G:i", $stamp);

		}

		if ($this->param == 3) {
			print __("Update daemon is taking too long to perform a feed update. This could indicate a problem like crash or a hang. Please check the daemon process or contact instance owner.");

			$stamp = (int) file_get_contents(LOCK_DIRECTORY . "/update_daemon.stamp");

			print "<p>" . __("Last update:") . " " . date("Y.m.d, G:i", $stamp);

		}

		print "</div>";

		print "<div align='center'>";

		print "<button onclick=\"return CommonDialogs.closeInfoBox()\">".
			__('Close this window')."</button>";

		print "</div>";

		//return;
	}

	function printTagCloud() {
		print "<div class='panel text-center'>";

		// from here: http://www.roscripts.com/Create_tag_cloud-71.html

		$sth = $this->pdo->prepare("SELECT tag_name, COUNT(post_int_id) AS count
			FROM ttrss_tags WHERE owner_uid = ?
			GROUP BY tag_name ORDER BY count DESC LIMIT 50");
		$sth->execute([$_SESSION['uid']]);

		$tags = array();

		while ($line = $sth->fetch()) {
			$tags[$line["tag_name"]] = $line["count"];
		}

        if(count($tags) == 0 ){ return; }

		ksort($tags);

		$max_size = 32; // max font size in pixels
		$min_size = 11; // min font size in pixels

		// largest and smallest array values
		$max_qty = max(array_values($tags));
		$min_qty = min(array_values($tags));

		// find the range of values
		$spread = $max_qty - $min_qty;
		if ($spread == 0) { // we don't want to divide by zero
				$spread = 1;
		}

		// set the font-size increment
		$step = ($max_size - $min_size) / ($spread);

		// loop through the tag array
		foreach ($tags as $key => $value) {
			// calculate font-size
			// find the $value in excess of $min_qty
			// multiply by the font-size increment ($size)
			// and add the $min_size set above
			$size = round($min_size + (($value - $min_qty) * $step));

			$key_escaped = str_replace("'", "\\'", $key);

			echo "<a href=\"#\" onclick=\"Feeds.open({feed:'$key_escaped'}) \" style=\"font-size: " .
				$size . "px\" title=\"$value articles tagged with " .
				$key . '">' . $key . '</a> ';
		}



		print "</div>";

		print "<div align='center'>";
		print "<button dojoType=\"dijit.form.Button\"
			onclick=\"return CommonDialogs.closeInfoBox()\">".
			__('Close this window')."</button>";
		print "</div>";

	}

	function generatedFeed() {

		$this->params = explode(":", $this->param, 3);
		$feed_id = $this->params[0];
		$is_cat = (bool) $this->params[1];

		$key = get_feed_access_key($feed_id, $is_cat);

		$url_path = htmlspecialchars($this->params[2]) . "&key=" . $key;

		$feed_title = Feeds::getFeedTitle($feed_id, $is_cat);

		print "<div>".T_sprintf("%s can be accessed via the following secret URL:", $feed_title)."</div>";

		print "<div class='panel text-center'>";
		print "<a id='gen_feed_url' href='$url_path' target='_blank'>$url_path</a>";
		print "</div>";

		print "<div align='center'>";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return CommonDialogs.genUrlChangeKey('$feed_id', '$is_cat')\">".
			__('Generate new URL')."</button> ";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return CommonDialogs.closeInfoBox()\">".
			__('Close this window')."</button>";

		print "</div>";

		//return;
	}

	function defaultPasswordWarning() {

    	print_warning(__("You are using default tt-rss password. Please change it in the Preferences (Personal data / Authentication)."));

		print "<div align='center'>";
		print "<button dojoType=\"dijit.form.Button\" onclick=\"document.location.href = 'prefs.php'\">".
			__('Open Preferences')."</button> ";
		print "<button dojoType=\"dijit.form.Button\"
			onclick=\"return CommonDialogs.closeInfoBox()\">".
			__('Close this window')."</button>";
		print "</div>";
	}
}