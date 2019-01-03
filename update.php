#!/usr/bin/env php
<?php
	set_include_path(dirname(__FILE__) ."/include" . PATH_SEPARATOR .
		get_include_path());

	define('DISABLE_SESSIONS', true);

	chdir(dirname(__FILE__));

	require_once "autoload.php";
	require_once "functions.php";
	require_once "config.php";
	require_once "sanity_check.php";
	require_once "db.php";
	require_once "db-prefs.php";

	if (!defined('PHP_EXECUTABLE'))
		define('PHP_EXECUTABLE', '/usr/bin/php');

	$pdo = Db::pdo();

	init_plugins();

	$longopts = array("feeds",
			"feedbrowser",
			"daemon",
			"daemon-loop",
			"task:",
			"cleanup-tags",
			"quiet",
			"log:",
			"log-level:",
			"indexes",
			"pidlock:",
			"update-schema",
			"convert-filters",
			"force-update",
			"gen-search-idx",
			"list-plugins",
			"debug-feed:",
			"force-refetch",
			"force-rehash",
			"help");

	foreach (PluginHost::getInstance()->get_commands() as $command => $data) {
		array_push($longopts, $command . $data["suffix"]);
	}

	$options = getopt("", $longopts);

	if (!is_array($options)) {
		die("error: getopt() failed. ".
			"Most probably you are using PHP CGI to run this script ".
			"instead of required PHP CLI. Check tt-rss wiki page on updating feeds for ".
			"additional information.\n");
	}

	if (count($options) == 0 && !defined('STDIN')) {
		?> <html>
		<head>
		<title>Tiny Tiny RSS data update script.</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		</head>

		<body>
		<div class="floatingLogo"><img src="images/logo_small.png"></div>
		<h1><?php echo __("Tiny Tiny RSS data update script.") ?></h1>

		<?php print_error("Please run this script from the command line. Use option \"--help\" to display command help if this error is displayed erroneously."); ?>

		</body></html>
	<?php
		exit;
	}

	if (count($options) == 0 || isset($options["help"]) ) {
		print "Tiny Tiny RSS data update script.\n\n";
		print "Options:\n";
		print "  --feeds              - update feeds\n";
		print "  --feedbrowser        - update feedbrowser\n";
		print "  --daemon             - start single-process update daemon\n";
		print "  --task N             - create lockfile using this task id\n";
		print "  --cleanup-tags       - perform tags table maintenance\n";
		print "  --quiet              - don't output messages to stdout\n";
		print "  --log FILE           - log messages to FILE\n";
		print "  --log-level N        - log verbosity level\n";
		print "  --indexes            - recreate missing schema indexes\n";
		print "  --update-schema      - update database schema\n";
		print "  --gen-search-idx     - generate basic PostgreSQL fulltext search index\n";
		print "  --convert-filters    - convert type1 filters to type2\n";
		print "  --force-update       - force update of all feeds\n";
		print "  --list-plugins       - list all available plugins\n";
		print "  --debug-feed N       - perform debug update of feed N\n";
		print "  --force-refetch      - debug update: force refetch feed data\n";
		print "  --force-rehash       - debug update: force rehash articles\n";
		print "  --help               - show this help\n";
		print "Plugin options:\n";

		foreach (PluginHost::getInstance()->get_commands() as $command => $data) {
			$args = $data['arghelp'];
			printf(" --%-19s - %s\n", "$command $args", $data["description"]);
		}

		return;
	}

	if (!isset($options['daemon'])) {
		require_once "errorhandler.php";
	}

	if (!isset($options['update-schema'])) {
		$schema_version = get_schema_version();

		if ($schema_version != SCHEMA_VERSION) {
			die("Schema version is wrong, please upgrade the database.\n");
		}
	}

	Debug::set_enabled(true);

	if (isset($options["log-level"])) {
	    Debug::set_loglevel((int)$options["log-level"]);
    }

	if (isset($options["log"])) {
		Debug::set_logfile($options["log"]);
        Debug::log("Logging to " . $options["log"]);
		Debug::set_quiet(isset($options['quiet']));
    } else {
	    if (isset($options['quiet'])) {
			Debug::set_loglevel(Debug::$LOG_DISABLED);
        }
    }

	if (!isset($options["daemon"])) {
		$lock_filename = "update.lock";
	} else {
		$lock_filename = "update_daemon.lock";
	}

	if (isset($options["task"])) {
		Debug::log("Using task id " . $options["task"]);
		$lock_filename = $lock_filename . "-task_" . $options["task"];
	}

	if (isset($options["pidlock"])) {
		$my_pid = $options["pidlock"];
		$lock_filename = "update_daemon-$my_pid.lock";

	}

	Debug::log("Lock: $lock_filename");

	$lock_handle = make_lockfile($lock_filename);
	$must_exit = false;

	if (isset($options["task"]) && isset($options["pidlock"])) {
		$waits = $options["task"] * 5;
		Debug::log("Waiting before update ($waits)");
		sleep($waits);
	}

	// Try to lock a file in order to avoid concurrent update.
	if (!$lock_handle) {
		die("error: Can't create lockfile ($lock_filename). ".
			"Maybe another update process is already running.\n");
	}

	if (isset($options["force-update"])) {
		Debug::log("marking all feeds as needing update...");

		$pdo->query( "UPDATE ttrss_feeds SET
          last_update_started = '1970-01-01', last_updated = '1970-01-01'");
	}

	if (isset($options["feeds"])) {
		RSSUtils::update_daemon_common();
		RSSUtils::housekeeping_common(true);

		PluginHost::getInstance()->run_hooks(PluginHost::HOOK_UPDATE_TASK, "hook_update_task", $op);
	}

	if (isset($options["feedbrowser"])) {
		$count = RSSUtils::update_feedbrowser_cache();
		print "Finished, $count feeds processed.\n";
	}

	if (isset($options["daemon"])) {
		while (true) {
			$quiet = (isset($options["quiet"])) ? "--quiet" : "";
            $log = isset($options['log']) ? '--log '.$options['log'] : '';
            $log_level = isset($options['log-level']) ? '--log-level '.$options['log-level'] : '';

			passthru(PHP_EXECUTABLE . " " . $argv[0] ." --daemon-loop $quiet $log $log_level");

			// let's enforce a minimum spawn interval as to not forkbomb the host
			$spawn_interval = max(60, DAEMON_SLEEP_INTERVAL);

			Debug::log("Sleeping for $spawn_interval seconds...");
			sleep($spawn_interval);
		}
	}

	if (isset($options["daemon-loop"])) {
		if (!make_stampfile('update_daemon.stamp')) {
			Debug::log("warning: unable to create stampfile\n");
		}

		RSSUtils::update_daemon_common(isset($options["pidlock"]) ? 50 : DAEMON_FEED_LIMIT);

		if (!isset($options["pidlock"]) || $options["task"] == 0)
			RSSUtils::housekeeping_common(true);

		PluginHost::getInstance()->run_hooks(PluginHost::HOOK_UPDATE_TASK, "hook_update_task", $op);
	}

	if (isset($options["cleanup-tags"])) {
		$rc = cleanup_tags( 14, 50000);
		Debug::log("$rc tags deleted.\n");
	}

	if (isset($options["indexes"])) {
		Debug::log("PLEASE BACKUP YOUR DATABASE BEFORE PROCEEDING!");
		Debug::log("Type 'yes' to continue.");

		if (read_stdin() != 'yes')
			exit;

		Debug::log("clearing existing indexes...");

		if (DB_TYPE == "pgsql") {
			$sth = $pdo->query( "SELECT relname FROM
				pg_catalog.pg_class WHERE relname LIKE 'ttrss_%'
					AND relname NOT LIKE '%_pkey'
				AND relkind = 'i'");
		} else {
			$sth = $pdo->query( "SELECT index_name,table_name FROM
				information_schema.statistics WHERE index_name LIKE 'ttrss_%'");
		}

		while ($line = $sth->fetch()) {
			if (DB_TYPE == "pgsql") {
				$statement = "DROP INDEX " . $line["relname"];
				Debug::log($statement);
			} else {
				$statement = "ALTER TABLE ".
					$line['table_name']." DROP INDEX ".$line['index_name'];
				Debug::log($statement);
			}
			$pdo->query($statement);
		}

		Debug::log("reading indexes from schema for: " . DB_TYPE);

		$fp = fopen("schema/ttrss_schema_" . DB_TYPE . ".sql", "r");
		if ($fp) {
			while ($line = fgets($fp)) {
				$matches = array();

				if (preg_match("/^create index ([^ ]+) on ([^ ]+)$/i", $line, $matches)) {
					$index = $matches[1];
					$table = $matches[2];

					$statement = "CREATE INDEX $index ON $table";

					Debug::log($statement);
					$pdo->query($statement);
				}
			}
			fclose($fp);
		} else {
			Debug::log("unable to open schema file.");
		}
		Debug::log("all done.");
	}

	if (isset($options["convert-filters"])) {
		Debug::log("WARNING: this will remove all existing type2 filters.");
		Debug::log("Type 'yes' to continue.");

		if (read_stdin() != 'yes')
			exit;

		Debug::log("converting filters...");

		$pdo->query("DELETE FROM ttrss_filters2");

		$res = $pdo->query("SELECT * FROM ttrss_filters ORDER BY id");

		while ($line = $res->fetch()) {
			$owner_uid = $line["owner_uid"];

			// date filters are removed
			if ($line["filter_type"] != 5) {
				$filter = array();

				if (sql_bool_to_bool($line["cat_filter"])) {
					$feed_id = "CAT:" . (int)$line["cat_id"];
				} else {
					$feed_id = (int)$line["feed_id"];
				}

				$filter["enabled"] = $line["enabled"] ? "on" : "off";
				$filter["rule"] = array(
					json_encode(array(
						"reg_exp" => $line["reg_exp"],
						"feed_id" => $feed_id,
						"filter_type" => $line["filter_type"])));

				$filter["action"] = array(
					json_encode(array(
						"action_id" => $line["action_id"],
						"action_param_label" => $line["action_param"],
						"action_param" => $line["action_param"])));

				// Oh god it's full of hacks

				$_REQUEST = $filter;
				$_SESSION["uid"] = $owner_uid;

				$filters = new Pref_Filters($_REQUEST);
				$filters->add();
			}
		}

	}

	if (isset($options["update-schema"])) {
		Debug::log("checking for updates (" . DB_TYPE . ")...");

		$updater = new DbUpdater(Db::pdo(), DB_TYPE, SCHEMA_VERSION);

		if ($updater->isUpdateRequired()) {
			Debug::log("schema update required, version " . $updater->getSchemaVersion() . " to " . SCHEMA_VERSION);
			Debug::log("WARNING: please backup your database before continuing.");
			Debug::log("Type 'yes' to continue.");

			if (read_stdin() != 'yes')
				exit;

			for ($i = $updater->getSchemaVersion() + 1; $i <= SCHEMA_VERSION; $i++) {
				Debug::log("performing update up to version $i...");

				$result = $updater->performUpdateTo($i, false);

				Debug::log($result ? "OK!" : "FAILED!");

				if (!$result) return;

			}
		} else {
			Debug::log("update not required.");
		}

	}

	if (isset($options["gen-search-idx"])) {
		echo "Generating search index (stemming set to English)...\n";

		$res = $pdo->query("SELECT COUNT(id) AS count FROM ttrss_entries WHERE tsvector_combined IS NULL");
		$row = $res->fetch();
		$count = $row['count'];

		print "Articles to process: $count.\n";

		$limit = 500;
		$processed = 0;

		$sth = $pdo->prepare("SELECT id, title, content FROM ttrss_entries WHERE
          tsvector_combined IS NULL ORDER BY id LIMIT ?");
		$sth->execute([$limit]);

		$usth = $pdo->prepare("UPDATE ttrss_entries
          SET tsvector_combined = to_tsvector('english', ?) WHERE id = ?");

		while (true) {

			while ($line = $sth->fetch()) {
				$tsvector_combined = mb_substr(strip_tags($line["title"] . " " . $line["content"]), 0, 1000000);

				$usth->execute([$tsvector_combined, $line['id']]);

				$processed++;
			}

			print "Processed $processed articles...\n";

			if ($processed < $limit) {
				echo "All done.\n";
				break;
			}
		}
	}

	if (isset($options["list-plugins"])) {
		$tmppluginhost = new PluginHost();
		$tmppluginhost->load_all($tmppluginhost::KIND_ALL, false);
		$enabled = array_map("trim", explode(",", PLUGINS));

		echo "List of all available plugins:\n";

		foreach ($tmppluginhost->get_plugins() as $name => $plugin) {
			$about = $plugin->about();

			$status = $about[3] ? "system" : "user";

			if (in_array($name, $enabled)) $name .= "*";

			printf("%-50s %-10s v%.2f (by %s)\n%s\n\n",
				$name, $status, $about[0], $about[2], $about[1]);
		}

		echo "Plugins marked by * are currently enabled for all users.\n";

	}

	if (isset($options["debug-feed"])) {
		$feed = $options["debug-feed"];

		if (isset($options["force-refetch"])) $_REQUEST["force_refetch"] = true;
		if (isset($options["force-rehash"])) $_REQUEST["force_rehash"] = true;

		Debug::set_loglevel(Debug::$LOG_EXTENDED);

		$rc = RSSUtils::update_rss_feed($feed) != false ? 0 : 1;

		exit($rc);
	}

	PluginHost::getInstance()->run_commands($options);

	if (file_exists(LOCK_DIRECTORY . "/$lock_filename"))
		if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN')
			fclose($lock_handle);
		unlink(LOCK_DIRECTORY . "/$lock_filename");
?>
