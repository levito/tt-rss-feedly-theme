function exportData() {
	try {

		var query = "backend.php?op=pluginhandler&plugin=import_export&method=exportData";

		if (dijit.byId("dataExportDlg"))
			dijit.byId("dataExportDlg").destroyRecursive();

		var exported = 0;

		dialog = new dijit.Dialog({
			id: "dataExportDlg",
			title: __("Export Data"),
			style: "width: 600px",
			prepare: function() {

				Notify.progress("Loading, please wait...");

				new Ajax.Request("backend.php", {
					parameters: "op=pluginhandler&plugin=import_export&method=exportrun&offset=" + exported,
					onComplete: function(transport) {
						try {
							var rv = JSON.parse(transport.responseText);

							if (rv && rv.exported != undefined) {
								if (rv.exported > 0) {

									exported += rv.exported;

									$("export_status_message").innerHTML =
										"<img src='images/indicator_tiny.gif'> " +
										"Exported %d articles, please wait...".replace("%d",
											exported);

									setTimeout('dijit.byId("dataExportDlg").prepare()', 2000);

								} else {

									$("export_status_message").innerHTML =
										ngettext("Finished, exported %d article. You can download the data <a class='visibleLink' href='%u'>here</a>.", "Finished, exported %d articles. You can download the data <a class='visibleLink' href='%u'>here</a>.", exported)
										.replace("%d", exported)
										.replace("%u", "backend.php?op=pluginhandler&plugin=import_export&subop=exportget");

									exported = 0;

								}

							} else {
								$("export_status_message").innerHTML =
									"Error occured, could not export data.";
							}
						} catch (e) {
							App.Error.report(e);
						}

						Notify.close();

					} });

			},
			execute: function() {
				if (this.validate()) {



				}
			},
			href: query});

		dialog.show();


	} catch (e) {
		App.Error.report(e);
	}
}

function dataImportComplete(iframe) {
	try {
		if (!iframe.contentDocument.body.innerHTML) return false;

		Element.hide(iframe);

		Notify.close();

		if (dijit.byId('dataImportDlg'))
			dijit.byId('dataImportDlg').destroyRecursive();

		var content = iframe.contentDocument.body.innerHTML;

		dialog = new dijit.Dialog({
			id: "dataImportDlg",
			title: __("Data Import"),
			style: "width: 600px",
			onCancel: function() {

			},
			content: content});

		dialog.show();

	} catch (e) {
		App.Error.report(e);
	}
}

function importData() {

	var file = $("export_file");

	if (file.value.length == 0) {
		alert(__("Please choose the file first."));
		return false;
	} else {
		Notify.progress("Importing, please wait...", true);

		Element.show("data_upload_iframe");

		return true;
	}
}


