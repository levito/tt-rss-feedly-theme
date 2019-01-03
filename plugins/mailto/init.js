Plugins.Mailto = {
	send: function (id) {
		if (!id) {
			const ids = Headlines.getSelected();

			if (ids.length == 0) {
				alert(__("No articles selected."));
				return;
			}

			id = ids.toString();
		}

		if (dijit.byId("emailArticleDlg"))
			dijit.byId("emailArticleDlg").destroyRecursive();

		const query = "backend.php?op=pluginhandler&plugin=mailto&method=emailArticle&param=" + encodeURIComponent(id);

		const dialog = new dijit.Dialog({
			id: "emailArticleDlg",
			title: __("Forward article by email"),
			style: "width: 600px",
			href: query});

		dialog.show();
	}
};

// override default hotkey action if enabled
Plugins.Mail = Plugins.Mail || {};

Plugins.Mail.onHotkey = function(id) {
	Plugins.Mailto.send(id);
};