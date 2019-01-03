require(['dojo/_base/kernel', 'dojo/ready'], function  (dojo, ready) {
	ready(function () {
		PluginHost.register(PluginHost.HOOK_ARTICLE_RENDERED_CDM, function (row) {
			if (row) {

				row.select("video").each(function (v) {
					v.muted = true;
				});
			}

			return true;
		});

		PluginHost.register(PluginHost.HOOK_ARTICLE_RENDERED, function (row) {
			if (row) {

				row.select("video").each(function (v) {
					v.muted = true;
				});
			}

			return true;
		});
	});
});
