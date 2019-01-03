/* global dijit */
define(["dojo/_base/declare", "dojo/dom-construct", "dijit/Tree", "dijit/Menu"], function (declare, domConstruct) {

	return declare("fox.FeedTree", dijit.Tree, {
		_onKeyPress: function(/* Event */ e) {
			return; // Stop dijit.Tree from interpreting keystrokes
		},
		_createTreeNode: function(args) {
			const tnode = new dijit._TreeNode(args);

			const iconName = args.item.icon ? String(args.item.icon[0]) : null;
			let iconNode;

			if (iconName) {
				if (iconName.indexOf("/") == -1) {
					iconNode = dojo.doc.createElement("i");
					iconNode.className = "material-icons icon icon-" + iconName;
					iconNode.innerHTML = iconName;
				} else {
					iconNode = dojo.doc.createElement('img');
					if (args.item.icon && args.item.icon[0]) {
						iconNode.src = args.item.icon[0];
					} else {
						iconNode.src = 'images/blank_icon.gif';
					}
					iconNode.className = 'icon';
				}
			}

			if (iconNode)
				domConstruct.place(iconNode, tnode.iconNode, 'only');

			const id = args.item.id[0];
			const bare_id = parseInt(id.substr(id.indexOf(':')+1));

			if (bare_id < _label_base_index) {
				const label = dojo.doc.createElement('i');
				//const fg_color = args.item.fg_color[0];
				const bg_color = args.item.bg_color[0];

				label.className = "material-icons icon icon-label";
				label.innerHTML = "label";
				label.setStyle({
					color: bg_color,
					});

				domConstruct.place(label, tnode.iconNode, 'only');
			}

			if (id.match("FEED:")) {
				let menu = new dijit.Menu();
				menu.row_id = bare_id;

				menu.addChild(new dijit.MenuItem({
					label: __("Mark as read"),
					onClick: function() {
						Feeds.catchupFeed(this.getParent().row_id);
					}}));

				if (bare_id > 0) {
					menu.addChild(new dijit.MenuItem({
						label: __("Edit feed"),
						onClick: function() {
							CommonDialogs.editFeed(this.getParent().row_id, false);
						}}));

					/* menu.addChild(new dijit.MenuItem({
					 label: __("Update feed"),
					 onClick: function() {
					 heduleFeedUpdate(this.getParent().row_id, false);
					 }})); */
				}

				menu.bindDomNode(tnode.domNode);
				tnode._menu = menu;
			}

			if (id.match("CAT:") && bare_id >= 0) {
				let menu = new dijit.Menu();
				menu.row_id = bare_id;

				menu.addChild(new dijit.MenuItem({
					label: __("Mark as read"),
					onClick: function() {
						Feeds.catchupFeed(this.getParent().row_id, true);
					}}));

				menu.addChild(new dijit.MenuItem({
					label: __("(Un)collapse"),
					onClick: function() {
						dijit.byId("feedTree").collapseCat(this.getParent().row_id);
					}}));

				menu.bindDomNode(tnode.domNode);
				tnode._menu = menu;
			}

			if (id.match("CAT:")) {
				loading = dojo.doc.createElement('img');
				loading.className = 'loadingNode';
				loading.src = 'images/blank_icon.gif';
				domConstruct.place(loading, tnode.labelNode, 'after');
				tnode.loadingNode = loading;
			}

			if (id.match("CAT:") && bare_id == -1) {
				let menu = new dijit.Menu();
				menu.row_id = bare_id;

				menu.addChild(new dijit.MenuItem({
					label: __("Mark all feeds as read"),
					onClick: function() {
						Feeds.catchupAllFeeds();
					}}));

				menu.bindDomNode(tnode.domNode);
				tnode._menu = menu;
			}

			ctr = dojo.doc.createElement('span');
			ctr.className = 'counterNode';
			ctr.innerHTML = args.item.unread > 0 ? args.item.unread : args.item.auxcounter;

			//args.item.unread > 0 ? ctr.addClassName("unread") : ctr.removeClassName("unread");

			args.item.unread > 0 || args.item.auxcounter > 0 ? Element.show(ctr) : Element.hide(ctr);

			args.item.unread == 0 && args.item.auxcounter > 0 ? ctr.addClassName("aux") : ctr.removeClassName("aux");

			domConstruct.place(ctr, tnode.rowNode, 'first');
			tnode.counterNode = ctr;

			//tnode.labelNode.innerHTML = args.label;
			return tnode;
		},
		postCreate: function() {
			this.connect(this.model, "onChange", "updateCounter");
			this.connect(this, "_expandNode", function() {
				this.hideRead(App.getInitParam("hide_read_feeds"), App.getInitParam("hide_read_shows_special"));
			});

			this.inherited(arguments);
		},
		updateCounter: function (item) {
			const tree = this;

			//console.log("updateCounter: " + item.id[0] + " " + item.unread + " " + tree);

			let node = tree._itemNodesMap[item.id];

			if (node) {
				node = node[0];

				if (node.counterNode) {
					ctr = node.counterNode;
					ctr.innerHTML = item.unread > 0 ? item.unread : item.auxcounter;
					item.unread > 0 || item.auxcounter > 0 ?
						item.unread > 0 ?
							Effect.Appear(ctr, {duration : 0.3,
								queue: { position: 'end', scope: 'CAPPEAR-' + item.id, limit: 1 }}) :
							Element.show(ctr) :
						Element.hide(ctr);

					item.unread == 0 && item.auxcounter > 0 ? ctr.addClassName("aux") : ctr.removeClassName("aux");

				}
			}

		},
		getTooltip: function (item) {
			return [item.updated, item.error].filter(x => x && x != "").join(" - ");
		},
		getIconClass: function (item, opened) {
			return (!item || this.model.mayHaveChildren(item)) ? (opened ? "dijitFolderOpened" : "dijitFolderClosed") : "feed-icon";
		},
		getLabelClass: function (item, opened) {
			return (item.unread == 0) ? "dijitTreeLabel" : "dijitTreeLabel Unread";
		},
		getRowClass: function (item, opened) {
			let rc = (!item.error || item.error == '') ? "dijitTreeRow" :
				"dijitTreeRow Error";

			if (item.unread > 0) rc += " Unread";
			if (item.updates_disabled > 0) rc += " UpdatesDisabled";

			return rc;
		},
		getLabel: function(item) {
			let name = String(item.name);

			/* Horrible */
			name = name.replace(/&quot;/g, "\"");
			name = name.replace(/&amp;/g, "&");
			name = name.replace(/&mdash;/g, "-");
			name = name.replace(/&lt;/g, "<");
			name = name.replace(/&gt;/g, ">");

			/* var label;

			 if (item.unread > 0) {
			 label = name + " (" + item.unread + ")";
			 } else {
			 label = name;
			 } */

			return name;
		},
		expandParentNodes: function(feed, is_cat, list) {
			try {
				for (let i = 0; i < list.length; i++) {
					const id = String(list[i].id);
					let item = this._itemNodesMap[id];

					if (item) {
						item = item[0];
						this._expandNode(item);
					}
				}
			} catch (e) {
				App.Error.report(e);
			}
		},
		findNodeParentsAndExpandThem: function(feed, is_cat, root, parents) {
			// expands all parents of specified feed to properly mark it as active
			// my fav thing about frameworks is doing everything myself
			try {
				const test_id = is_cat ? 'CAT:' + feed : 'FEED:' + feed;

				if (!root) {
					if (!this.model || !this.model.store) return false;

					const items = this.model.store._arrayOfTopLevelItems;

					for (let i = 0; i < items.length; i++) {
						if (String(items[i].id) == test_id) {
							this.expandParentNodes(feed, is_cat, parents);
						} else {
							this.findNodeParentsAndExpandThem(feed, is_cat, items[i], []);
						}
					}
				} else if (root.items) {
						parents.push(root);

						for (let i = 0; i < root.items.length; i++) {
							if (String(root.items[i].id) == test_id) {
								this.expandParentNodes(feed, is_cat, parents);
							} else {
								this.findNodeParentsAndExpandThem(feed, is_cat, root.items[i], parents.slice(0));
							}
						}
					} else if (String(root.id) == test_id) {
							this.expandParentNodes(feed, is_cat, parents.slice(0));
						}
			} catch (e) {
				App.Error.report(e);
			}
		},
		selectFeed: function(feed, is_cat) {
			this.findNodeParentsAndExpandThem(feed, is_cat, false, false);

			if (is_cat)
				treeNode = this._itemNodesMap['CAT:' + feed];
			else
				treeNode = this._itemNodesMap['FEED:' + feed];

			if (treeNode) {
				treeNode = treeNode[0];
				if (!is_cat) this._expandNode(treeNode);
				this.set("selectedNodes", [treeNode]);
				this.focusNode(treeNode);

				// focus headlines to route key events there
				setTimeout(function() {
					$("headlines-frame").focus();
				}, 0);
			}
		},
		setFeedIcon: function(feed, is_cat, src) {
			if (is_cat)
				treeNode = this._itemNodesMap['CAT:' + feed];
			else
				treeNode = this._itemNodesMap['FEED:' + feed];

			if (treeNode) {
				treeNode = treeNode[0];
				const icon = dojo.doc.createElement('img');
				icon.src = src;
				icon.className = 'icon';
				domConstruct.place(icon, treeNode.iconNode, 'only');
				return true;
			}
			return false;
		},
		setFeedExpandoIcon: function(feed, is_cat, src) {
			if (is_cat)
				treeNode = this._itemNodesMap['CAT:' + feed];
			else
				treeNode = this._itemNodesMap['FEED:' + feed];

			if (treeNode) {
				treeNode = treeNode[0];
				if (treeNode.loadingNode) {
					treeNode.loadingNode.src = src;
					return true;
				} else {
					const icon = dojo.doc.createElement('img');
					icon.src = src;
					icon.className = 'loadingExpando';
					domConstruct.place(icon, treeNode.expandoNode, 'only');
					return true;
				}
			}

			return false;
		},
		hasCats: function() {
			return this.model.hasCats();
		},
		hideReadCat: function (cat, hide, show_special) {
			if (this.hasCats()) {
				const tree = this;

				if (cat && cat.items) {
					let cat_unread = tree.hideReadFeeds(cat.items, hide, show_special);

					const id = String(cat.id);
					const node = tree._itemNodesMap[id];
					const bare_id = parseInt(id.substr(id.indexOf(":")+1));

					if (node) {
						const check_unread = tree.model.getFeedUnread(bare_id, true);

						if (hide && cat_unread == 0 && check_unread == 0 && (id != "CAT:-1" || !show_special)) {
							Effect.Fade(node[0].rowNode, {duration : 0.3,
								queue: { position: 'end', scope: 'FFADE-' + id, limit: 1 }});
						} else {
							Element.show(node[0].rowNode);
							++cat_unread;
						}
					}
				}
			}
		},
		hideRead: function (hide, show_special) {
			if (this.hasCats()) {

				const tree = this;
				const cats = this.model.store._arrayOfTopLevelItems;

				cats.each(function(cat) {
					tree.hideReadCat(cat, hide, show_special);
				});

			} else {
				this.hideReadFeeds(this.model.store._arrayOfTopLevelItems, hide,
					show_special);
			}
		},
		hideReadFeeds: function (items, hide, show_special) {
			const tree = this;
			let cat_unread = 0;

			items.each(function(feed) {
				const id = String(feed.id);

				// it's a subcategory
				if (feed.items) {
					tree.hideReadCat(feed, hide, show_special);
				} else {	// it's a feed
					const bare_id = parseInt(feed.bare_id);

					const unread = feed.unread[0];
					const has_error = feed.error[0] != '';
					const node = tree._itemNodesMap[id];

					if (node) {
						if (hide && unread == 0 && !has_error && (bare_id > 0 || bare_id < _label_base_index || !show_special)) {
							Effect.Fade(node[0].rowNode, {duration : 0.3,
								queue: { position: 'end', scope: 'FFADE-' + id, limit: 1 }});
						} else {
							Element.show(node[0].rowNode);
							++cat_unread;
						}
					}
				}
			});

			return cat_unread;
		},
		collapseCat: function(id) {
			if (!this.model.hasCats()) return;

			const tree = this;

			const node = tree._itemNodesMap['CAT:' + id][0];
			const item = tree.model.store._itemsByIdentity['CAT:' + id];

			if (node && item) {
				if (!node.isExpanded)
					tree._expandNode(node);
				else
					tree._collapseNode(node);

			}
		},
		getVisibleUnreadFeeds: function() {
			const items = this.model.store._arrayOfAllItems;
			const rv = [];

			for (let i = 0; i < items.length; i++) {
				const id = String(items[i].id);
				const box = this._itemNodesMap[id];

				if (box) {
					const row = box[0].rowNode;
					let cat = false;

					try {
						cat = box[0].rowNode.parentNode.parentNode;
					} catch (e) { }

					if (row) {
						if (Element.visible(row) && (!cat || Element.visible(cat))) {
							const feed_id = String(items[i].bare_id);
							const is_cat = !id.match('FEED:');
							const unread = this.model.getFeedUnread(feed_id, is_cat);

							if (unread > 0)
								rv.push([feed_id, is_cat]);

						}
					}
				}
			}

			return rv;
		},
		getNextFeed: function (feed, is_cat) {
			if (is_cat) {
				treeItem = this.model.store._itemsByIdentity['CAT:' + feed];
			} else {
				treeItem = this.model.store._itemsByIdentity['FEED:' + feed];
			}

			const items = this.model.store._arrayOfAllItems;
			let item = items[0];

			for (let i = 0; i < items.length; i++) {
				if (items[i] == treeItem) {

					for (let j = i+1; j < items.length; j++) {
						const id = String(items[j].id);
						const box = this._itemNodesMap[id];

						if (box) {
							const row = box[0].rowNode;
							const cat = box[0].rowNode.parentNode.parentNode;

							if (Element.visible(cat) && Element.visible(row)) {
								item = items[j];
								break;
							}
						}
					}
					break;
				}
			}

			if (item) {
				return [this.model.store.getValue(item, 'bare_id'),
					!this.model.store.getValue(item, 'id').match('FEED:')];
			} else {
				return false;
			}
		},
		getPreviousFeed: function (feed, is_cat) {
			if (is_cat) {
				treeItem = this.model.store._itemsByIdentity['CAT:' + feed];
			} else {
				treeItem = this.model.store._itemsByIdentity['FEED:' + feed];
			}

			const items = this.model.store._arrayOfAllItems;
			let item = items[0] == treeItem ? items[items.length-1] : items[0];

			for (let i = 0; i < items.length; i++) {
				if (items[i] == treeItem) {

					for (let j = i-1; j > 0; j--) {
						const id = String(items[j].id);
						const box = this._itemNodesMap[id];

						if (box) {
							const row = box[0].rowNode;
							const cat = box[0].rowNode.parentNode.parentNode;

							if (Element.visible(cat) && Element.visible(row)) {
								item = items[j];
								break;
							}
						}

					}
					break;
				}
			}

			if (item) {
				return [this.model.store.getValue(item, 'bare_id'),
					!this.model.store.getValue(item, 'id').match('FEED:')];
			} else {
				return false;
			}

		},
		getFeedCategory: function(feed) {
			try {
				return this.getNodesByItem(this.model.store.
					_itemsByIdentity["FEED:" + feed])[0].
				getParent().item.bare_id[0];

			} catch (e) {
				return false;
			}
		},
	});
});

