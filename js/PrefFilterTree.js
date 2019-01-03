/* global dijit,lib */
define(["dojo/_base/declare", "dojo/dom-construct", "lib/CheckBoxTree"], function (declare, domConstruct) {

	return declare("fox.PrefFilterTree", lib.CheckBoxTree, {
		_createTreeNode: function(args) {
			const tnode = this.inherited(arguments);

			const enabled = this.model.store.getValue(args.item, 'enabled');
			let param = this.model.store.getValue(args.item, 'param');
			const rules = this.model.store.getValue(args.item, 'rules');

			if (param) {
				param = dojo.doc.createElement('span');
				param.className = (enabled != false) ? 'labelParam' : 'labelParam filterDisabled';
				param.innerHTML = args.item.param[0];
				domConstruct.place(param, tnode.rowNode, 'first');
			}

			if (rules) {
				param = dojo.doc.createElement('ul');
				param.className = 'filterRules';
				param.innerHTML = rules;
				domConstruct.place(param, tnode.rowNode, 'next');
			}

			/* if (this.model.store.getValue(args.item, 'id') != 'root') {
				const i = dojo.doc.createElement('i');
				i.className = 'material-icons filter';
				i.innerHTML = 'label';
				tnode._filterIconNode = i;
				domConstruct.place(tnode._filterIconNode, tnode.labelNode, 'before');
			} */

			return tnode;
		},

		getLabel: function(item) {
			let label = String(item.name);

			const feed = this.model.store.getValue(item, 'feed');
			const inverse = this.model.store.getValue(item, 'inverse');
			const last_triggered = this.model.store.getValue(item, 'last_triggered');

			if (feed)
				label += " (" + __("in") + " " + feed + ")";

			if (inverse)
				label += " (" + __("Inverse") + ")";

			if (last_triggered)
				label += " — " + last_triggered;

			return label;
		},
		getIconClass: function (item, opened) {
			return (!item || this.model.mayHaveChildren(item)) ? (opened ? "dijitFolderOpened" : "dijitFolderClosed") : "invisible";
		},
		getRowClass: function (item, opened) {
			const enabled = this.model.store.getValue(item, 'enabled');

			return enabled ? "dijitTreeRow" : "dijitTreeRow filterDisabled";
		},
		checkItemAcceptance: function(target, source, position) {
			const item = dijit.getEnclosingWidget(target).item;

			// disable copying items
			source.copyState = function() { return false; };

			return position != 'over';
		},
		onDndDrop: function() {
			this.inherited(arguments);
			this.tree.model.store.save();
		},
		getSelectedFilters: function() {
			const tree = this;
			const items = tree.model.getCheckedItems();
			const rv = [];

			items.each(function (item) {
				rv.push(tree.model.store.getValue(item, 'bare_id'));
			});

			return rv;
		},
		reload: function() {
			const user_search = $("filter_search");
			let search = "";
			if (user_search) { search = user_search.value; }

			xhrPost("backend.php", { op: "pref-filters", search: search }, (transport) => {
				dijit.byId('filterConfigTab').attr('content', transport.responseText);
				Notify.close();
			});
		},
		resetFilterOrder: function() {
			Notify.progress("Loading, please wait...");

			xhrPost("backend.php", {op: "pref-filters", method: "filtersortreset"}, () => {
				this.reload();
			});
		},
		joinSelectedFilters: function() {
			const rows = this.getSelectedFilters();

			if (rows.length == 0) {
				alert(__("No filters selected."));
				return;
			}

			if (confirm(__("Combine selected filters?"))) {
				Notify.progress("Joining filters...");

				xhrPost("backend.php", {op: "pref-filters", method: "join", ids: rows.toString()}, () => {
					this.reload();
				});
			}
		},
		editSelectedFilter: function() {
			const rows = this.getSelectedFilters();

			if (rows.length == 0) {
				alert(__("No filters selected."));
				return;
			}

			if (rows.length > 1) {
				alert(__("Please select only one filter."));
				return;
			}

			Notify.close();

			this.editFilter(rows[0]);
		},
		editFilter: function(id) {

			const query = "backend.php?op=pref-filters&method=edit&id=" + encodeURIComponent(id);

			if (dijit.byId("feedEditDlg"))
				dijit.byId("feedEditDlg").destroyRecursive();

			if (dijit.byId("filterEditDlg"))
				dijit.byId("filterEditDlg").destroyRecursive();

			const dialog = new dijit.Dialog({
				id: "filterEditDlg",
				title: __("Edit Filter"),
				style: "width: 600px",

				test: function () {
					const query = "backend.php?" + dojo.formToQuery("filter_edit_form") + "&savemode=test";

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
				removeFilter: function () {
					const msg = __("Remove filter?");

					if (confirm(msg)) {
						this.hide();

						Notify.progress("Removing filter...");

						const query = {op: "pref-filters", method: "remove", ids: this.attr('value').id};

						xhrPost("backend.php", query, () => {
							dijit.byId("filterTree").reload();
						});
					}
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

						Notify.progress("Saving data...", true);

						xhrPost("backend.php", dojo.formToObject("filter_edit_form"), () => {
							dialog.hide();
							dijit.byId("filterTree").reload();
						});
					}
				},
				href: query
			});

			dialog.show();
		},
		removeSelectedFilters: function() {
			const sel_rows = this.getSelectedFilters();

			if (sel_rows.length > 0) {
				if (confirm(__("Remove selected filters?"))) {
					Notify.progress("Removing selected filters...");

					const query = {
						op: "pref-filters", method: "remove",
						ids: sel_rows.toString()
					};

					xhrPost("backend.php", query, () => {
						this.reload();
					});
				}
			} else {
				alert(__("No filters selected."));
			}

			return false;
		},



});
});


