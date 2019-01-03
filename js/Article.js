'use strict'
/* global __, ngettext */
define(["dojo/_base/declare"], function (declare) {
	Article = {
		getScoreClass: function (score) {
			if (score > 500) {
				return "score-high";
			} else if (score > 0) {
				return "score-half-high";
			} else if (score < -100) {
				return "score-low";
			} else if (score < 0) {
				return "score-half-low";
			} else {
				return "score-neutral";
			}
		},
		getScorePic: function (score) {
			if (score > 500) {
				return "trending_up";
			} else if (score > 0) {
				return "trending_up";
			} else if (score < 0) {
				return "trending_down";
			} else {
				return "trending_neutral";
			}
		},
		selectionSetScore: function () {
			const ids = Headlines.getSelected();

			if (ids.length > 0) {
				const score = prompt(__("Please enter new score for selected articles:"));

				if (parseInt(score) != undefined) {
					ids.each((id) => {
						const row = $("RROW-" + id);

						if (row) {
							row.setAttribute("data-score", score);

							const pic = row.select(".icon-score")[0];

							pic.innerHTML = Article.getScorePic(score);
							pic.setAttribute("title", score);

							["score-low", "score-high", "score-half-low", "score-half-high", "score-neutral"]
								.each(function(scl) {
									if (row.hasClassName(scl))
										row.removeClassName(scl);
								});

							row.addClassName(Article.getScoreClass(score));
						}
					});
				}

			} else {
				alert(__("No articles selected."));
			}
		},
		setScore: function (id, pic) {
			const row = pic.up("div[id*=RROW]");

			if (row) {
				const score_old = row.getAttribute("data-score");
				const score = prompt(__("Please enter new score for this article:"), score_old);

				if (parseInt(score) != undefined) {
					row.setAttribute("data-score", score);

					const pic = row.select(".icon-score")[0];

					pic.innerHTML = Article.getScorePic(score);
					pic.setAttribute("title", score);

					["score-low", "score-high", "score-half-low", "score-half-high", "score-neutral"]
						.each(function(scl) {
							if (row.hasClassName(scl))
								row.removeClassName(scl);
						});

					row.addClassName(Article.getScoreClass(score));
				}
			}
		},
		cdmUnsetActive: function (event) {
			const row = $("RROW-" + Article.getActive());

			if (row) {
				row.removeClassName("active");

				if (event)
					event.stopPropagation();

				return false;
			}
		},
		close: function () {
			if (dijit.byId("content-insert"))
				dijit.byId("headlines-wrap-inner").removeChild(
					dijit.byId("content-insert"));

			Article.setActive(0);
		},
		displayUrl: function (id) {
			const query = {op: "rpc", method: "getlinktitlebyid", id: id};

			xhrJson("backend.php", query, (reply) => {
				if (reply && reply.link) {
					prompt(__("Article URL:"), reply.link);
				}
			});
		},
		openInNewWindow: function (id) {
			const w = window.open("");

			if (w) {
				w.opener = null;
				w.location = "backend.php?op=article&method=redirect&id=" + id;

				Headlines.toggleUnread(id, 0);
			}
		},
		render: function (article) {
			App.cleanupMemory("content-insert");

			dijit.byId("headlines-wrap-inner").addChild(
				dijit.byId("content-insert"));

			const c = dijit.byId("content-insert");

			try {
				c.domNode.scrollTop = 0;
			} catch (e) {
			}

			c.attr('content', article);
			PluginHost.run(PluginHost.HOOK_ARTICLE_RENDERED, c.domNode);

			Headlines.correctHeadlinesOffset(Article.getActive());

			try {
				c.focus();
			} catch (e) {
			}
		},
		formatComments: function(hl) {
			let comments = "";

			if (hl.comments) {
				let comments_msg = __("comments");

				if (hl.num_comments > 0) {
					comments_msg = hl.num_comments + " " + ngettext("comment", "comments", hl.num_comments)
				}

				comments = `<a href="${hl.comments}">(${comments_msg})</a>`;
			}

			return comments;
		},
		formatOriginallyFrom: function(hl) {
			return hl.orig_feed ? `<span>
					${__('Originally from:')} <a target="_blank" rel="noopener noreferrer" href="${hl.orig_feed[1]}">${hl.orig_feed[0]}</a>
					</span>` : "";
		},
		unpack: function(row) {
			if (row.hasAttribute("data-content")) {
				console.log("unpacking: " + row.id);

				const container = row.querySelector(".content-inner");

				container.innerHTML = row.getAttribute("data-content").trim();

				// blank content element might screw up onclick selection and keyboard moving
				if (container.textContent.length == 0)
					container.innerHTML += "&nbsp;";

				row.removeAttribute("data-content");

				PluginHost.run(PluginHost.HOOK_ARTICLE_RENDERED_CDM, row);
			}
		},
		view: function (id, noexpand) {
			this.setActive(id);

			if (!noexpand) {
				const hl = Headlines.objectById(id);

				if (hl) {

					const comments = this.formatComments(hl);
					const originally_from = this.formatOriginallyFrom(hl);

					const article = `<div class="post post-${hl.id}">
						<div class="header">
							<div class="row">
								<div class="title"><a target="_blank" rel="noopener noreferrer" title="${hl.title}" href="${hl.link}">${hl.title}</a></div>
								<div class="date">${hl.updated_long}</div>
							</div>
							<div class="row">
								<div class="buttons left">${hl.buttons_left}</div>
								<div class="comments">${comments}</div>
								<div class="author">${hl.author}</div>
								<i class="material-icons">label_outline</i>
								<span id="ATSTR-${hl.id}">${hl.tags_str}</span>
								&nbsp;<a title="${__("Edit tags for this article")}" href="#" 
									onclick="Article.editTags(${hl.id})">(+)</a>
								<div class="buttons right">${hl.buttons}</div>
							</div>
						</div>
						<div id="POSTNOTE-${hl.id}">${hl.note}</div>
						<div class="content" lang="${hl.lang ? hl.lang : 'en'}">
							${originally_from}
							${hl.content}
							${hl.enclosures}
						</div>
						</div>`;

					Headlines.toggleUnread(id, 0);
					this.render(article);
				}
			}

			return false;
		},
		editTags: function (id) {
			const query = "backend.php?op=article&method=editArticleTags&param=" + encodeURIComponent(id);

			if (dijit.byId("editTagsDlg"))
				dijit.byId("editTagsDlg").destroyRecursive();

			const dialog = new dijit.Dialog({
				id: "editTagsDlg",
				title: __("Edit article Tags"),
				style: "width: 600px",
				execute: function () {
					if (this.validate()) {
						Notify.progress("Saving article tags...", true);

						xhrPost("backend.php", this.attr('value'), (transport) => {
							try {
								Notify.close();
								dialog.hide();

								const data = JSON.parse(transport.responseText);

								if (data) {
									const id = data.id;

									const tags = $("ATSTR-" + id);
									const tooltip = dijit.byId("ATSTRTIP-" + id);

									if (tags) tags.innerHTML = data.content;
									if (tooltip) tooltip.attr('label', data.content_full);
								}
							} catch (e) {
								App.Error.report(e);
							}
						});
					}
				},
				href: query
			});

			const tmph = dojo.connect(dialog, 'onLoad', function () {
				dojo.disconnect(tmph);

				new Ajax.Autocompleter('tags_str', 'tags_choices',
					"backend.php?op=article&method=completeTags",
					{tokens: ',', paramName: "search"});
			});

			dialog.show();
		},
		cdmScrollToId: function (id, force) {
			const ctr = $("headlines-frame");
			const e = $("RROW-" + id);

			if (!e || !ctr) return;

			if (force || e.offsetTop + e.offsetHeight > (ctr.scrollTop + ctr.offsetHeight) ||
				e.offsetTop < ctr.scrollTop) {

				ctr.scrollTop = e.offsetTop;

				Element.hide("floatingTitle");
			}
		},
		setActive: function (id) {
			console.log("setActive", id);

			$$("div[id*=RROW][class*=active]").each((e) => {
				e.removeClassName("active");
			});

			const row = $("RROW-" + id);

			if (row) {
				Article.unpack(row);

				row.removeClassName("Unread");
				row.addClassName("active");

				PluginHost.run(PluginHost.HOOK_ARTICLE_SET_ACTIVE, row.getAttribute("data-article-id"));
			}
		},
		getActive: function () {
			const row = document.querySelector("#headlines-frame > div[id*=RROW][class*=active]");

			if (row)
				return row.getAttribute("data-article-id");
			else
				return 0;
		},
		scroll: function (offset) {
			if (!App.isCombinedMode()) {
				const ci = $("content-insert");
				if (ci) {
					ci.scrollTop += offset;
				}
			} else {
				const hi = $("headlines-frame");
				if (hi) {
					hi.scrollTop += offset;
				}

			}
		},
		mouseIn: function (id) {
			this.post_under_pointer = id;
		},
		mouseOut: function (id) {
			this.post_under_pointer = false;
		},
		getUnderPointer: function () {
			return this.post_under_pointer;
		}
	}

	return Article;
});