Plugins.Psql_Trgm = {
	showRelated: function (id) {
		const query = "backend.php?op=pluginhandler&plugin=af_psql_trgm&method=showrelated&param=" + encodeURIComponent(id);

		if (dijit.byId("trgmRelatedDlg"))
			dijit.byId("trgmRelatedDlg").destroyRecursive();

		dialog = new dijit.Dialog({
			id: "trgmRelatedDlg",
			title: __("Related articles"),
			style: "width: 600px",
			execute: function () {

			},
			href: query,
		});

		dialog.show();
	}
};

