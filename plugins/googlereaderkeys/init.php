<?php
class GoogleReaderKeys extends Plugin {
	private $host;

	function about() {
		return array(1.0,
			"Keyboard hotkeys emulate Google Reader",
			"markwaters");
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_HOTKEY_MAP, $this);
	}

	function hook_hotkey_map($hotkeys) {

		$hotkeys["j"]		= "next_article_noscroll";
		$hotkeys["k"]		= "prev_article_noscroll";
		$hotkeys["*n"]		= "next_feed";
		$hotkeys["*p"]		= "prev_feed";
		$hotkeys["v"]		= "open_in_new_window";
		$hotkeys["r"]		= "feed_refresh";
		$hotkeys["m"]		= "toggle_unread";
		$hotkeys["o"]		= "toggle_expand";
		$hotkeys["(13)|enter"]	= "toggle_expand";
		$hotkeys["*(191)|?"]    = "help_dialog";
		$hotkeys["(32)|space"]	= "next_article";
		$hotkeys["(38)|up"]	= "article_scroll_up";
		$hotkeys["(40)|down"]	= "article_scroll_down";

		return $hotkeys;
	}

	function api_version() {
		return 2;
	}

}