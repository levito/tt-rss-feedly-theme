<?php
class Share extends Plugin {
	private $host;

	function about() {
		return array(1.0,
			"Share article by unique URL",
			"fox");
	}

	/* @var PluginHost $host */
	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_BUTTON, $this);
		$host->add_hook($host::HOOK_PREFS_TAB_SECTION, $this);
	}

	function get_js() {
		return file_get_contents(dirname(__FILE__) . "/share.js");
	}

	function get_css() {
		return file_get_contents(dirname(__FILE__) . "/share.css");
	}

	function get_prefs_js() {
		return file_get_contents(dirname(__FILE__) . "/share_prefs.js");
	}


	function unshare() {
		$id = $_REQUEST['id'];

		$sth = $this->pdo->prepare("UPDATE ttrss_user_entries SET uuid = '' WHERE int_id = ?
			AND owner_uid = ?");
		$sth->execute([$id, $_SESSION['uid']]);

		print "OK";
	}

	function hook_prefs_tab_section($id) {
		if ($id == "prefFeedsPublishedGenerated") {

			print "<hr/>";

			print "<p>" . __("You can disable all articles shared by unique URLs here.") . "</p>";

			print "<button class=\"alt-danger\" dojoType=\"dijit.form.Button\" onclick=\"return Plugins.Share.clearKeys()\">".
				__('Unshare all articles')."</button> ";

			print "</p>";

		}
	}

	// Silent
	function clearArticleKeys() {
		$sth = $this->pdo->prepare("UPDATE ttrss_user_entries SET uuid = '' WHERE
			owner_uid = ?");
		$sth->execute([$_SESSION['uid']]);

		return;
	}


	function newkey() {
		$id = $_REQUEST['id'];
		$uuid = uniqid_short();

		$sth = $this->pdo->prepare("UPDATE ttrss_user_entries SET uuid = ? WHERE int_id = ?
			AND owner_uid = ?");
		$sth->execute([$uuid, $id, $_SESSION['uid']]);

		print json_encode(array("link" => $uuid));
	}

	function hook_article_button($line) {
		$img_class = $line['uuid'] ? "shared" : "";

		return "<i id='SHARE-IMG-".$line['int_id']."' class='material-icons icon-share $img_class'
			style='cursor : pointer' onclick=\"Plugins.Share.shareArticle(".$line['int_id'].")\"
			title='".__('Share by URL')."'>link</i>";
	}

	function shareArticle() {
		$param = $_REQUEST['param'];

		$sth = $this->pdo->prepare("SELECT uuid FROM ttrss_user_entries WHERE int_id = ?
			AND owner_uid = ?");
		$sth->execute([$param, $_SESSION['uid']]);

		if ($row = $sth->fetch()) {

			$uuid = $row['uuid'];

			if (!$uuid) {
				$uuid = uniqid_short();

				$sth = $this->pdo->prepare("UPDATE ttrss_user_entries SET uuid = ? WHERE int_id = ?
					AND owner_uid = ?");
				$sth->execute([$uuid, $param, $_SESSION['uid']]);
			}

			print __("You can share this article by the following unique URL:") . "<br/>";

			$url_path = get_self_url_prefix();
			$url_path .= "/public.php?op=share&key=$uuid";

			print "<div class='panel text-center'>";
			print "<a id='gen_article_url' href='$url_path' target='_blank' rel='noopener noreferrer'>$url_path</a>";
			print "</div>";

			/* if (!label_find_id(__('Shared'), $_SESSION["uid"]))
				label_create(__('Shared'), $_SESSION["uid"]);

			label_add_article($ref_id, __('Shared'), $_SESSION['uid']); */


		} else {
			print "Article not found.";
		}

		print "<div align='center'>";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('shareArticleDlg').unshare()\">".
			__('Unshare article')."</button>";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('shareArticleDlg').newurl()\">".
			__('Generate new URL')."</button>";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('shareArticleDlg').hide()\">".
			__('Close this window')."</button>";

		print "</div>";
	}

	function api_version() {
		return 2;
	}

}