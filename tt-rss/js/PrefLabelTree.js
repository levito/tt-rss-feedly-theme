/* global lib,dijit */
define(["dojo/_base/declare", "dojo/dom-construct", "lib/CheckBoxTree", "dijit/form/DropDownButton"], function (declare, domConstruct) {

	return declare("fox.PrefLabelTree", lib.CheckBoxTree, {
		setNameById: function (id, name) {
			const item = this.model.store._itemsByIdentity['LABEL:' + id];

			if (item)
				this.model.store.setValue(item, 'name', name);

		},
		_createTreeNode: function(args) {
			const tnode = this.inherited(arguments);

			const fg_color = this.model.store.getValue(args.item, 'fg_color');
			const bg_color = this.model.store.getValue(args.item, 'bg_color');
			const type = this.model.store.getValue(args.item, 'type');
			const bare_id = this.model.store.getValue(args.item, 'bare_id');

			if (type == 'label') {
				const label = dojo.doc.createElement('i');
				//const fg_color = args.item.fg_color[0];
				const bg_color = String(args.item.bg_color);

				label.className = "material-icons icon-label";
				label.id = 'icon-label-' + String(args.item.bare_id);
				label.innerHTML = "label";
				label.setStyle({
					color: bg_color,
				});

				domConstruct.place(label, tnode.iconNode, 'before');

				//tnode._labelIconNode = span;
				//domConstruct.place(tnode._labelIconNode, tnode.labelNode, 'before');
			}

			return tnode;
		},
		getIconClass: function (item, opened) {
			return (!item || this.model.mayHaveChildren(item)) ? (opened ? "dijitFolderOpened" : "dijitFolderClosed") : "invisible";
		},
		getSelectedLabels: function() {
			const tree = this;
			const items = tree.model.getCheckedItems();
			const rv = [];

			items.each(function(item) {
				rv.push(tree.model.store.getValue(item, 'bare_id'));
			});

			return rv;
		},
		reload: function() {
			xhrPost("backend.php", { op: "pref-labels" }, (transport) => {
				dijit.byId('labelConfigTab').attr('content', transport.responseText);
				Notify.close();
			});
		},
		editLabel: function(id) {
			const query = "backend.php?op=pref-labels&method=edit&id=" +
				encodeURIComponent(id);

			if (dijit.byId("labelEditDlg"))
				dijit.byId("labelEditDlg").destroyRecursive();

			const dialog = new dijit.Dialog({
				id: "labelEditDlg",
				title: __("Label Editor"),
				style: "width: 650px",
				setLabelColor: function (id, fg, bg) {

					let kind = '';
					let color = '';

					if (fg && bg) {
						kind = 'both';
					} else if (fg) {
						kind = 'fg';
						color = fg;
					} else if (bg) {
						kind = 'bg';
						color = bg;
					}

					const e = $("icon-label-" + id);

					if (e) {
						if (bg) e.style.color = bg;
					}

					const query = {
						op: "pref-labels", method: "colorset", kind: kind,
						ids: id, fg: fg, bg: bg, color: color
					};

					xhrPost("backend.php", query, () => {
						dijit.byId("filterTree").reload(); // maybe there's labels in there
					});

				},
				execute: function () {
					if (this.validate()) {
						const caption = this.attr('value').caption;
						const fg_color = this.attr('value').fg_color;
						const bg_color = this.attr('value').bg_color;

						dijit.byId('labelTree').setNameById(id, caption);
						this.setLabelColor(id, fg_color, bg_color);
						this.hide();

						xhrPost("backend.php", this.attr('value'), () => {
							dijit.byId("filterTree").reload(); // maybe there's labels in there
						});
					}
				},
				href: query
			});

			dialog.show();
		},
		resetColors: function() {
			const labels = this.getSelectedLabels();

			if (labels.length > 0) {
				if (confirm(__("Reset selected labels to default colors?"))) {

					const query = {
						op: "pref-labels", method: "colorreset",
						ids: labels.toString()
					};

					xhrPost("backend.php", query, () => {
						this.reload();
					});
				}

			} else {
				alert(__("No labels selected."));
			}
		},
		removeSelected: function() {
			const sel_rows = this.getSelectedLabels();

			if (sel_rows.length > 0) {
				if (confirm(__("Remove selected labels?"))) {
					Notify.progress("Removing selected labels...");

					const query = {
						op: "pref-labels", method: "remove",
						ids: sel_rows.toString()
					};

					xhrPost("backend.php", query, () => {
						this.reload();
					});
				}
			} else {
				alert(__("No labels selected."));
			}

			return false;
		}
});

});


