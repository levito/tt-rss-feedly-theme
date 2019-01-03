// based on http://www.velvetcache.org/2010/08/19/a-simple-javascript-hooks-system

PluginHost = {
	HOOK_ARTICLE_RENDERED: 1,
	HOOK_ARTICLE_RENDERED_CDM: 2,
	HOOK_ARTICLE_SET_ACTIVE: 3,
	HOOK_FEED_SET_ACTIVE: 4,
	HOOK_FEED_LOADED: 5,
	HOOK_ARTICLE_EXPANDED: 6,
	HOOK_ARTICLE_COLLAPSED: 7,
	HOOK_PARAMS_LOADED: 8,
	HOOK_RUNTIME_INFO_LOADED: 9,
	HOOK_FLOATING_TITLE: 10,
	HOOK_INIT_COMPLETE: 11,
	HOOK_HEADLINE_RENDERED: 12,
	hooks: [],
	register: function (name, callback) {
		if (typeof(this.hooks[name]) == 'undefined')
			this.hooks[name] = [];

		this.hooks[name].push(callback);
	},
	run: function (name, args) {
		//console.warn('PluginHost::run ' + name);

		if (typeof(this.hooks[name]) != 'undefined')
			for (let i = 0; i < this.hooks[name].length; i++) {
				this.hooks[name][i](args);
			}
	}
};

/* PluginHost.register(PluginHost.HOOK_ARTICLE_RENDERED,
		function (args) { console.log('ARTICLE_RENDERED: ' + args); return true; });

PluginHost.register(PluginHost.HOOK_ARTICLE_RENDERED_CDM,
		function (args) { console.log('ARTICLE_RENDERED_CDM: ' + args); return true; }); */

