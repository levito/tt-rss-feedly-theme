'use strict'
/* global __, ngettext */
define(["dojo/_base/declare"], function (declare) {
	// noinspection JSUnusedGlobalSymbols
	CommonDialogs = {
		closeInfoBox: function() {
			const dialog = dijit.byId("infoBox");
			if (dialog)	dialog.hide();
		},
		uploadIconHandler: function(rc) {
			switch (rc) {
				case 0:
					Notify.info("Upload complete.");

					if (App.isPrefs())
						dijit.byId("feedTree").reload();
					else
						Feeds.reload();

					break;
				case 1:
					Notify.error("Upload failed: icon is too big.");
					break;
				case 2:
					Notify.error("Upload failed.");
					break;
			}
		},
		removeFeedIcon: function(id) {
			if (confirm(__("Remove stored feed icon?"))) {
				Notify.progress("Removing feed icon...", true);

				const query = {op: "pref-feeds", method: "removeicon", feed_id: id};

				xhrPost("backend.php", query, () => {
					Notify.info("Feed icon removed.");

					if (App.isPrefs())
						dijit.byId("feedTree").reload();
					else
						Feeds.reload();

				});
			}

			return false;
		},
		uploadFeedIcon: function() {
			const file = $("icon_file");

			if (file.value.length == 0) {
				alert(__("Please select an image file to upload."));
			} else if (confirm(__("Upload new icon for this feed?"))) {
				Notify.progress("Uploading, please wait...", true);
				return true;
			}

			return false;
		},
		quickAddFeed: function() {
			const query = "backend.php?op=feeds&method=quickAddFeed";

			// overlapping widgets
			if (dijit.byId("batchSubDlg")) dijit.byId("batchSubDlg").destroyRecursive();
			if (dijit.byId("feedAddDlg")) dijit.byId("feedAddDlg").destroyRecursive();

			const dialog = new dijit.Dialog({
				id: "feedAddDlg",
				title: __("Subscribe to Feed"),
				style: "width: 600px",
				show_error: function (msg) {
					const elem = $("fadd_error_message");

					elem.innerHTML = msg;

					if (!Element.visible(elem))
						new Effect.Appear(elem);

				},
				execute: function () {
					if (this.validate()) {
						console.log(dojo.objectToQuery(this.attr('value')));

						const feed_url = this.attr('value').feed;

						Element.show("feed_add_spinner");
						Element.hide("fadd_error_message");

						xhrPost("backend.php", this.attr('value'), (transport) => {
							try {

								try {
									var reply = JSON.parse(transport.responseText);
								} catch (e) {
									Element.hide("feed_add_spinner");
									alert(__("Failed to parse output. This can indicate server timeout and/or network issues. Backend output was logged to browser console."));
									console.log('quickAddFeed, backend returned:' + transport.responseText);
									return;
								}

								const rc = reply['result'];

								Notify.close();
								Element.hide("feed_add_spinner");

								console.log(rc);

								switch (parseInt(rc['code'])) {
									case 1:
										dialog.hide();
										Notify.info(__("Subscribed to %s").replace("%s", feed_url));

										if (App.isPrefs())
											dijit.byId("feedTree").reload();
										else
											Feeds.reload();

										break;
									case 2:
										dialog.show_error(__("Specified URL seems to be invalid."));
										break;
									case 3:
										dialog.show_error(__("Specified URL doesn't seem to contain any feeds."));
										break;
									case 4:
										const feeds = rc['feeds'];

										Element.show("fadd_multiple_notify");

										const select = dijit.byId("feedDlg_feedContainerSelect");

										while (select.getOptions().length > 0)
											select.removeOption(0);

										select.addOption({value: '', label: __("Expand to select feed")});

										let count = 0;
										for (const feedUrl in feeds) {
											if (feeds.hasOwnProperty(feedUrl)) {
												select.addOption({value: feedUrl, label: feeds[feedUrl]});
												count++;
											}
										}

										Effect.Appear('feedDlg_feedsContainer', {duration: 0.5});

										break;
									case 5:
										dialog.show_error(__("Couldn't download the specified URL: %s").replace("%s", rc['message']));
										break;
									case 6:
										dialog.show_error(__("XML validation failed: %s").replace("%s", rc['message']));
										break;
									case 0:
										dialog.show_error(__("You are already subscribed to this feed."));
										break;
								}

							} catch (e) {
								console.error(transport.responseText);
								App.Error.report(e);
							}
						});
					}
				},
				href: query
			});

			dialog.show();
		},
		showFeedsWithErrors: function() {
			const query = {op: "pref-feeds", method: "feedsWithErrors"};

			if (dijit.byId("errorFeedsDlg"))
				dijit.byId("errorFeedsDlg").destroyRecursive();

			const dialog = new dijit.Dialog({
				id: "errorFeedsDlg",
				title: __("Feeds with update errors"),
				style: "width: 600px",
				getSelectedFeeds: function () {
					return Tables.getSelected("error-feeds-list");
				},
				removeSelected: function () {
					const sel_rows = this.getSelectedFeeds();

					if (sel_rows.length > 0) {
						if (confirm(__("Remove selected feeds?"))) {
							Notify.progress("Removing selected feeds...", true);

							const query = {
								op: "pref-feeds", method: "remove",
								ids: sel_rows.toString()
							};

							xhrPost("backend.php", query, () => {
								Notify.close();
								dialog.hide();

								if (App.isPrefs())
									dijit.byId("feedTree").reload();
								else
									Feeds.reload();

							});
						}

					} else {
						alert(__("No feeds selected."));
					}
				},
				execute: function () {
					if (this.validate()) {
						//
					}
				},
				href: "backend.php?" + dojo.objectToQuery(query)
			});

			dialog.show();
		},
		feedBrowser: function() {
			const query = {op: "feeds", method: "feedBrowser"};

			if (dijit.byId("feedAddDlg"))
				dijit.byId("feedAddDlg").hide();

			if (dijit.byId("feedBrowserDlg"))
				dijit.byId("feedBrowserDlg").destroyRecursive();

			// noinspection JSUnusedGlobalSymbols
			const dialog = new dijit.Dialog({
				id: "feedBrowserDlg",
				title: __("More Feeds"),
				style: "width: 600px",
				getSelectedFeedIds: function () {
					const list = $$("#browseFeedList li[id*=FBROW]");
					const selected = [];

					list.each(function (child) {
						const id = child.id.replace("FBROW-", "");

						if (child.hasClassName('Selected')) {
							selected.push(id);
						}
					});

					return selected;
				},
				getSelectedFeeds: function () {
					const list = $$("#browseFeedList li.Selected");
					const selected = [];

					list.each(function (child) {
						const title = child.getElementsBySelector("span.fb_feedTitle")[0].innerHTML;
						const url = child.getElementsBySelector("a.fb_feedUrl")[0].href;

						selected.push([title, url]);

					});

					return selected;
				},

				subscribe: function () {
					const mode = this.attr('value').mode;
					let selected = [];

					if (mode == "1")
						selected = this.getSelectedFeeds();
					else
						selected = this.getSelectedFeedIds();

					if (selected.length > 0) {
						dijit.byId("feedBrowserDlg").hide();

						Notify.progress("Loading, please wait...", true);

						const query = {
							op: "rpc", method: "massSubscribe",
							payload: JSON.stringify(selected), mode: mode
						};

						xhrPost("backend.php", query, () => {
							Notify.close();

							if (App.isPrefs())
								dijit.byId("feedTree").reload();
							else
								Feeds.reload();
						});

					} else {
						alert(__("No feeds selected."));
					}

				},
				update: function () {
					Element.show('feed_browser_spinner');

					xhrPost("backend.php", dialog.attr("value"), (transport) => {
						Notify.close();

						Element.hide('feed_browser_spinner');

						const reply = JSON.parse(transport.responseText);
						const mode = reply['mode'];

						if ($("browseFeedList") && reply['content']) {
							$("browseFeedList").innerHTML = reply['content'];
						}

						dojo.parser.parse("browseFeedList");

						if (mode == 2) {
							Element.show(dijit.byId('feed_archive_remove').domNode);
						} else {
							Element.hide(dijit.byId('feed_archive_remove').domNode);
						}
					});
				},
				removeFromArchive: function () {
					const selected = this.getSelectedFeedIds();

					if (selected.length > 0) {
						if (confirm(__("Remove selected feeds from the archive? Feeds with stored articles will not be removed."))) {
							Element.show('feed_browser_spinner');

							const query = {op: "rpc", method: "remarchive", ids: selected.toString()};

							xhrPost("backend.php", query, () => {
								dialog.update();
							});
						}
					}
				},
				execute: function () {
					if (this.validate()) {
						this.subscribe();
					}
				},
				href: "backend.php?" + dojo.objectToQuery(query)
			});

			dialog.show();
		},
		addLabel: function(select, callback) {
			const caption = prompt(__("Please enter label caption:"), "");

			if (caption != undefined && caption.trim().length > 0) {

				const query = {op: "pref-labels", method: "add", caption: caption.trim()};

				if (select)
					Object.extend(query, {output: "select"});

				Notify.progress("Loading, please wait...", true);

				xhrPost("backend.php", query, (transport) => {
					if (callback) {
						callback(transport);
					} else if (App.isPrefs()) {
						dijit.byId("labelTree").reload();
					} else {
						Feeds.reload();
					}
				});
			}
		},
		unsubscribeFeed: function(feed_id, title) {

			const msg = __("Unsubscribe from %s?").replace("%s", title);

			if (title == undefined || confirm(msg)) {
				Notify.progress("Removing feed...");

				const query = {op: "pref-feeds", quiet: 1, method: "remove", ids: feed_id};

				xhrPost("backend.php", query, () => {
					if (dijit.byId("feedEditDlg")) dijit.byId("feedEditDlg").hide();

					if (App.isPrefs()) {
						dijit.byId("feedTree").reload();
					} else {
						if (feed_id == Feeds.getActive())
							setTimeout(() => {
									Feeds.open({feed: -5})
								},
								100);

						if (feed_id < 0) Feeds.reload();
					}
				});
			}

			return false;
		},
		editFeed: function (feed) {
			if (feed <= 0)
				return alert(__("You can't edit this kind of feed."));

			const query = {op: "pref-feeds", method: "editfeed", id: feed};

			console.log("editFeed", query);

			if (dijit.byId("filterEditDlg"))
				dijit.byId("filterEditDlg").destroyRecursive();

			if (dijit.byId("feedEditDlg"))
				dijit.byId("feedEditDlg").destroyRecursive();

			const dialog = new dijit.Dialog({
				id: "feedEditDlg",
				title: __("Edit Feed"),
				style: "width: 600px",
				execute: function () {
					if (this.validate()) {
						Notify.progress("Saving data...", true);

						xhrPost("backend.php", dialog.attr('value'), () => {
							dialog.hide();
							Notify.close();

							if (App.isPrefs())
								dijit.byId("feedTree").reload();
							else
								Feeds.reload();

						});
					}
				},
				href: "backend.php?" + dojo.objectToQuery(query)
			});

			dialog.show();
		},
		genUrlChangeKey: function(feed, is_cat) {
			if (confirm(__("Generate new syndication address for this feed?"))) {

				Notify.progress("Trying to change address...", true);

				const query = {op: "pref-feeds", method: "regenFeedKey", id: feed, is_cat: is_cat};

				xhrJson("backend.php", query, (reply) => {
					const new_link = reply.link;
					const e = $('gen_feed_url');

					if (new_link) {
						e.innerHTML = e.innerHTML.replace(/&amp;key=.*$/,
							"&amp;key=" + new_link);

						e.href = e.href.replace(/&key=.*$/,
							"&key=" + new_link);

						new Effect.Highlight(e);

						Notify.close();

					} else {
						Notify.error("Could not change feed URL.");
					}
				});
			}
			return false;
		}
	};

	return CommonDialogs;
});