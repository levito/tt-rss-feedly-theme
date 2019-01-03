<?php
class Mail extends Plugin {

	/* @var PluginHost $host */
	private $host;

	function about() {
		return array(1.0,
			"Share article via email",
			"fox");
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_BUTTON, $this);
		$host->add_hook($host::HOOK_PREFS_TAB, $this);
	}

	function get_js() {
		return file_get_contents(dirname(__FILE__) . "/mail.js");
	}

	function save() {
		$addresslist = $_POST["addresslist"];

		$this->host->set($this, "addresslist", $addresslist);

		echo __("Mail addresses saved.");
	}

	function hook_prefs_tab($args) {
		if ($args != "prefPrefs") return;

		print "<div dojoType=\"dijit.layout.AccordionPane\" 
			title=\"<i class='material-icons'>mail</i> ".__('Mail plugin')."\">";

		print "<p>" . __("You can set predefined email addressed here (comma-separated list):") . "</p>";

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
			print_hidden("plugin", "mail");

			$addresslist = $this->host->get($this, "addresslist");

			print "<textarea dojoType=\"dijit.form.SimpleTextarea\" style='font-size : 12px; width : 50%' rows=\"3\"
				name='addresslist'>$addresslist</textarea>";

			print "<p><button dojoType=\"dijit.form.Button\" type=\"submit\">".
				__("Save")."</button>";

			print "</form>";

		print "</div>";
	}

	function hook_article_button($line) {
		return "<i class='material-icons' style=\"cursor : pointer\"
					onclick=\"Plugins.Mail.send(".$line["id"].")\"
					title='".__('Forward by email')."'>mail</i>";
	}

	function emailArticle() {

		$ids = explode(",", $_REQUEST['param']);
		$ids_qmarks = arr_qmarks($ids);

		print_hidden("op", "pluginhandler");
		print_hidden("plugin", "mail");
		print_hidden("method", "sendEmail");

		$sth = $this->pdo->prepare("SELECT email, full_name FROM ttrss_users WHERE
			id = ?");
		$sth->execute([$_SESSION['uid']]);

		if ($row = $sth->fetch()) {
			$user_email = htmlspecialchars($row['email']);
			$user_name = htmlspecialchars($row['full_name']);
		}

		if (!$user_name) $user_name = $_SESSION['name'];

		print_hidden("from_email", "$user_email");
		print_hidden("from_name", "$user_name");

		require_once "lib/MiniTemplator.class.php";

		$tpl = new MiniTemplator;

		$tpl->readTemplateFromFile("templates/email_article_template.txt");

		$tpl->setVariable('USER_NAME', $_SESSION["name"], true);
		$tpl->setVariable('USER_EMAIL', $user_email, true);
		$tpl->setVariable('TTRSS_HOST', $_SERVER["HTTP_HOST"], true);

		$sth = $this->pdo->prepare("SELECT DISTINCT link, content, title, note
			FROM ttrss_user_entries, ttrss_entries WHERE id = ref_id AND
			id IN ($ids_qmarks) AND owner_uid = ?");
		$sth->execute(array_merge($ids, [$_SESSION['uid']]));

		if (count($ids) > 1) {
			$subject = __("[Forwarded]") . " " . __("Multiple articles");
		}

		while ($line = $sth->fetch()) {

			if (!$subject)
				$subject = __("[Forwarded]") . " " . htmlspecialchars($line["title"]);

			$tpl->setVariable('ARTICLE_TITLE', strip_tags($line["title"]));
			$tnote = strip_tags($line["note"]);
			if( $tnote != ''){
				$tpl->setVariable('ARTICLE_NOTE', $tnote, true);
				$tpl->addBlock('note');
			}
			$tpl->setVariable('ARTICLE_URL', strip_tags($line["link"]));

			$tpl->addBlock('article');
		}

		$tpl->addBlock('email');

		$content = "";
		$tpl->generateOutputToString($content);

		print "<table width='100%'><tr><td>";

		$addresslist = explode(",", $this->host->get($this, "addresslist"));

		print __('To:');

		print "</td><td>";

/*		print "<input dojoType=\"dijit.form.ValidationTextBox\" required=\"true\"
				style=\"width : 30em;\"
				name=\"destination\" id=\"emailArticleDlg_destination\">"; */

		print_select("destination", "", $addresslist, 'style="width: 30em" dojoType="dijit.form.ComboBox"');

/*		print "<div class=\"autocomplete\" id=\"emailArticleDlg_dst_choices\"
	style=\"z-index: 30; display : none\"></div>"; */

		print "</td></tr><tr><td>";

		print __('Subject:');

		print "</td><td>";

		print "<input dojoType=\"dijit.form.ValidationTextBox\" required=\"true\"
				style=\"width : 30em;\"
				name=\"subject\" value=\"$subject\" id=\"subject\">";

		print "</td></tr>";

		print "<tr><td colspan='2'><textarea dojoType=\"dijit.form.SimpleTextarea\"
			style='height : 200px; font-size : 12px; width : 98%' rows=\"20\"
			name='content'>$content</textarea>";

		print "</td></tr></table>";

		print "<div class='dlgButtons'>";
		print "<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('emailArticleDlg').execute()\">".__('Send e-mail')."</button> ";
		print "<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('emailArticleDlg').hide()\">".__('Cancel')."</button>";
		print "</div>";

		//return;
	}

	function sendEmail() {
		$reply = array();

		/*$mail->AddReplyTo(strip_tags($_REQUEST['from_email']),
			strip_tags($_REQUEST['from_name']));
		//$mail->AddAddress($_REQUEST['destination']);
		$addresses = explode(';', $_REQUEST['destination']);
		foreach($addresses as $nextaddr)
			$mail->AddAddress($nextaddr);

		$mail->IsHTML(false);
		$mail->Subject = $_REQUEST['subject'];
		$mail->Body = $_REQUEST['content'];

		$rc = $mail->Send(); */

		$to = $_REQUEST["destination"];
		$subject = strip_tags($_REQUEST["subject"]);
		$message = strip_tags($_REQUEST["content"]);
		$from = strip_tags($_REQUEST["from_email"]);

		$mailer = new Mailer();

		$rc = $mailer->mail(["to_address" => $to,
			"headers" => ["Reply-To: $from"],
			"subject" => $subject,
			"message" => $message]);

		if (!$rc) {
			$reply['error'] =  $mailer->error();
		} else {
			//save_email_address($destination);
			$reply['message'] = "UPDATE_COUNTERS";
		}

		print json_encode($reply);
	}

	/* function completeEmails() {
		$search = $_REQUEST["search"];

		print "<ul>";

		foreach ($_SESSION['stored_emails'] as $email) {
			if (strpos($email, $search) !== false) {
				print "<li>$email</li>";
			}
		}

		print "</ul>";
	} */

	function api_version() {
		return 2;
	}

}
