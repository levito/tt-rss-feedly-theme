require(['dojo/_base/kernel', 'dojo/ready'], function (dojo, ready) {
	ready(function () {
		PluginHost.register(PluginHost.HOOK_INIT_COMPLETE, () => {
			App.updateTitle = function () {
				document.title = "Tiny Tiny RSS";
			};
		});
	});
});
