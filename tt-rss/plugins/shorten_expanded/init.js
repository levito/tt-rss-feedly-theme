const _shorten_expanded_threshold = 1.5; //window heights

Plugins.Shorten_Expanded = {
	expand: function(id) {
		const row = $(id);

		if (row) {
			const content = row.select(".content-shrink-wrap")[0];
			const link = row.select(".expand-prompt")[0];

			if (content) content.removeClassName("content-shrink-wrap");
			if (link) Element.hide(link);
		}

		return false;
	}
}

require(['dojo/_base/kernel', 'dojo/ready'], function  (dojo, ready) {
	ready(function() {
		PluginHost.register(PluginHost.HOOK_ARTICLE_RENDERED_CDM, function(row) {
			window.setTimeout(function() {
				if (row) {

					const c_inner = row.select(".content-inner")[0];
					const c_inter = row.select(".intermediate")[0];

					if (c_inner && c_inter &&
						row.offsetHeight >= _shorten_expanded_threshold * window.innerHeight) {

						let tmp = document.createElement("div");

						c_inter.select("> *:not([class*='attachments'])").each(function(p) {
							p.parentNode.removeChild(p);
							tmp.appendChild(p);
						});

						c_inner.innerHTML = `<div class="content-shrink-wrap">
							${c_inner.innerHTML}
							${tmp.innerHTML}</div>							
							<button dojoType="dijit.form.Button" class="alt-info expand-prompt" onclick="return Plugins.Shorten_Expanded.expand('${row.id}')" href="#">
								${__("Click to expand article")}</button>`;

						dojo.parser.parse(c_inner);

						Headlines.unpackVisible();
					}
				}
			}, 150);

			return true;
		});
	});
});
