define(["dojo/_base/declare"], function (declare) {
	Helpers = {
		clearFeedAccessKeys: function() {
			if (confirm(__("This will invalidate all previously generated feed URLs. Continue?"))) {
				Notify.progress("Clearing URLs...");

				xhrPost("backend.php", {op: "pref-feeds", method: "clearKeys"}, () => {
					Notify.info("Generated URLs cleared.");
				});
			}

			return false;
		},
		updateEventLog: function() {
			xhrPost("backend.php", { op: "pref-system" }, (transport) => {
				dijit.byId('systemConfigTab').attr('content', transport.responseText);
				Notify.close();
			});
		},
		clearEventLog: function() {
			if (confirm(__("Clear event log?"))) {

				Notify.progress("Loading, please wait...");

				xhrPost("backend.php", {op: "pref-system", method: "clearLog"}, () => {
					this.updateEventLog();
				});
			}
		},
		editProfiles: function() {

			if (dijit.byId("profileEditDlg"))
				dijit.byId("profileEditDlg").destroyRecursive();

			const query = "backend.php?op=pref-prefs&method=editPrefProfiles";

			// noinspection JSUnusedGlobalSymbols
			const dialog = new dijit.Dialog({
				id: "profileEditDlg",
				title: __("Settings Profiles"),
				style: "width: 600px",
				getSelectedProfiles: function () {
					return Tables.getSelected("pref-profiles-list");
				},
				removeSelected: function () {
					const sel_rows = this.getSelectedProfiles();

					if (sel_rows.length > 0) {
						if (confirm(__("Remove selected profiles? Active and default profiles will not be removed."))) {
							Notify.progress("Removing selected profiles...", true);

							const query = {
								op: "rpc", method: "remprofiles",
								ids: sel_rows.toString()
							};

							xhrPost("backend.php", query, () => {
								Notify.close();
								Helpers.editProfiles();
							});
						}

					} else {
						alert(__("No profiles selected."));
					}
				},
				activateProfile: function () {
					const sel_rows = this.getSelectedProfiles();

					if (sel_rows.length == 1) {
						if (confirm(__("Activate selected profile?"))) {
							Notify.progress("Loading, please wait...");

							xhrPost("backend.php", {op: "rpc", method: "setprofile", id: sel_rows.toString()}, () => {
								window.location.reload();
							});
						}

					} else {
						alert(__("Please choose a profile to activate."));
					}
				},
				addProfile: function () {
					if (this.validate()) {
						Notify.progress("Creating profile...", true);

						const query = {op: "rpc", method: "addprofile", title: dialog.attr('value').newprofile};

						xhrPost("backend.php", query, () => {
							Notify.close();
							Helpers.editProfiles();
						});

					}
				},
				execute: function () {
					if (this.validate()) {
					}
				},
				href: query
			});

			dialog.show();
		},
		customizeCSS: function() {
			const query = "backend.php?op=pref-prefs&method=customizeCSS";

			if (dijit.byId("cssEditDlg"))
				dijit.byId("cssEditDlg").destroyRecursive();

			const dialog = new dijit.Dialog({
				id: "cssEditDlg",
				title: __("Customize stylesheet"),
				style: "width: 600px",
				execute: function () {
					Notify.progress('Saving data...', true);

					xhrPost("backend.php", this.attr('value'), () => {
						window.location.reload();
					});

				},
				href: query
			});

			dialog.show();
		},
		confirmReset: function() {
			if (confirm(__("Reset to defaults?"))) {
				xhrPost("backend.php", {op: "pref-prefs", method: "resetconfig"}, (transport) => {
					Helpers.refresh();
					Notify.info(transport.responseText);
				});
			}
		},
		clearPluginData: function(name) {
			if (confirm(__("Clear stored data for this plugin?"))) {
				Notify.progress("Loading, please wait...");

				xhrPost("backend.php", {op: "pref-prefs", method: "clearplugindata", name: name}, () => {
					Helpers.refresh();
				});
			}
		},
		refresh: function() {
			xhrPost("backend.php", { op: "pref-prefs" }, (transport) => {
				dijit.byId('genConfigTab').attr('content', transport.responseText);
				Notify.close();
			});
		},
		OPML: {
			import: function() {
				const opml_file = $("opml_file");

				if (opml_file.value.length == 0) {
					alert(__("Please choose an OPML file first."));
					return false;
				} else {
					Notify.progress("Importing, please wait...", true);

					Element.show("upload_iframe");

					return true;
				}
			},
			onImportComplete: function(iframe) {
				if (!iframe.contentDocument.body.innerHTML) return false;

				Element.show(iframe);

				Notify.close();

				if (dijit.byId('opmlImportDlg'))
					dijit.byId('opmlImportDlg').destroyRecursive();

				const content = iframe.contentDocument.body.innerHTML;

				const dialog = new dijit.Dialog({
					id: "opmlImportDlg",
					title: __("OPML Import"),
					style: "width: 600px",
					onCancel: function () {
						window.location.reload();
					},
					execute: function () {
						window.location.reload();
					},
					content: content
				});

				dojo.connect(dialog, "onShow", function () {
					Element.hide(iframe);
				});

				dialog.show();
			},
			export: function() {
				console.log("export");
				window.open("backend.php?op=opml&method=export&" + dojo.formToQuery("opmlExportForm"));
			},
			changeKey: function() {
				if (confirm(__("Replace current OPML publishing address with a new one?"))) {
					Notify.progress("Trying to change address...", true);

					xhrJson("backend.php", {op: "pref-feeds", method: "regenOPMLKey"}, (reply) => {
						if (reply) {
							const new_link = reply.link;
							const e = $('pub_opml_url');

							if (new_link) {
								e.href = new_link;
								e.innerHTML = new_link;

								new Effect.Highlight(e);

								Notify.close();

							} else {
								Notify.error("Could not change feed URL.");
							}
						}
					});
				}
				return false;
			},
		}
	};

	return Helpers;
});
