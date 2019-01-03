<?php
	if (file_exists("install") && !file_exists("config.php")) {
		header("Location: install/");
	}

	set_include_path(dirname(__FILE__) ."/include" . PATH_SEPARATOR .
		get_include_path());

	if (!file_exists("config.php")) {
		print "<b>Fatal Error</b>: You forgot to copy
		<b>config.php-dist</b> to <b>config.php</b> and edit it.\n";
		exit;
	}

	require_once "autoload.php";
	require_once "sessions.php";
	require_once "functions.php";
	require_once "sanity_check.php";
	require_once "version.php";
	require_once "config.php";
	require_once "db-prefs.php";

	if (!init_plugins()) return;

	login_sequence();

	header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
	<title>Tiny Tiny RSS : <?php echo __("Preferences") ?></title>
    <meta name="viewport" content="initial-scale=1,width=device-width" />

	<script type="text/javascript">
		var __ttrss_version = "<?php echo VERSION ?>"
	</script>

	<?php if ($_SESSION["uid"]) {
		$theme = get_pref("USER_CSS_THEME", false, false);
		if ($theme && theme_exists("$theme")) {
			echo stylesheet_tag(get_theme_path($theme), 'theme_css');
		} else {
			echo stylesheet_tag("css/default.css", 'theme_css');
		}
	}
	?>

	<?php print_user_stylesheet() ?>

	<link rel="shortcut icon" type="image/png" href="images/favicon.png"/>
	<link rel="icon" type="image/png" sizes="72x72" href="images/favicon-72px.png" />

	<script>
		dojoConfig = {
			async: true,
			cacheBust: "<?php echo get_scripts_timestamp(); ?>",
			packages: [
				{ name: "lib", location: "../" },
				{ name: "fox", location: "../../js" },
			]
		};
	</script>

	<?php
	foreach (array("lib/prototype.js",
				"lib/scriptaculous/scriptaculous.js?load=effects,controls",
				"lib/dojo/dojo.js",
				"lib/dojo/tt-rss-layer.js",
				"js/common.js",
				"js/prefs.js",
				"errors.php?mode=js") as $jsfile) {

		echo javascript_tag($jsfile);

	} ?>

    <script type="text/javascript">
		require({cache:{}});
    </script>

	<script type="text/javascript">
	<?php
		foreach (PluginHost::getInstance()->get_plugins() as $n => $p) {
			if (method_exists($p, "get_prefs_js")) {
				$script = $p->get_prefs_js();

				if ($script) {
					echo "try {
					    $script
					} catch (e) {
                        console.warn('failed to initialize plugin JS: $n', e);
                    }";
				}
			}
		}

		init_js_translations();
	?>
	</script>

	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
</head>

<body class="flat ttrss_main ttrss_prefs">

<div id="notify" class="notify"></div>
<div id="cmdline" style="display : none"></div>

<div id="overlay">
	<div id="overlay_inner">
		<?php echo __("Loading, please wait...") ?>
		<div dojoType="dijit.ProgressBar" places="0" style="width : 300px" id="loading_bar"
	     progress="0" maximum="100">
		</div>
		<noscript><br/><?php print_error('Javascript is disabled. Please enable it.') ?></noscript>
	</div>
</div>

<div id="header">
	<!-- <a href='#' onclick="showHelp()"><?php echo __("Keyboard shortcuts") ?></a> | -->
	<a href="#" onclick="document.location.href = 'index.php'"><?php echo __('Exit preferences') ?></a>
</div>

<div id="main" dojoType="dijit.layout.BorderContainer">
    <div dojoType="dijit.layout.TabContainer" region="center" id="pref-tabs">
        <div id="genConfigTab" dojoType="dijit.layout.ContentPane"
            href="backend.php?op=pref-prefs"
            title="<i class='material-icons'>settings</i> <?php echo __('Preferences') ?>"></div>
        <div id="feedConfigTab" dojoType="dijit.layout.ContentPane"
            href="backend.php?op=pref-feeds"
            title="<i class='material-icons'>rss_feed</i>  <?php echo __('Feeds') ?>"></div>
        <div id="filterConfigTab" dojoType="dijit.layout.ContentPane"
            style="padding : 0px"
            href="backend.php?op=pref-filters"
            title="<i class='material-icons'>filter_list1</i> <?php echo __('Filters') ?>"></div>
        <div id="labelConfigTab" dojoType="dijit.layout.ContentPane"
            style="padding : 0px"
            href="backend.php?op=pref-labels"
            title="<i class='material-icons'>label_outline1</i> <?php echo __('Labels') ?>"></div>
        <?php if ($_SESSION["access_level"] >= 10) { ?>
            <div id="userConfigTab" dojoType="dijit.layout.ContentPane"
                style="padding : 0px"
                href="backend.php?op=pref-users"
                title="<i class='material-icons'>person</i> <?php echo __('Users') ?>"></div>
            <div id="systemConfigTab" dojoType="dijit.layout.ContentPane"
                href="backend.php?op=pref-system"
                title="<i class='material-icons'>info_outline</i> <?php echo __('System') ?>"></div>
        <?php } ?>
        <?php
            PluginHost::getInstance()->run_hooks(PluginHost::HOOK_PREFS_TABS,
                "hook_prefs_tabs", false);
        ?>
        </div>
    <div id="footer" dojoType="dijit.layout.ContentPane" region="bottom">
        <a class="insensitive" target="_blank" href="http://tt-rss.org/">
        Tiny Tiny RSS</a>
        <?php if (!defined('HIDE_VERSION')) { ?>
             v<?php echo VERSION ?>
        <?php } ?>
        &copy; 2005-<?php echo date('Y') ?>
        <a class="insensitive" target="_blank"
        href="http://fakecake.org/">Andrew Dolgov</a>
    </div> <!-- footer -->
</div>

</body>
</html>
