'use strict'
/* global __, ngettext */
define(["dojo/_base/declare"], function (declare) {
	Filters = {
		filterDlgCheckAction: function(sender) {
			const action = sender.value;

			const action_param = $("filterDlg_paramBox");

			if (!action_param) {
				console.log("filterDlgCheckAction: can't find action param box!");
				return;
			}

			// if selected action supports parameters, enable params field
			if (action == 4 || action == 6 || action == 7 || action == 9) {
				new Effect.Appear(action_param, {duration: 0.5});

				Element.hide(dijit.byId("filterDlg_actionParam").domNode);
				Element.hide(dijit.byId("filterDlg_actionParamLabel").domNode);
				Element.hide(dijit.byId("filterDlg_actionParamPlugin").domNode);

				if (action == 7) {
					Element.show(dijit.byId("filterDlg_actionParamLabel").domNode);
				} else if (action == 9) {
					Element.show(dijit.byId("filterDlg_actionParamPlugin").domNode);
				} else {
					Element.show(dijit.byId("filterDlg_actionParam").domNode);
				}

			} else {
				Element.hide(action_param);
			}
		},
		createNewRuleElement: function(parentNode, replaceNode) {
			const form = document.forms["filter_new_rule_form"];
			const query = {op: "pref-filters", method: "printrulename", rule: dojo.formToJson(form)};

			xhrPost("backend.php", query, (transport) => {
				try {
					const li = dojo.create("li");

					const cb = dojo.create("input", {type: "checkbox"}, li);

					new dijit.form.CheckBox({
						onChange: function () {
							Lists.onRowChecked(this);
						},
					}, cb);

					dojo.create("input", {
						type: "hidden",
						name: "rule[]",
						value: dojo.formToJson(form)
					}, li);

					dojo.create("span", {
						onclick: function () {
							dijit.byId('filterEditDlg').editRule(this);
						},
						innerHTML: transport.responseText
					}, li);

					if (replaceNode) {
						parentNode.replaceChild(li, replaceNode);
					} else {
						parentNode.appendChild(li);
					}
				} catch (e) {
					App.Error.report(e);
				}
			});
		},
		createNewActionElement: function(parentNode, replaceNode) {
			const form = document.forms["filter_new_action_form"];

			if (form.action_id.value == 7) {
				form.action_param.value = form.action_param_label.value;
			} else if (form.action_id.value == 9) {
				form.action_param.value = form.action_param_plugin.value;
			}

			const query = {
				op: "pref-filters", method: "printactionname",
				action: dojo.formToJson(form)
			};

			xhrPost("backend.php", query, (transport) => {
				try {
					const li = dojo.create("li");

					const cb = dojo.create("input", {type: "checkbox"}, li);

					new dijit.form.CheckBox({
						onChange: function () {
							Lists.onRowChecked(this);
						},
					}, cb);

					dojo.create("input", {
						type: "hidden",
						name: "action[]",
						value: dojo.formToJson(form)
					}, li);

					dojo.create("span", {
						onclick: function () {
							dijit.byId('filterEditDlg').editAction(this);
						},
						innerHTML: transport.responseText
					}, li);

					if (replaceNode) {
						parentNode.replaceChild(li, replaceNode);
					} else {
						parentNode.appendChild(li);
					}

				} catch (e) {
					App.Error.report(e);
				}
			});
		},
		addFilterRule: function(replaceNode, ruleStr) {
			if (dijit.byId("filterNewRuleDlg"))
				dijit.byId("filterNewRuleDlg").destroyRecursive();

			const query = "backend.php?op=pref-filters&method=newrule&rule=" +
				encodeURIComponent(ruleStr);

			const rule_dlg = new dijit.Dialog({
				id: "filterNewRuleDlg",
				title: ruleStr ? __("Edit rule") : __("Add rule"),
				style: "width: 600px",
				execute: function () {
					if (this.validate()) {
						Filters.createNewRuleElement($("filterDlg_Matches"), replaceNode);
						this.hide();
					}
				},
				href: query
			});

			rule_dlg.show();
		},
		addFilterAction: function(replaceNode, actionStr) {
			if (dijit.byId("filterNewActionDlg"))
				dijit.byId("filterNewActionDlg").destroyRecursive();

			const query = "backend.php?op=pref-filters&method=newaction&action=" +
				encodeURIComponent(actionStr);

			const rule_dlg = new dijit.Dialog({
				id: "filterNewActionDlg",
				title: actionStr ? __("Edit action") : __("Add action"),
				style: "width: 600px",
				execute: function () {
					if (this.validate()) {
						Filters.createNewActionElement($("filterDlg_Actions"), replaceNode);
						this.hide();
					}
				},
				href: query
			});

			rule_dlg.show();
		},
		editFilterTest: function(query) {

			if (dijit.byId("filterTestDlg"))
				dijit.byId("filterTestDlg").destroyRecursive();

			const test_dlg = new dijit.Dialog({
				id: "filterTestDlg",
				title: "Test Filter",
				style: "width: 600px",
				results: 0,
				limit: 100,
				max_offset: 10000,
				getTestResults: function (query, offset) {
					const updquery = query + "&offset=" + offset + "&limit=" + test_dlg.limit;

					console.log("getTestResults:" + offset);

					xhrPost("backend.php", updquery, (transport) => {
						try {
							const result = JSON.parse(transport.responseText);

							if (result && dijit.byId("filterTestDlg") && dijit.byId("filterTestDlg").open) {
								test_dlg.results += result.length;

								console.log("got results:" + result.length);

								$("prefFilterProgressMsg").innerHTML = __("Looking for articles (%d processed, %f found)...")
									.replace("%f", test_dlg.results)
									.replace("%d", offset);

								console.log(offset + " " + test_dlg.max_offset);

								for (let i = 0; i < result.length; i++) {
									const tmp = new Element("table");
									tmp.innerHTML = result[i];
									dojo.parser.parse(tmp);

									$("prefFilterTestResultList").innerHTML += tmp.innerHTML;
								}

								if (test_dlg.results < 30 && offset < test_dlg.max_offset) {

									// get the next batch
									window.setTimeout(function () {
										test_dlg.getTestResults(query, offset + test_dlg.limit);
									}, 0);

								} else {
									// all done

									Element.hide("prefFilterLoadingIndicator");

									if (test_dlg.results == 0) {
										$("prefFilterTestResultList").innerHTML = `<tr><td align='center'>
											${__('No recent articles matching this filter have been found.')}</td></tr>`;
										$("prefFilterProgressMsg").innerHTML = "Articles matching this filter:";
									} else {
										$("prefFilterProgressMsg").innerHTML = __("Found %d articles matching this filter:")
											.replace("%d", test_dlg.results);
									}

								}

							} else if (!result) {
								console.log("getTestResults: can't parse results object");

								Element.hide("prefFilterLoadingIndicator");

								Notify.error("Error while trying to get filter test results.");

							} else {
								console.log("getTestResults: dialog closed, bailing out.");
							}
						} catch (e) {
							App.Error.report(e);
						}

					});
				},
				href: query
			});

			dojo.connect(test_dlg, "onLoad", null, function (e) {
				test_dlg.getTestResults(query, 0);
			});

			test_dlg.show();
		},
		quickAddFilter: function() {
			let query;

			if (!App.isPrefs()) {
				query = {
					op: "pref-filters", method: "newfilter",
					feed: Feeds.getActive(), is_cat: Feeds.activeIsCat()
				};
			} else {
				query = {op: "pref-filters", method: "newfilter"};
			}

			console.log('quickAddFilter', query);

			if (dijit.byId("feedEditDlg"))
				dijit.byId("feedEditDlg").destroyRecursive();

			if (dijit.byId("filterEditDlg"))
				dijit.byId("filterEditDlg").destroyRecursive();

			const dialog = new dijit.Dialog({
				id: "filterEditDlg",
				title: __("Create Filter"),
				style: "width: 600px",
				test: function () {
					const query = "backend.php?" + dojo.formToQuery("filter_new_form") + "&savemode=test";

					Filters.editFilterTest(query);
				},
				selectRules: function (select) {
					Lists.select("filterDlg_Matches", select);
				},
				selectActions: function (select) {
					Lists.select("filterDlg_Actions", select);
				},
				editRule: function (e) {
					const li = e.parentNode;
					const rule = li.getElementsByTagName("INPUT")[1].value;
					Filters.addFilterRule(li, rule);
				},
				editAction: function (e) {
					const li = e.parentNode;
					const action = li.getElementsByTagName("INPUT")[1].value;
					Filters.addFilterAction(li, action);
				},
				addAction: function () {
					Filters.addFilterAction();
				},
				addRule: function () {
					Filters.addFilterRule();
				},
				deleteAction: function () {
					$$("#filterDlg_Actions li[class*=Selected]").each(function (e) {
						e.parentNode.removeChild(e)
					});
				},
				deleteRule: function () {
					$$("#filterDlg_Matches li[class*=Selected]").each(function (e) {
						e.parentNode.removeChild(e)
					});
				},
				execute: function () {
					if (this.validate()) {

						const query = dojo.formToQuery("filter_new_form");

						xhrPost("backend.php", query, () => {
							if (App.isPrefs()) {
								dijit.byId("filterTree").reload();
							}

							dialog.hide();
						});
					}
				},
				href: "backend.php?" + dojo.objectToQuery(query)
			});

			if (!App.isPrefs()) {
				const selectedText = getSelectionText();

				const lh = dojo.connect(dialog, "onLoad", function () {
					dojo.disconnect(lh);

					if (selectedText != "") {

						const feed_id = Feeds.activeIsCat() ? 'CAT:' + parseInt(Feeds.getActive()) :
							Feeds.getActive();

						const rule = {reg_exp: selectedText, feed_id: [feed_id], filter_type: 1};

						Filters.addFilterRule(null, dojo.toJson(rule));

					} else {

						const query = {op: "rpc", method: "getlinktitlebyid", id: Article.getActive()};

						xhrPost("backend.php", query, (transport) => {
							const reply = JSON.parse(transport.responseText);

							let title = false;

							if (reply && reply.title) title = reply.title;

							if (title || Feeds.getActive() || Feeds.activeIsCat()) {

								console.log(title + " " + Feeds.getActive());

								const feed_id = Feeds.activeIsCat() ? 'CAT:' + parseInt(Feeds.getActive()) :
									Feeds.getActive();

								const rule = {reg_exp: title, feed_id: [feed_id], filter_type: 1};

								Filters.addFilterRule(null, dojo.toJson(rule));
							}
						});
					}
				});
			}
			dialog.show();
		},
	};

	return Filters;
});
