<?php
class Import_Export extends Plugin implements IHandler {
	private $host;

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_PREFS_TAB, $this);
		$host->add_command("xml-import", "import articles from XML", $this, ":", "FILE");
	}

	function about() {
		return array(1.0,
			"Imports and exports user data using neutral XML format",
			"fox");
	}

	function xml_import($args) {

		$filename = $args['xml_import'];

		if (!is_file($filename)) {
			print "error: input filename ($filename) doesn't exist.\n";
			return;
		}

		Debug::log("please enter your username:");

		$username = trim(read_stdin());

		Debug::log("importing $filename for user $username...\n");

		$sth = $this->pdo->prepare("SELECT id FROM ttrss_users WHERE login = ?");
		$sth->execute($username);

		if ($row = $sth->fetch()) {
			$owner_uid = $row['id'];

			$this->perform_data_import($filename, $owner_uid);
		} else {
			print "error: could not find user $username.\n";
			return;
		}
	}

	function get_prefs_js() {
		return file_get_contents(dirname(__FILE__) . "/import_export.js");
	}

	function hook_prefs_tab($args) {
		if ($args != "prefFeeds") return;

		print "<div dojoType=\"dijit.layout.AccordionPane\" 
			title=\"<i class='material-icons'>import_export</i> ".__('Import and export')."\">";

		print_notice(__("You can export and import your Starred and Archived articles for safekeeping or when migrating between tt-rss instances of same version."));

		print "<p>";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return exportData()\">".
			__('Export my data')."</button> ";

		print "<hr>";

		print "<iframe id=\"data_upload_iframe\"
			name=\"data_upload_iframe\" onload=\"dataImportComplete(this)\"
			style=\"width: 400px; height: 100px; display: none;\"></iframe>";

		print "<form name=\"import_form\" style='display : block' target=\"data_upload_iframe\"
			enctype=\"multipart/form-data\" method=\"POST\"
			action=\"backend.php\">
			<label class=\"dijitButton\">".__("Choose file...")."
				<input style=\"display : none\" id=\"export_file\" name=\"export_file\" type=\"file\">&nbsp;
			</label>
			<input type=\"hidden\" name=\"op\" value=\"pluginhandler\">
			<input type=\"hidden\" name=\"plugin\" value=\"import_export\">
			<input type=\"hidden\" name=\"method\" value=\"dataimport\">
			<button dojoType=\"dijit.form.Button\" onclick=\"return importData();\" type=\"submit\">" .
			__('Import') . "</button>";

		print "</form>";

		print "</p>";

		print "</div>"; # pane
	}

	function csrf_ignore($method) {
		return in_array($method, array("exportget"));
	}

	/**
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	function before($method) {
		return $_SESSION["uid"] != false;
	}

	function after() {
		return true;
	}

	/**
	 * @SuppressWarnings(unused)
	 */
	function exportget() {
		$exportname = CACHE_DIR . "/export/" .
			sha1($_SESSION['uid'] . $_SESSION['login']) . ".xml";

		if (file_exists($exportname)) {
			header("Content-type: text/xml");

			$timestamp_suffix = date("Y-m-d", filemtime($exportname));

			if (function_exists('gzencode')) {
				header("Content-Disposition: attachment; filename=TinyTinyRSS_exported_${timestamp_suffix}.xml.gz");
				echo gzencode(file_get_contents($exportname));
			} else {
				header("Content-Disposition: attachment; filename=TinyTinyRSS_exported_${timestamp_suffix}.xml");
				echo file_get_contents($exportname);
			}
		} else {
			echo "File not found.";
		}
	}

	function exportrun() {
		$offset = (int) $_REQUEST['offset'];
		$exported = 0;
		$limit = 250;

		if ($offset < 10000 && is_writable(CACHE_DIR . "/export")) {

			$sth = $this->pdo->prepare("SELECT
					ttrss_entries.guid,
					ttrss_entries.title,
					content,
					marked,
					published,
					score,
					note,
					link,
					tag_cache,
					label_cache,
					ttrss_feeds.title AS feed_title,
					ttrss_feeds.feed_url AS feed_url,
					ttrss_entries.updated
				FROM
					ttrss_user_entries LEFT JOIN ttrss_feeds ON (ttrss_feeds.id = feed_id),
					ttrss_entries
				WHERE
					(marked = true OR feed_id IS NULL) AND
					ref_id = ttrss_entries.id AND
					ttrss_user_entries.owner_uid = ?
				ORDER BY ttrss_entries.id LIMIT $limit OFFSET $offset");

			$sth->execute([$_SESSION['uid']]);

			$exportname = sha1($_SESSION['uid'] . $_SESSION['login']);

			if ($offset == 0) {
				$fp = fopen(CACHE_DIR . "/export/$exportname.xml", "w");
				fputs($fp, "<articles schema-version=\"".SCHEMA_VERSION."\">");
			} else {
				$fp = fopen(CACHE_DIR . "/export/$exportname.xml", "a");
			}

			if ($fp) {

				$exported = 0;
				while ($line = $sth->fetch(PDO::FETCH_ASSOC)) {
					++$exported;

					fputs($fp, "<article>\n");

					foreach ($line as $k => $v) {

						fputs($fp, "  ");

						if (is_bool($v))
							$v = (int) $v;

						if (!$v || is_numeric($v)) {
							fputs($fp, "<$k>$v</$k>\n");
						} else {
							$v = str_replace("]]>", "]]]]><![CDATA[>", $v);
							fputs($fp, "<$k><![CDATA[$v]]></$k>\n");
						}
					}

					fputs($fp, "</article>\n");
				}

				if ($exported < $limit && $exported > 0) {
					fputs($fp, "</articles>");
				}

				fclose($fp);
			}

		}

		print json_encode(array("exported" => $exported));
	}

	function perform_data_import($filename, $owner_uid) {

		$num_imported = 0;
		$num_processed = 0;
		$num_feeds_created = 0;

		libxml_disable_entity_loader(false);

		$doc = new DOMDocument();

		if (!$doc_loaded = @$doc->load($filename)) {
			$contents = file_get_contents($filename);

			if ($contents) {
				$data = @gzuncompress($contents);
			}

			if (!$data) {
				$data = @gzdecode($contents);
			}

			if ($data)
				$doc_loaded = $doc->loadXML($data);
		}

		libxml_disable_entity_loader(true);

		if ($doc_loaded) {

			$xpath = new DOMXpath($doc);

			$container = $doc->firstChild;

			if ($container && $container->hasAttribute('schema-version')) {
				$schema_version = $container->getAttribute('schema-version');

				if ($schema_version != SCHEMA_VERSION) {
					print "<p>" .__("Could not import: incorrect schema version.") . "</p>";
					return;
				}

			} else {
				print "<p>" . __("Could not import: unrecognized document format.") . "</p>";
				return;
			}

			$articles = $xpath->query("//article");

			foreach ($articles as $article_node) {
				if ($article_node->childNodes) {

					$ref_id = 0;

					$article = array();

					foreach ($article_node->childNodes as $child) {
						if ($child->nodeName == 'content' || $child->nodeName == 'label_cache') {
							$article[$child->nodeName] = $child->nodeValue;
						} else {
							$article[$child->nodeName] = clean($child->nodeValue);
						}
					}

					//print_r($article);

					if ($article['guid']) {

						++$num_processed;

						$this->pdo->beginTransaction();

						//print 'GUID:' . $article['guid'] . "\n";

						$sth = $this->pdo->prepare("SELECT id FROM ttrss_entries
							WHERE guid = ?");
						$sth->execute([$article['guid']]);

						if ($row = $sth->fetch()) {
							$ref_id = $row['id'];
						} else {
							$sth = $this->pdo->prepare(
								"INSERT INTO ttrss_entries
									(title,
									guid,
									link,
									updated,
									content,
									content_hash,
									no_orig_date,
									date_updated,
									date_entered,
									comments,
									num_comments,
									author)
								VALUES
									(?, ?, ?, ?, ?, ?,
									false,
									NOW(),
									NOW(),
									'',
									'0',
									'')");

							$sth->execute([
								$article['title'],
								$article['guid'],
								$article['link'],
								$article['updated'],
								$article['content'],
								sha1($article['content'])
							]);

							$sth = $this->pdo->prepare("SELECT id FROM ttrss_entries
								WHERE guid = ?");
							$sth->execute([$article['guid']]);

							if ($row = $sth->fetch()) {
								$ref_id = $row['id'];
							}
						}

						//print "Got ref ID: $ref_id\n";

						if ($ref_id) {

							$feed = NULL;

							if ($article['feed_url'] && $article['feed_title']) {

								$sth = $this->pdo->prepare("SELECT id FROM ttrss_feeds
									WHERE feed_url = ? AND owner_uid = ?");
								$sth->execute([$article['feed_url'], $owner_uid]);

								if ($row = $sth->fetch()) {
									$feed = $row['id'];
								} else {
									// try autocreating feed in Uncategorized...

									$sth = $this->pdo->prepare("INSERT INTO ttrss_feeds (owner_uid,
										feed_url, title) VALUES (?, ?, ?)");
									$res = $sth->execute([$owner_uid, $article['feed_url'], $article['feed_title']]);

									if ($res) {
										$sth = $this->pdo->prepare("SELECT id FROM ttrss_feeds
											WHERE feed_url = ? AND owner_uid = ?");
										$sth->execute([$article['feed_url'], $owner_uid]);

										if ($row = $sth->fetch()) {
											++$num_feeds_created;

											$feed = $row['id'];
										}
									}
								}
							}

							if ($feed)
								$feed_qpart = "feed_id = " . (int) $feed;
							else
								$feed_qpart = "feed_id IS NULL";

							//print "$ref_id / $feed / " . $article['title'] . "\n";

							$sth = $this->pdo->prepare("SELECT int_id FROM ttrss_user_entries
								WHERE ref_id = ? AND owner_uid = ? AND $feed_qpart");
							$sth->execute([$ref_id, $owner_uid]);

							if (!$sth->fetch()) {

								$score = (int) $article['score'];

								$tag_cache = $article['tag_cache'];
								$note = $article['note'];

								//print "Importing " . $article['title'] . "<br/>";

								++$num_imported;

								$sth = $this->pdo->prepare(
									"INSERT INTO ttrss_user_entries
									(ref_id, owner_uid, feed_id, unread, last_read, marked,
										published, score, tag_cache, label_cache, uuid, note)
									VALUES (?, ?, ?, false,
										NULL, ?, ?, ?, ?, '', '', ?)");

								$res = $sth->execute([
									$ref_id,
									$owner_uid,
									$feed,
									(int)sql_bool_to_bool($article['marked']),
									(int)sql_bool_to_bool($article['published']),
									$score,
									$tag_cache,
									$note]);

								if ($res) {

									$label_cache = json_decode($article['label_cache'], true);

									if (is_array($label_cache) && $label_cache["no-labels"] != 1) {
										foreach ($label_cache as $label) {
											Labels::create($label[1],
												$label[2], $label[3], $owner_uid);

											Labels::add_article($ref_id, $label[1], $owner_uid);
										}
									}
								}
							}
						}

						$this->pdo->commit();
					}
				}
			}

			print "<p>" .
				__("Finished: ").
				vsprintf(_ngettext("%d article processed, ", "%d articles processed, ", $num_processed), $num_processed).
				vsprintf(_ngettext("%d imported, ", "%d imported, ", $num_imported), $num_imported).
				vsprintf(_ngettext("%d feed created.", "%d feeds created.", $num_feeds_created), $num_feeds_created).
					"</p>";

		} else {

			print "<p>" . __("Could not load XML document.") . "</p>";

		}
	}

	function exportData() {

		print "<p style='text-align : center' id='export_status_message'>You need to prepare exported data first by clicking the button below.</p>";

		print "<div align='center'>";
		print "<button dojoType=\"dijit.form.Button\"
			onclick=\"dijit.byId('dataExportDlg').prepare()\">".
			__('Prepare data')."</button>";

		print "<button dojoType=\"dijit.form.Button\"
			onclick=\"dijit.byId('dataExportDlg').hide()\">".
			__('Close this window')."</button>";

		print "</div>";


	}

	function dataImport() {
		header("Content-Type: text/html"); # required for iframe

		print "<div style='text-align : center'>";

		if ($_FILES['export_file']['error'] != 0) {
			print_error(T_sprintf("Upload failed with error code %d (%s)",
				$_FILES['export_file']['error'],
				get_upload_error_message($_FILES['export_file']['error'])));
		} else {

			if (is_uploaded_file($_FILES['export_file']['tmp_name'])) {
				$tmp_file = tempnam(CACHE_DIR . '/upload', 'export');

				$result = move_uploaded_file($_FILES['export_file']['tmp_name'],
					$tmp_file);

				if (!$result) {
					print_error(__("Unable to move uploaded file."));
					return;
				}
			} else {
				print_error(__('Error: please upload OPML file.'));
				return;
			}

			if (is_file($tmp_file)) {
				$this->perform_data_import($tmp_file, $_SESSION['uid']);
				unlink($tmp_file);
			} else {
				print_error(__('No file uploaded.'));
				return;
			}
		}

		print "<button dojoType=\"dijit.form.Button\"
			onclick=\"dijit.byId('dataImportDlg').hide()\">".
			__('Close this window')."</button>";

		print "</div>";

	}

	function api_version() {
		return 2;
	}

}
