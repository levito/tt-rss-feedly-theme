<?php
	set_include_path(dirname(__FILE__) ."/include" . PATH_SEPARATOR .
		get_include_path());

	require_once "autoload.php";
	require_once "functions.php";
	require_once "sessions.php";
	require_once "sanity_check.php";
	require_once "config.php";
	require_once "db.php";
	require_once "db-prefs.php";

	if (!init_plugins()) return;

	$op = $_REQUEST['op'];

	if ($op == "publish"){
		$key = $_REQUEST["key"];
		$pdo = Db::pdo();

		$sth = $pdo->prepare( "SELECT owner_uid
				FROM ttrss_access_keys WHERE
				access_key = ? AND feed_id = 'OPML:Publish'");
		$sth->execute([$key]);

		if ($row = $sth->fetch()) {
			$owner_uid = $row['owner_uid'];

			$opml = new Opml($_REQUEST);
			$opml->opml_export("", $owner_uid, true, false);

		} else {
			print "<error>User not found</error>";
		}
	}

?>
