'use strict'
/* global dijit, __ */

let App;
let CommonDialogs;
let Filters;
let Users;
let Helpers;

const Plugins = {};

require(["dojo/_base/kernel",
	"dojo/_base/declare",
	"dojo/ready",
	"dojo/parser",
	"fox/AppBase",
	"dojo/_base/loader",
	"dojo/_base/html",
	"dijit/ColorPalette",
	"dijit/Dialog",
	"dijit/form/Button",
	"dijit/form/CheckBox",
	"dijit/form/DropDownButton",
	"dijit/form/FilteringSelect",
	"dijit/form/MultiSelect",
	"dijit/form/Form",
	"dijit/form/RadioButton",
	"dijit/form/ComboButton",
	"dijit/form/Select",
	"dijit/form/SimpleTextarea",
	"dijit/form/TextBox",
	"dijit/form/ValidationTextBox",
	"dijit/InlineEditBox",
	"dijit/layout/AccordionContainer",
	"dijit/layout/AccordionPane",
	"dijit/layout/BorderContainer",
	"dijit/layout/ContentPane",
	"dijit/layout/TabContainer",
	"dijit/Menu",
	"dijit/ProgressBar",
	"dijit/Toolbar",
	"dijit/Tree",
	"dijit/tree/dndSource",
	"dojo/data/ItemFileWriteStore",
	"lib/CheckBoxStoreModel",
	"lib/CheckBoxTree",
	"fox/CommonDialogs",
	"fox/CommonFilters",
	"fox/PrefUsers",
	"fox/PrefHelpers",
	"fox/PrefFeedStore",
	"fox/PrefFilterStore",
	"fox/PrefFeedTree",
	"fox/PrefFilterTree",
	"fox/PrefLabelTree"], function (dojo, declare, ready, parser, AppBase) {

	ready(function () {
		try {
			const _App = declare("fox.App", AppBase, {
				constructor: function() {
					parser.parse();

					this.setLoadingProgress(50);

					const clientTzOffset = new Date().getTimezoneOffset() * 60;
					const params = {op: "rpc", method: "sanityCheck", clientTzOffset: clientTzOffset};

					xhrPost("backend.php", params, (transport) => {
						try {
							this.backendSanityCallback(transport);
						} catch (e) {
							this.Error.report(e);
						}
					});
				},
				initSecondStage: function() {
					this.enableCsrfSupport();

					document.onkeydown = (event) => { return App.hotkeyHandler(event) };
					App.setLoadingProgress(50);
					Notify.close();

					let tab = App.urlParam('tab');

					if (tab) {
						tab = dijit.byId(tab + "Tab");
						if (tab) {
							dijit.byId("pref-tabs").selectChild(tab);

							switch (App.urlParam('method')) {
								case "editfeed":
									window.setTimeout(function () {
										CommonDialogs.editFeed(App.urlParam('methodparam'))
									}, 100);
									break;
								default:
									console.warn("initSecondStage, unknown method:", App.urlParam("method"));
							}
						}
					} else {
						let tab = localStorage.getItem("ttrss:prefs-tab");

						if (tab) {
							tab = dijit.byId(tab);
							if (tab) {
								dijit.byId("pref-tabs").selectChild(tab);
							}
						}
					}

					dojo.connect(dijit.byId("pref-tabs"), "selectChild", function (elem) {
						localStorage.setItem("ttrss:prefs-tab", elem.id);
					});

				},
				hotkeyHandler: function (event) {
					if (event.target.nodeName == "INPUT" || event.target.nodeName == "TEXTAREA") return;

					const action_name = App.keyeventToAction(event);

					if (action_name) {
						switch (action_name) {
							case "feed_subscribe":
								CommonDialogs.quickAddFeed();
								return false;
							case "create_label":
								CommonDialogs.addLabel();
								return false;
							case "create_filter":
								Filters.quickAddFilter();
								return false;
							case "help_dialog":
								App.helpDialog("main");
								return false;
							case "toggle_night_mode":
								App.toggleNightMode();
							default:
								console.log("unhandled action: " + action_name + "; keycode: " + event.which);
						}
					}
				},
				isPrefs: function() {
					return true;
				}
			});

			App = new _App();

		} catch (e) {
			this.Error.report(e);
		}
	});
});