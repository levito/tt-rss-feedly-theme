'use strict'
/* global __, ngettext */
define(["dojo/_base/declare"], function (declare) {
	return declare("fox.AppBase", null, {
		_initParams: [],
		_rpc_seq: 0,
		hotkey_prefix: 0,
		hotkey_prefix_pressed: false,
		hotkey_prefix_timeout: 0,
		constructor: function() {
			window.onerror = this.Error.onWindowError;
		},
		getInitParam: function(k) {
			return this._initParams[k];
		},
		setInitParam: function(k, v) {
			this._initParams[k] = v;
		},
		enableCsrfSupport: function() {
			Ajax.Base.prototype.initialize = Ajax.Base.prototype.initialize.wrap(
				function (callOriginal, options) {

					if (App.getInitParam("csrf_token") != undefined) {
						Object.extend(options, options || { });

						if (Object.isString(options.parameters))
							options.parameters = options.parameters.toQueryParams();
						else if (Object.isHash(options.parameters))
							options.parameters = options.parameters.toObject();

						options.parameters["csrf_token"] = App.getInitParam("csrf_token");
					}

					return callOriginal(options);
				}
			);
		},
		urlParam: function(param) {
			return String(window.location.href).parseQuery()[param];
		},
		next_seq: function() {
			this._rpc_seq += 1;
			return this._rpc_seq;
		},
		get_seq: function() {
			return this._rpc_seq;
		},
		setLoadingProgress: function(p) {
			loading_progress += p;

			if (dijit.byId("loading_bar"))
				dijit.byId("loading_bar").update({progress: loading_progress});

			if (loading_progress >= 90) {
				$("overlay").hide();
			}

		},
		keyeventToAction: function(event) {

			const hotkeys_map = App.getInitParam("hotkeys");
			const keycode = event.which;
			const keychar = String.fromCharCode(keycode).toLowerCase();

			if (keycode == 27) { // escape and drop prefix
				this.hotkey_prefix = false;
			}

			if (keycode == 16 || keycode == 17) return; // ignore lone shift / ctrl

			if (!this.hotkey_prefix && hotkeys_map[0].indexOf(keychar) != -1) {

				this.hotkey_prefix = keychar;
				$("cmdline").innerHTML = keychar;
				Element.show("cmdline");

				window.clearTimeout(this.hotkey_prefix_timeout);
				this.hotkey_prefix_timeout = window.setTimeout(() => {
					this.hotkey_prefix = false;
					Element.hide("cmdline");
				}, 3 * 1000);

				event.stopPropagation();

				return false;
			}

			Element.hide("cmdline");

			let hotkey_name = keychar.search(/[a-zA-Z0-9]/) != -1 ? keychar : "(" + keycode + ")";

			// ensure ^*char notation
			if (event.shiftKey) hotkey_name = "*" + hotkey_name;
			if (event.ctrlKey) hotkey_name = "^" + hotkey_name;
			if (event.altKey) hotkey_name = "+" + hotkey_name;
			if (event.metaKey) hotkey_name = "%" + hotkey_name;

			const hotkey_full = this.hotkey_prefix ? this.hotkey_prefix + " " + hotkey_name : hotkey_name;
			this.hotkey_prefix = false;

			let action_name = false;

			for (const sequence in hotkeys_map[1]) {
				if (hotkeys_map[1].hasOwnProperty(sequence)) {
					if (sequence == hotkey_full) {
						action_name = hotkeys_map[1][sequence];
						break;
					}
				}
			}

			console.log('keyeventToAction', hotkey_full, '=>', action_name);

			return action_name;
		},
		cleanupMemory: function(root) {
			const dijits = dojo.query("[widgetid]", dijit.byId(root).domNode).map(dijit.byNode);

			dijits.each(function (d) {
				dojo.destroy(d.domNode);
			});

			$$("#" + root + " *").each(function (i) {
				i.parentNode ? i.parentNode.removeChild(i) : true;
			});
		},
		helpDialog: function(topic) {
			const query = "backend.php?op=backend&method=help&topic=" + encodeURIComponent(topic);

			if (dijit.byId("helpDlg"))
				dijit.byId("helpDlg").destroyRecursive();

			const dialog = new dijit.Dialog({
				id: "helpDlg",
				title: __("Help"),
				style: "width: 600px",
				href: query,
			});

			dialog.show();
		},
		displayDlg: function(title, id, param, callback) {
			Notify.progress("Loading, please wait...", true);

			const query = {op: "dlg", method: id, param: param};

			xhrPost("backend.php", query, (transport) => {
				try {
					const content = transport.responseText;

					let dialog = dijit.byId("infoBox");

					if (!dialog) {
						dialog = new dijit.Dialog({
							title: title,
							id: 'infoBox',
							style: "width: 600px",
							onCancel: function () {
								return true;
							},
							onExecute: function () {
								return true;
							},
							onClose: function () {
								return true;
							},
							content: content
						});
					} else {
						dialog.attr('title', title);
						dialog.attr('content', content);
					}

					dialog.show();

					Notify.close();

					if (callback) callback(transport);
				} catch (e) {
					this.Error.report(e);
				}
			});

			return false;
		},
		handleRpcJson: function(transport) {

			const netalert = $$("#toolbar .net-alert")[0];

			try {
				const reply = JSON.parse(transport.responseText);

				if (reply) {
					const error = reply['error'];

					if (error) {
						const code = error['code'];
						const msg = error['message'];

						console.warn("[handleRpcJson] received fatal error ", code, msg);

						if (code != 0) {
							/* global ERRORS */
							this.Error.fatal(ERRORS[code], {info: msg, code: code});
							return false;
						}
					}

					const seq = reply['seq'];

					if (seq && this.get_seq() != seq) {
						console.log("[handleRpcJson] sequence mismatch: ", seq, '!=', this.get_seq());
						return true;
					}

					const message = reply['message'];

					if (message == "UPDATE_COUNTERS") {
						console.log("need to refresh counters...");
						Feeds.requestCounters(true);
					}

					const counters = reply['counters'];

					if (counters)
						Feeds.parseCounters(counters);

					const runtime_info = reply['runtime-info'];

					if (runtime_info)
						App.parseRuntimeInfo(runtime_info);

					if (netalert) netalert.hide();

					return reply;

				} else {
					if (netalert) netalert.show();

					Notify.error("Communication problem with server.");
				}

			} catch (e) {
				if (netalert) netalert.show();

				Notify.error("Communication problem with server.");

				console.error(e);
			}

			return false;
		},
		parseRuntimeInfo: function(data) {
			for (const k in data) {
				if (data.hasOwnProperty(k)) {
					const v = data[k];

					console.log("RI:", k, "=>", v);

					if (k == "daemon_is_running" && v != 1) {
						Notify.error("<span onclick=\"App.explainError(1)\">Update daemon is not running.</span>", true);
						return;
					}

					if (k == "recent_log_events") {
						const alert = $$(".log-alert")[0];

						if (alert) {
							v > 0 ? alert.show() : alert.hide();
						}
					}

					if (k == "daemon_stamp_ok" && v != 1) {
						Notify.error("<span onclick=\"App.explainError(3)\">Update daemon is not updating feeds.</span>", true);
						return;
					}

					if (k == "max_feed_id" || k == "num_feeds") {
						if (App.getInitParam(k) != v) {
							console.log("feed count changed, need to reload feedlist.");
							Feeds.reload();
						}
					}

					this.setInitParam(k, v);
				}
			}

			PluginHost.run(PluginHost.HOOK_RUNTIME_INFO_LOADED, data);
		},
		backendSanityCallback: function (transport) {
			const reply = JSON.parse(transport.responseText);

			/* global ERRORS */

			if (!reply) {
				this.Error.fatal(ERRORS[3], {info: transport.responseText});
				return;
			}

			if (reply['error']) {
				const code = reply['error']['code'];

				if (code && code != 0) {
					return this.Error.fatal(ERRORS[code],
						{code: code, info: reply['error']['message']});
				}
			}

			console.log("sanity check ok");

			const params = reply['init-params'];

			if (params) {
				console.log('reading init-params...');

				for (const k in params) {
					if (params.hasOwnProperty(k)) {
						switch (k) {
							case "label_base_index":
								_label_base_index = parseInt(params[k]);
								break;
							case "cdm_auto_catchup":
								if (params[k] == 1) {
									const hl = $("headlines-frame");
									if (hl) hl.addClassName("auto_catchup");
								}
								break;
							case "hotkeys":
								// filter mnemonic definitions (used for help panel) from hotkeys map
								// i.e. *(191)|Ctrl-/ -> *(191)

								const tmp = [];
								for (const sequence in params[k][1]) {
									if (params[k][1].hasOwnProperty(sequence)) {
										const filtered = sequence.replace(/\|.*$/, "");
										tmp[filtered] = params[k][1][sequence];
									}
								}

								params[k][1] = tmp;
								break;
						}

						console.log("IP:", k, "=>", params[k]);
						this.setInitParam(k, params[k]);
					}
				}

				// PluginHost might not be available on non-index pages
				if (typeof PluginHost !== 'undefined')
					PluginHost.run(PluginHost.HOOK_PARAMS_LOADED, App._initParams);
			}

			this.initSecondStage();
		},
		toggleNightMode: function() {
			const link = $("theme_css");

			if (link) {

				let user_theme = "";
				let user_css = "";

				if (link.getAttribute("href").indexOf("themes/night.css") == -1) {
					user_css = "themes/night.css?" + Date.now();
					user_theme = "night.css";
				} else {
					user_theme = "default.php";
					user_css = "css/default.css?" + Date.now();
				}

				$("main").fade({duration: 0.5, afterFinish: () => {
					link.setAttribute("href", user_css);
					$("main").appear({duration: 0.5});
					xhrPost("backend.php", {op: "rpc", method: "setpref", key: "USER_CSS_THEME", value: user_theme});
				}});

			}
		},
		explainError: function(code) {
			return this.displayDlg(__("Error explained"), "explainError", code);
		},
		Error: {
			fatal: function (error, params) {
				params = params || {};

				if (params.code) {
					if (params.code == 6) {
						window.location.href = "index.php";
						return;
					} else if (params.code == 5) {
						window.location.href = "public.php?op=dbupdate";
						return;
					}
				}

				return this.report(error,
					Object.extend({title: __("Fatal error")}, params));
			},
			report: function(error, params) {
				params = params || {};

				if (!error) return;

				console.error("[Error.report]", error, params);

				const message = params.message ? params.message : error.toString();

				try {
					xhrPost("backend.php",
						{op: "rpc", method: "log",
							file: params.filename ? params.filename : error.fileName,
							line: params.lineno ? params.lineno : error.lineNumber,
							msg: message,
							context: error.stack},
						(transport) => {
							console.warn("[Error.report] log response", transport.responseText);
						});
				} catch (re) {
					console.error("[Error.report] exception while saving logging error on server", re);
				}

				try {
					if (dijit.byId("exceptionDlg"))
						dijit.byId("exceptionDlg").destroyRecursive();

					let stack_msg = "";

					if (error.stack)
						stack_msg += `<div><b>Stack trace:</b></div>
							<textarea name="stack" readonly="1">${error.stack}</textarea>`;

					if (params.info)
						stack_msg += `<div><b>Additional information:</b></div>
							<textarea name="stack" readonly="1">${params.info}</textarea>`;

					let content = `<div class="error-contents">
							<p class="message">${message}</p>
							${stack_msg}
							<div class="dlgButtons">
								<button dojoType="dijit.form.Button"
									onclick=\"dijit.byId('exceptionDlg').hide()">${__('Close this window')}</button>
							</div>
						</div>`;

					const dialog = new dijit.Dialog({
						id: "exceptionDlg",
						title: params.title || __("Unhandled exception"),
						style: "width: 600px",
						content: content
					});

					dialog.show();
				} catch (de) {
					console.error("[Error.report] exception while showing error dialog", de);

					alert(error.stack ? error.stack : message);
				}

			},
			onWindowError: function (message, filename, lineno, colno, error) {
				// called without context (this) from window.onerror
				App.Error.report(error,
					{message: message, filename: filename, lineno: lineno, colno: colno});
			},
		}
	});
});
