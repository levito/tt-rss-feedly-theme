define(["dojo/_base/declare", "dijit/tree/ForestStoreModel"], function (declare) {

	return declare("fox.FeedStoreModel", dijit.tree.ForestStoreModel, {
		getItemsInCategory: function (id) {
			if (!this.store._itemsByIdentity) return undefined;

			let cat = this.store._itemsByIdentity['CAT:' + id];

			if (cat && cat.items)
				return cat.items;
			else
				return undefined;

		},
		getItemById: function (id) {
			return this.store._itemsByIdentity[id];
		},
		getFeedValue: function (feed, is_cat, key) {
			if (!this.store._itemsByIdentity) return undefined;

			if (is_cat)
				treeItem = this.store._itemsByIdentity['CAT:' + feed];
			else
				treeItem = this.store._itemsByIdentity['FEED:' + feed];

			if (treeItem)
				return this.store.getValue(treeItem, key);
		},
		getFeedName: function (feed, is_cat) {
			return this.getFeedValue(feed, is_cat, 'name');
		},
		getFeedUnread: function (feed, is_cat) {
			const unread = parseInt(this.getFeedValue(feed, is_cat, 'unread'));
			return (isNaN(unread)) ? 0 : unread;
		},
		setFeedUnread: function (feed, is_cat, unread) {
			return this.setFeedValue(feed, is_cat, 'unread', parseInt(unread));
		},
		setFeedValue: function (feed, is_cat, key, value) {
			if (!value) value = '';
			if (!this.store._itemsByIdentity) return undefined;

			if (is_cat)
				treeItem = this.store._itemsByIdentity['CAT:' + feed];
			else
				treeItem = this.store._itemsByIdentity['FEED:' + feed];

			if (treeItem)
				return this.store.setValue(treeItem, key, value);
		},
		getNextUnreadFeed: function (feed, is_cat) {
			if (!this.store._itemsByIdentity)
				return null;

			if (is_cat) {
				treeItem = this.store._itemsByIdentity['CAT:' + feed];
			} else {
				treeItem = this.store._itemsByIdentity['FEED:' + feed];
			}

			let items = this.store._arrayOfAllItems;

			for (let i = 0; i < items.length; i++) {
				if (items[i] == treeItem) {

					for (var j = i + 1; j < items.length; j++) {
						let unread = this.store.getValue(items[j], 'unread');
						let id = this.store.getValue(items[j], 'id');

						if (unread > 0 && ((is_cat && id.match("CAT:")) || (!is_cat && id.match("FEED:")))) {
							if (!is_cat || !(this.store.hasAttribute(items[j], 'parent_id') && this.store.getValue(items[j], 'parent_id') == feed)) return items[j];
						}
					}

					for (var j = 0; j < i; j++) {
						let unread = this.store.getValue(items[j], 'unread');
						let id = this.store.getValue(items[j], 'id');

						if (unread > 0 && ((is_cat && id.match("CAT:")) || (!is_cat && id.match("FEED:")))) {
							if (!is_cat || !(this.store.hasAttribute(items[j], 'parent_id') && this.store.getValue(items[j], 'parent_id') == feed)) return items[j];
						}
					}
				}
			}

			return null;
		},
		hasCats: function () {
			if (this.store && this.store._itemsByIdentity)
				return this.store._itemsByIdentity['CAT:-1'] != undefined;
			else
				return false;
		},

	});
});


