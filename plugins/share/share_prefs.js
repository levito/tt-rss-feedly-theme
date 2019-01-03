Plugins.Share = {
	clearKeys: function() {
		if (confirm(__("This will invalidate all previously shared article URLs. Continue?"))) {
			Notify.progress("Clearing URLs...");

			const query = {op: "pluginhandler", plugin: "share", method: "clearArticleKeys"};

			xhrPost("backend.php", query, () => {
				Notify.info("Shared URLs cleared.");
			});
		}

		return false;
	}
};
