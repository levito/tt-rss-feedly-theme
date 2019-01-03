<?php
	if (file_exists("install") && !file_exists("config.php")) {
		header("Location: install/");
	}

	if (!file_exists("config.php")) {
		print "<b>Fatal Error</b>: You forgot to copy
		<b>config.php-dist</b> to <b>config.php</b> and edit it.\n";
		exit;
	}

	// we need a separate check here because functions.php might get parsed
	// incorrectly before 5.3 because of :: syntax.
	if (version_compare(PHP_VERSION, '5.6.0', '<')) {
		print "<b>Fatal Error</b>: PHP version 5.6.0 or newer required. You're using " . PHP_VERSION . ".\n";
		exit;
	}

	set_include_path(dirname(__FILE__) ."/include" . PATH_SEPARATOR .
		get_include_path());

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
	<title>Tiny Tiny RSS</title>
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

	<style type="text/css">
	<?php
		foreach (PluginHost::getInstance()->get_plugins() as $n => $p) {
			if (method_exists($p, "get_css")) {
				echo $p->get_css();
			}
		}
	?>
	</style>

	<link rel="shortcut icon" type="image/png" href="images/favicon.png"/>
	<link rel="icon" type="image/png" sizes="72x72" href="images/favicon-72px.png" />

	<script>
		dojoConfig = {
			async: true,
			cacheBust: "<?php echo get_scripts_timestamp(); ?>",
			packages: [
				{ name: "fox", location: "../../js" },
			]
		};
	</script>

	<?php
	foreach (array("lib/prototype.js",
				"lib/scriptaculous/scriptaculous.js?load=effects,controls",
				"lib/dojo/dojo.js",
				"lib/dojo/tt-rss-layer.js",
				"js/tt-rss.js",
				"js/common.js",
				"errors.php?mode=js") as $jsfile) {

		echo javascript_tag($jsfile);

	} ?>

	<script type="text/javascript">
		require({cache:{}});
	</script>

	<script type="text/javascript">
	<?php
		foreach (PluginHost::getInstance()->get_plugins() as $n => $p) {
			if (method_exists($p, "get_js")) {
			    $script = $p->get_js();

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
	<meta name="referrer" content="no-referrer"/>
</head>

<body class="flat ttrss_main ttrss_index">

<div id="overlay" style="display : block">
	<div id="overlay_inner">
		<?php echo __("Loading, please wait...") ?>
		<div dojoType="dijit.ProgressBar" places="0" style="width : 300px" id="loading_bar"
	     progress="0" maximum="100">
		</div>
		<noscript><br/><?php print_error('Javascript is disabled. Please enable it.') ?></noscript>
	</div>
</div>

<div id="notify" class="notify"></div>
<div id="cmdline" style="display : none"></div>

<div id="main" dojoType="dijit.layout.BorderContainer">
    <div id="feeds-holder" dojoType="dijit.layout.ContentPane" region="leading" style="width : 20%" splitter="true">
        <div id="feedlistLoading">
            <img src='images/indicator_tiny.gif'/>
            <?php echo  __("Loading, please wait..."); ?></div>
        <div id="feedTree"></div>
    </div>

    <div dojoType="dijit.layout.BorderContainer" region="center" id="content-wrap">
        <div id="toolbar-frame" dojoType="dijit.layout.ContentPane" region="top">
            <div id="toolbar" dojoType="dijit.Toolbar">

            <i class="material-icons net-alert" style="display : none"
                title="<?php echo __("Communication problem with server.") ?>">error_outline</i>

            <i class="material-icons log-alert" style="display : none"
                 title="<?php echo __("Recent entries found in event log.") ?>">warning</i>

            <i id="updates-available" class="material-icons icon-new-version" style="display : none"
               title="<?php echo __('Updates are available from Git.') ?>">new_releases</i>

            <?php
            foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_MAIN_TOOLBAR_BUTTON) as $p) {
                echo $p->hook_main_toolbar_button();
            }
            ?>

            <form id="toolbar-headlines" action="" onsubmit='return false'>

            </form>

            <form id="toolbar-main" action="" onsubmit='return false'>

            <select name="view_mode" title="<?php echo __('Show articles') ?>"
                onchange="App.onViewModeChanged()"
                dojoType="dijit.form.Select">
                <option selected="selected" value="adaptive"><?php echo __('Adaptive') ?></option>
                <option value="all_articles"><?php echo __('All Articles') ?></option>
                <option value="marked"><?php echo __('Starred') ?></option>
                <option value="published"><?php echo __('Published') ?></option>
                <option value="unread"><?php echo __('Unread') ?></option>
                <option value="has_note"><?php echo __('With Note') ?></option>
                <!-- <option value="noscores"><?php echo __('Ignore Scoring') ?></option> -->
            </select>

            <select title="<?php echo __('Sort articles') ?>"
                onchange="App.onViewModeChanged()"
                dojoType="dijit.form.Select" name="order_by">
                <option selected="selected" value="default"><?php echo __('Default') ?></option>
                <option value="feed_dates"><?php echo __('Newest first') ?></option>
                <option value="date_reverse"><?php echo __('Oldest first') ?></option>
                <option value="title"><?php echo __('Title') ?></option>
            </select>

            <div dojoType="dijit.form.ComboButton" onclick="Feeds.catchupCurrent()">
                <span><?php echo __('Mark as read') ?></span>
                <div dojoType="dijit.DropDownMenu">
                    <div dojoType="dijit.MenuItem" onclick="Feeds.catchupCurrent('1day')">
                        <?php echo __('Older than one day') ?>
                    </div>
                    <div dojoType="dijit.MenuItem" onclick="Feeds.catchupCurrent('1week')">
                        <?php echo __('Older than one week') ?>
                    </div>
                    <div dojoType="dijit.MenuItem" onclick="Feeds.catchupCurrent('2week')">
                        <?php echo __('Older than two weeks') ?>
                    </div>
                </div>
            </div>

            </form>

            <div class="action-chooser">

                <?php
                    foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_TOOLBAR_BUTTON) as $p) {
                         echo $p->hook_toolbar_button();
                    }
                ?>

                <div dojoType="dijit.form.DropDownButton">
                    <span><?php echo __('Actions...') ?></span>
                    <div dojoType="dijit.Menu" style="display: none">
                        <div dojoType="dijit.MenuItem" onclick="App.onActionSelected('qmcPrefs')"><?php echo __('Preferences...') ?></div>
                        <div dojoType="dijit.MenuItem" onclick="App.onActionSelected('qmcSearch')"><?php echo __('Search...') ?></div>
                        <div dojoType="dijit.MenuItem" disabled="1"><?php echo __('Feed actions:') ?></div>
                        <div dojoType="dijit.MenuItem" onclick="App.onActionSelected('qmcAddFeed')"><?php echo __('Subscribe to feed...') ?></div>
                        <div dojoType="dijit.MenuItem" onclick="App.onActionSelected('qmcEditFeed')"><?php echo __('Edit this feed...') ?></div>
                        <div dojoType="dijit.MenuItem" onclick="App.onActionSelected('qmcRemoveFeed')"><?php echo __('Unsubscribe') ?></div>
                        <div dojoType="dijit.MenuItem" disabled="1"><?php echo __('All feeds:') ?></div>
                        <div dojoType="dijit.MenuItem" onclick="App.onActionSelected('qmcCatchupAll')"><?php echo __('Mark as read') ?></div>
                        <div dojoType="dijit.MenuItem" onclick="App.onActionSelected('qmcShowOnlyUnread')"><?php echo __('(Un)hide read feeds') ?></div>
                        <div dojoType="dijit.MenuItem" disabled="1"><?php echo __('Other actions:') ?></div>
                        <div dojoType="dijit.MenuItem" onclick="App.onActionSelected('qmcToggleWidescreen')"><?php echo __('Toggle widescreen mode') ?></div>
                        <div dojoType="dijit.MenuItem" onclick="App.onActionSelected('qmcToggleNightMode')"><?php echo __('Toggle night mode') ?></div>
                        <div dojoType="dijit.MenuItem" onclick="App.onActionSelected('qmcHKhelp')"><?php echo __('Keyboard shortcuts help') ?></div>

                        <?php
                            foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_ACTION_ITEM) as $p) {
                             echo $p->hook_action_item();
                            }
                        ?>

                        <?php if (!$_SESSION["hide_logout"]) { ?>
                            <div dojoType="dijit.MenuItem" onclick="App.onActionSelected('qmcLogout')"><?php echo __('Logout') ?></div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div> <!-- toolbar -->
        </div> <!-- toolbar pane -->
        <div id="headlines-wrap-inner" dojoType="dijit.layout.BorderContainer" region="center">
            <div id="floatingTitle" style="display : none"></div>
            <div id="headlines-frame" dojoType="dijit.layout.ContentPane" tabindex="0"
                    region="center">
                <div id="headlinesInnerContainer">
                    <div class="whiteBox"><?php echo __('Loading, please wait...') ?></div>
                </div>
            </div>
            <div id="content-insert" dojoType="dijit.layout.ContentPane" region="bottom"
                style="height : 50%" splitter="true"></div>
        </div>
    </div>
</div>

</body>
</html>
