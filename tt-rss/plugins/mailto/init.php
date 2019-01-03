<?php
class MailTo extends Plugin {
	private $host;

	function about() {
		return array(1.0,
			"Share article via email (using mailto: links, invoking your mail client)",
			"fox");
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_BUTTON, $this);
	}

	function get_js() {
		return file_get_contents(dirname(__FILE__) . "/init.js");
	}

	function hook_article_button($line) {
		return "<i class='material-icons' style=\"cursor : pointer\"
					onclick=\"Plugins.Mailto.send(".$line["id"].")\"
					title='".__('Forward by email')."'>mail_outline</i>";
	}

	function emailArticle() {

		$ids = explode(",", $_REQUEST['param']);
		$ids_qmarks = arr_qmarks($ids);

		require_once "lib/MiniTemplator.class.php";

		$tpl = new MiniTemplator;

		$tpl->readTemplateFromFile("templates/email_article_template.txt");

		$tpl->setVariable('USER_NAME', $_SESSION["name"], true);
		//$tpl->setVariable('USER_EMAIL', $user_email, true);
		$tpl->setVariable('TTRSS_HOST', $_SERVER["HTTP_HOST"], true);


		$sth = $this->pdo->prepare("SELECT DISTINCT link, content, title
			FROM ttrss_user_entries, ttrss_entries WHERE id = ref_id AND
			id IN ($ids_qmarks) AND owner_uid = ?");
		$sth->execute(array_merge($ids, [$_SESSION['uid']]));

		if (count($ids) > 1) {
			$subject = __("[Forwarded]") . " " . __("Multiple articles");
		} else {
			$subject = "";
		}

		while ($line = $sth->fetch()) {

			if (!$subject)
				$subject = __("[Forwarded]") . " " . htmlspecialchars($line["title"]);

			$tpl->setVariable('ARTICLE_TITLE', strip_tags($line["title"]));
			$tpl->setVariable('ARTICLE_URL', strip_tags($line["link"]));

			$tpl->addBlock('article');
		}

		$tpl->addBlock('email');

		$content = "";
		$tpl->generateOutputToString($content);

		$mailto_link = htmlspecialchars("mailto:?subject=".rawurlencode($subject).
			"&body=".rawurlencode($content));

		print __("Clicking the following link to invoke your mail client:");

		print "<div class='panel text-center'>";
		print "<a target=\"_blank\" href=\"$mailto_link\">".
			__("Forward selected article(s) by email.")."</a>";
		print "</div>";

		print __("You should be able to edit the message before sending in your mail client.");

		print "<p>";

		print "<div style='text-align : center'>";
		print "<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('emailArticleDlg').hide()\">".__('Close this dialog')."</button>";
		print "</div>";

		//return;
	}

	function api_version() {
		return 2;
	}

}
