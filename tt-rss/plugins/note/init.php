<?php
class Note extends Plugin {

	/* @var PluginHost $host */
	private $host;

	function about() {
		return array(1.0,
			"Adds support for setting article notes",
			"fox");
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_BUTTON, $this);
	}

	function get_js() {
		return file_get_contents(dirname(__FILE__) . "/note.js");
	}


	function hook_article_button($line) {
		return "<i class='material-icons' onclick=\"Plugins.Note.edit(".$line["id"].")\"
			style='cursor : pointer' title='".__('Edit article note')."'>note</i>";
	}

	function edit() {
		$param = $_REQUEST['param'];

		$sth = $this->pdo->prepare("SELECT note FROM ttrss_user_entries WHERE
			ref_id = ? AND owner_uid = ?");
		$sth->execute([$param, $_SESSION['uid']]);

		if ($row = $sth->fetch()) {

			$note = $row['note'];

			print_hidden("id", "$param");
			print_hidden("op", "pluginhandler");
			print_hidden("method", "setNote");
			print_hidden("plugin", "note");

			print "<table width='100%'><tr><td>";
			print "<textarea dojoType=\"dijit.form.SimpleTextarea\"
				style='font-size : 12px; width : 98%; height: 100px;'
				placeHolder='body#ttrssMain { font-size : 14px; };'
				name='note'>$note</textarea>";
			print "</td></tr></table>";

		}

		print "<div class='dlgButtons'>";
		print "<button dojoType=\"dijit.form.Button\"
			onclick=\"dijit.byId('editNoteDlg').execute()\">".__('Save')."</button> ";
		print "<button dojoType=\"dijit.form.Button\"
			onclick=\"dijit.byId('editNoteDlg').hide()\">".__('Cancel')."</button>";
		print "</div>";

	}

	function setNote() {
		$id = $_REQUEST["id"];
		$note = trim(strip_tags($_REQUEST["note"]));

		$sth = $this->pdo->prepare("UPDATE ttrss_user_entries SET note = ?
			WHERE ref_id = ? AND owner_uid = ?");
		$sth->execute([$note, $id, $_SESSION['uid']]);

		$formatted_note = Article::format_article_note($id, $note);

		print json_encode(array("note" => $formatted_note,
				"raw_length" => mb_strlen($note)));
	}

	function api_version() {
		return 2;
	}

}