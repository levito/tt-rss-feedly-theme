Plugins.Share = {
	shareArticle: function(id) {
		if (dijit.byId("shareArticleDlg"))
			dijit.byId("shareArticleDlg").destroyRecursive();

		const query = "backend.php?op=pluginhandler&plugin=share&method=shareArticle&param=" + encodeURIComponent(id);

		const dialog = new dijit.Dialog({
			id: "shareArticleDlg",
			title: __("Share article by URL"),
			style: "width: 600px",
			newurl: function () {
				if (confirm(__("Generate new share URL for this article?"))) {

					Notify.progress("Trying to change URL...", true);

					const query = {op: "pluginhandler", plugin: "share", method: "newkey", id: id};

					xhrJson("backend.php", query, (reply) => {
						if (reply) {
							const new_link = reply.link;
							const e = $('gen_article_url');

							if (new_link) {

								e.innerHTML = e.innerHTML.replace(/\&amp;key=.*$/,
									"&amp;key=" + new_link);

								e.href = e.href.replace(/\&key=.*$/,
									"&key=" + new_link);

								new Effect.Highlight(e);

								const img = $("SHARE-IMG-" + id);
								img.addClassName("shared");

								Notify.close();

							} else {
								Notify.error("Could not change URL.");
							}
						}
					});
				}

			},
			unshare: function () {
				if (confirm(__("Remove sharing for this article?"))) {

					const query = {op: "pluginhandler", plugin: "share", method: "unshare", id: id};

					xhrPost("backend.php", query, () => {
						try {
							const img = $("SHARE-IMG-" + id);

							if (img) {
								img.removeClassName("shared");
								img.up("div[id*=RROW]").removeClassName("shared");
							}

							dialog.hide();
						} catch (e) {
							console.error(e);
						}
					});
				}

			},
			href: query
		});

		dialog.show();

		const img = $("SHARE-IMG-" + id);
		img.addClassName("shared");
	}
};



