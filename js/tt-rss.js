'use strict'
/* global dijit,__ */

let App;
let CommonDialogs;
let Filters;
let Feeds;
let Headlines;
let Article;
let PluginHost;

const Plugins = {};

require(["dojo/_base/kernel",
	"dojo/_base/declare",
	"dojo/ready",
	"dojo/parser",
	"fox/AppBase",
	"dojo/_base/loader",
	"dojo/_base/html",
	"dojo/query",
	"dijit/ProgressBar",
	"dijit/ColorPalette",
	"dijit/Dialog",
	"dijit/form/Button",
	"dijit/form/ComboButton",
	"dijit/form/CheckBox",
	"dijit/form/DropDownButton",
	"dijit/form/FilteringSelect",
	"dijit/form/Form",
	"dijit/form/RadioButton",
	"dijit/form/Select",
	"dijit/form/MultiSelect",
	"dijit/form/SimpleTextarea",
	"dijit/form/TextBox",
	"dijit/form/ComboBox",
	"dijit/form/ValidationTextBox",
	"dijit/InlineEditBox",
	"dijit/layout/AccordionContainer",
	"dijit/layout/BorderContainer",
	"dijit/layout/ContentPane",
	"dijit/layout/TabContainer",
	"dijit/PopupMenuItem",
	"dijit/Menu",
	"dijit/Toolbar",
	"dijit/Tree",
	"dijit/tree/dndSource",
	"dijit/tree/ForestStoreModel",
	"dojo/data/ItemFileWriteStore",
	"fox/PluginHost",
	"fox/CommonFilters",
	"fox/CommonDialogs",
	"fox/Feeds",
	"fox/Headlines",
	"fox/Article",
	"fox/FeedStoreModel",
	"fox/FeedTree"], function (dojo, declare, ready, parser, AppBase) {

	ready(function () {
		try {
			const _App = declare("fox.App", AppBase, {
				global_unread: -1,
				_widescreen_mode: false,
				hotkey_actions: {},
				constructor: function () {
					parser.parse();

					if (!this.checkBrowserFeatures())
						return;

					this.setLoadingProgress(30);
					this.initHotkeyActions();

					const a = document.createElement('audio');
					const hasAudio = !!a.canPlayType;
					const hasSandbox = "sandbox" in document.createElement("iframe");
					const hasMp3 = !!(a.canPlayType && a.canPlayType('audio/mpeg;').replace(/no/, ''));
					const clientTzOffset = new Date().getTimezoneOffset() * 60;

					const params = {
						op: "rpc", method: "sanityCheck", hasAudio: hasAudio,
						hasMp3: hasMp3,
						clientTzOffset: clientTzOffset,
						hasSandbox: hasSandbox
					};

					xhrPost("backend.php", params, (transport) => {
						try {
							App.backendSanityCallback(transport);
						} catch (e) {
							App.Error.report(e);
						}
					});
				},
				checkBrowserFeatures: function() {
					let errorMsg = "";

					['MutationObserver'].each(function(wf) {
						if (! (wf in window)) {
							errorMsg = `Browser feature check failed: <code>window.${wf}</code> not found.`;
							throw $break;
						}
					});

					if (errorMsg) {
						this.Error.fatal(errorMsg, {info: navigator.userAgent});
					}

					return errorMsg == "";
				},
				initSecondStage: function () {
					this.enableCsrfSupport();

					Feeds.reload();
					Article.close();

					if (parseInt(Cookie.get("ttrss_fh_width")) > 0) {
						dijit.byId("feeds-holder").domNode.setStyle(
							{width: Cookie.get("ttrss_fh_width") + "px"});
					}

					dijit.byId("main").resize();

					dojo.connect(dijit.byId('feeds-holder'), 'resize',
						function (args) {
							if (args && args.w >= 0) {
								Cookie.set("ttrss_fh_width", args.w, App.getInitParam("cookie_lifetime"));
							}
						});

					dojo.connect(dijit.byId('content-insert'), 'resize',
						function (args) {
							if (args && args.w >= 0 && args.h >= 0) {
								Cookie.set("ttrss_ci_width", args.w, App.getInitParam("cookie_lifetime"));
								Cookie.set("ttrss_ci_height", args.h, App.getInitParam("cookie_lifetime"));
							}
						});

					const toolbar = document.forms["toolbar-main"];

					dijit.getEnclosingWidget(toolbar.view_mode).attr('value',
						App.getInitParam("default_view_mode"));

					dijit.getEnclosingWidget(toolbar.order_by).attr('value',
						App.getInitParam("default_view_order_by"));

					const hash_feed_id = hash_get('f');
					const hash_feed_is_cat = hash_get('c') == "1";

					if (hash_feed_id != undefined) {
						Feeds.setActive(hash_feed_id, hash_feed_is_cat);
					}

					App.setLoadingProgress(50);

					this._widescreen_mode = App.getInitParam("widescreen");
					this.switchPanelMode(this._widescreen_mode);

					Headlines.initScrollHandler();

					if (App.getInitParam("simple_update")) {
						console.log("scheduling simple feed updater...");
						window.setInterval(() => { Feeds.updateRandom() }, 30 * 1000);
					}

					if (App.getInitParam('check_for_updates')) {
						window.setInterval(() => {
							App.checkForUpdates();
						}, 3600 * 1000);
					}

					console.log("second stage ok");

					PluginHost.run(PluginHost.HOOK_INIT_COMPLETE, null);

				},
				checkForUpdates: function() {
					console.log('checking for updates...');

					xhrJson("backend.php", {op: 'rpc', method: 'checkforupdates'})
						.then((reply) => {
							console.log('update reply', reply);

							if (reply.id) {
								$("updates-available").show();
							} else {
								$("updates-available").hide();
							}
						});
				},
				updateTitle: function() {
					let tmp = "Tiny Tiny RSS";

					if (this.global_unread > 0) {
						tmp = "(" + this.global_unread + ") " + tmp;
					}

					document.title = tmp;
				},
				onViewModeChanged: function() {
					return Feeds.reloadCurrent('');
				},
				isCombinedMode: function() {
					return App.getInitParam("combined_display_mode");
				},
				hotkeyHandler(event) {
					if (event.target.nodeName == "INPUT" || event.target.nodeName == "TEXTAREA") return;

					const action_name = App.keyeventToAction(event);

					if (action_name) {
						const action_func = this.hotkey_actions[action_name];

						if (action_func != null) {
							action_func();
							event.stopPropagation();
							return false;
						}
					}
				},
				switchPanelMode: function(wide) {
					//if (App.isCombinedMode()) return;

					const article_id = Article.getActive();

					if (wide) {
						dijit.byId("headlines-wrap-inner").attr("design", 'sidebar');
						dijit.byId("content-insert").attr("region", "trailing");

						dijit.byId("content-insert").domNode.setStyle({width: '50%',
							height: 'auto',
							borderTopWidth: '0px' });

						if (parseInt(Cookie.get("ttrss_ci_width")) > 0) {
							dijit.byId("content-insert").domNode.setStyle(
								{width: Cookie.get("ttrss_ci_width") + "px" });
						}

						$("headlines-frame").setStyle({ borderBottomWidth: '0px' });
						$("headlines-frame").addClassName("wide");

					} else {

						dijit.byId("content-insert").attr("region", "bottom");

						dijit.byId("content-insert").domNode.setStyle({width: 'auto',
							height: '50%',
							borderTopWidth: '0px'});

						if (parseInt(Cookie.get("ttrss_ci_height")) > 0) {
							dijit.byId("content-insert").domNode.setStyle(
								{height: Cookie.get("ttrss_ci_height") + "px" });
						}

						$("headlines-frame").setStyle({ borderBottomWidth: '1px' });
						$("headlines-frame").removeClassName("wide");

					}

					Article.close();

					if (article_id) Article.view(article_id);

					xhrPost("backend.php", {op: "rpc", method: "setpanelmode", wide: wide ? 1 : 0});
				},
				initHotkeyActions: function() {
					this.hotkey_actions["next_feed"] = function () {
						const rv = dijit.byId("feedTree").getNextFeed(
							Feeds.getActive(), Feeds.activeIsCat());

						if (rv) Feeds.open({feed: rv[0], is_cat: rv[1], delayed: true})
					};
					this.hotkey_actions["prev_feed"] = function () {
						const rv = dijit.byId("feedTree").getPreviousFeed(
							Feeds.getActive(), Feeds.activeIsCat());

						if (rv) Feeds.open({feed: rv[0], is_cat: rv[1], delayed: true})
					};
					this.hotkey_actions["next_article"] = function () {
						Headlines.move('next');
					};
					this.hotkey_actions["prev_article"] = function () {
						Headlines.move('prev');
					};
					this.hotkey_actions["next_article_noscroll"] = function () {
						Headlines.move('next', true);
					};
					this.hotkey_actions["prev_article_noscroll"] = function () {
						Headlines.move('prev', true);
					};
					this.hotkey_actions["next_article_noexpand"] = function () {
						Headlines.move('next', true, true);
					};
					this.hotkey_actions["prev_article_noexpand"] = function () {
						Headlines.move('prev', true, true);
					};
					this.hotkey_actions["search_dialog"] = function () {
						Feeds.search();
					};
					this.hotkey_actions["toggle_mark"] = function () {
						Headlines.selectionToggleMarked();
					};
					this.hotkey_actions["toggle_publ"] = function () {
						Headlines.selectionTogglePublished();
					};
					this.hotkey_actions["toggle_unread"] = function () {
						Headlines.selectionToggleUnread({no_error: 1});
					};
					this.hotkey_actions["edit_tags"] = function () {
						const id = Article.getActive();
						if (id) {
							Article.editTags(id);
						}
					};
					this.hotkey_actions["open_in_new_window"] = function () {
						if (Article.getActive()) {
							Article.openInNewWindow(Article.getActive());
						}
					};
					this.hotkey_actions["catchup_below"] = function () {
						Headlines.catchupRelativeTo(1);
					};
					this.hotkey_actions["catchup_above"] = function () {
						Headlines.catchupRelativeTo(0);
					};
					this.hotkey_actions["article_scroll_down"] = function () {
						Article.scroll(40);
					};
					this.hotkey_actions["article_scroll_up"] = function () {
						Article.scroll(-40);
					};
					this.hotkey_actions["close_article"] = function () {
						if (App.isCombinedMode()) {
							Article.cdmUnsetActive();
						} else {
							Article.close();
						}
					};
					this.hotkey_actions["email_article"] = function () {
						if (typeof Plugins.Mail != "undefined") {
							Plugins.Mail.onHotkey(Headlines.getSelected());
						} else {
							alert(__("Please enable mail or mailto plugin first."));
						}
					};
					this.hotkey_actions["select_all"] = function () {
						Headlines.select('all');
					};
					this.hotkey_actions["select_unread"] = function () {
						Headlines.select('unread');
					};
					this.hotkey_actions["select_marked"] = function () {
						Headlines.select('marked');
					};
					this.hotkey_actions["select_published"] = function () {
						Headlines.select('published');
					};
					this.hotkey_actions["select_invert"] = function () {
						Headlines.select('invert');
					};
					this.hotkey_actions["select_none"] = function () {
						Headlines.select('none');
					};
					this.hotkey_actions["feed_refresh"] = function () {
						if (Feeds.getActive() != undefined) {
							Feeds.open({feed: Feeds.getActive(), is_cat: Feeds.activeIsCat()});
						}
					};
					this.hotkey_actions["feed_unhide_read"] = function () {
						Feeds.toggleUnread();
					};
					this.hotkey_actions["feed_subscribe"] = function () {
						CommonDialogs.quickAddFeed();
					};
					this.hotkey_actions["feed_debug_update"] = function () {
						if (!Feeds.activeIsCat() && parseInt(Feeds.getActive()) > 0) {
							window.open("backend.php?op=feeds&method=update_debugger&feed_id=" + Feeds.getActive() +
								"&csrf_token=" + App.getInitParam("csrf_token"));
						} else {
							alert("You can't debug this kind of feed.");
						}
					};

					this.hotkey_actions["feed_debug_viewfeed"] = function () {
						Feeds.open({feed: Feeds.getActive(), is_cat: Feeds.activeIsCat(), viewfeed_debug: true});
					};

					this.hotkey_actions["feed_edit"] = function () {
						if (Feeds.activeIsCat())
							alert(__("You can't edit this kind of feed."));
						else
							CommonDialogs.editFeed(Feeds.getActive());
					};
					this.hotkey_actions["feed_catchup"] = function () {
						if (Feeds.getActive() != undefined) {
							Feeds.catchupCurrent();
						}
					};
					this.hotkey_actions["feed_reverse"] = function () {
						Headlines.reverse();
					};
					this.hotkey_actions["feed_toggle_vgroup"] = function () {
						xhrPost("backend.php", {op: "rpc", method: "togglepref", key: "VFEED_GROUP_BY_FEED"}, () => {
							Feeds.reloadCurrent();
						})
					};
					this.hotkey_actions["catchup_all"] = function () {
						Feeds.catchupAll();
					};
					this.hotkey_actions["cat_toggle_collapse"] = function () {
						if (Feeds.activeIsCat()) {
							dijit.byId("feedTree").collapseCat(Feeds.getActive());
						}
					};
					this.hotkey_actions["goto_all"] = function () {
						Feeds.open({feed: -4});
					};
					this.hotkey_actions["goto_fresh"] = function () {
						Feeds.open({feed: -3});
					};
					this.hotkey_actions["goto_marked"] = function () {
						Feeds.open({feed: -1});
					};
					this.hotkey_actions["goto_published"] = function () {
						Feeds.open({feed: -2});
					};
					this.hotkey_actions["goto_tagcloud"] = function () {
						App.displayDlg(__("Tag cloud"), "printTagCloud");
					};
					this.hotkey_actions["goto_prefs"] = function () {
						document.location.href = "prefs.php";
					};
					this.hotkey_actions["select_article_cursor"] = function () {
						const id = Article.getUnderPointer();
						if (id) {
							const row = $("RROW-" + id);

							if (row)
								row.toggleClassName("Selected");
						}
					};
					this.hotkey_actions["create_label"] = function () {
						CommonDialogs.addLabel();
					};
					this.hotkey_actions["create_filter"] = function () {
						Filters.quickAddFilter();
					};
					this.hotkey_actions["collapse_sidebar"] = function () {
						Feeds.toggle();
					};
					this.hotkey_actions["toggle_embed_original"] = function () {
						if (typeof embedOriginalArticle != "undefined") {
							if (Article.getActive())
								embedOriginalArticle(Article.getActive());
						} else {
							alert(__("Please enable embed_original plugin first."));
						}
					};
					this.hotkey_actions["toggle_widescreen"] = function () {
						if (!App.isCombinedMode()) {
							App._widescreen_mode = !App._widescreen_mode;

							// reset stored sizes because geometry changed
							Cookie.set("ttrss_ci_width", 0);
							Cookie.set("ttrss_ci_height", 0);

							App.switchPanelMode(App._widescreen_mode);
						} else {
							alert(__("Widescreen is not available in combined mode."));
						}
					};
					this.hotkey_actions["help_dialog"] = function () {
						App.helpDialog("main");
					};
					this.hotkey_actions["toggle_combined_mode"] = function () {
						const value = App.isCombinedMode() ? "false" : "true";

						xhrPost("backend.php", {op: "rpc", method: "setpref", key: "COMBINED_DISPLAY_MODE", value: value}, () => {
							App.setInitParam("combined_display_mode",
								!App.getInitParam("combined_display_mode"));

							Article.close();
							Headlines.renderAgain();
						})
					};
					this.hotkey_actions["toggle_cdm_expanded"] = function () {
						const value = App.getInitParam("cdm_expanded") ? "false" : "true";

						xhrPost("backend.php", {op: "rpc", method: "setpref", key: "CDM_EXPANDED", value: value}, () => {
							App.setInitParam("cdm_expanded", !App.getInitParam("cdm_expanded"));
							Headlines.renderAgain();
						});
					};
					this.hotkey_actions["toggle_night_mode"] = function () {
						App.toggleNightMode();
					};
				},
				onActionSelected: function(opid) {
					switch (opid) {
						case "qmcPrefs":
							document.location.href = "prefs.php";
							break;
						case "qmcLogout":
							document.location.href = "backend.php?op=logout";
							break;
						case "qmcTagCloud":
							App.displayDlg(__("Tag cloud"), "printTagCloud");
							break;
						case "qmcSearch":
							Feeds.search();
							break;
						case "qmcAddFeed":
							CommonDialogs.quickAddFeed();
							break;
						case "qmcDigest":
							window.location.href = "backend.php?op=digest";
							break;
						case "qmcEditFeed":
							if (Feeds.activeIsCat())
								alert(__("You can't edit this kind of feed."));
							else
								CommonDialogs.editFeed(Feeds.getActive());
							break;
						case "qmcRemoveFeed":
							const actid = Feeds.getActive();

							if (!actid) {
								alert(__("Please select some feed first."));
								return;
							}

							if (Feeds.activeIsCat()) {
								alert(__("You can't unsubscribe from the category."));
								return;
							}

							const fn = Feeds.getName(actid);

							if (confirm(__("Unsubscribe from %s?").replace("%s", fn))) {
								CommonDialogs.unsubscribeFeed(actid);
							}
							break;
						case "qmcCatchupAll":
							Feeds.catchupAll();
							break;
						case "qmcShowOnlyUnread":
							Feeds.toggleUnread();
							break;
						case "qmcToggleWidescreen":
							if (!App.isCombinedMode()) {
								App._widescreen_mode = !App._widescreen_mode;

								// reset stored sizes because geometry changed
								Cookie.set("ttrss_ci_width", 0);
								Cookie.set("ttrss_ci_height", 0);

								App.switchPanelMode(App._widescreen_mode);
							} else {
								alert(__("Widescreen is not available in combined mode."));
							}
							break;
						case "qmcToggleNightMode":
							App.toggleNightMode();
							break;
						case "qmcHKhelp":
							App.helpDialog("main");
							break;
						default:
							console.log("quickMenuGo: unknown action: " + opid);
					}
				},
				isPrefs: function() {
					return false;
				}
			});

			App = new _App();
		} catch (e) {
			App.Error.report(e);
		}
	});
});

function hash_get(key) {
	const kv = window.location.hash.substring(1).toQueryParams();
	return kv[key];
}

function hash_set(key, value) {
	const kv = window.location.hash.substring(1).toQueryParams();
	kv[key] = value;
	window.location.hash = $H(kv).toQueryString();
}
