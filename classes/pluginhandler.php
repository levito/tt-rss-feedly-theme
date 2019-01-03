<?php
class PluginHandler extends Handler_Protected {
	function csrf_ignore($method) {
		return true;
	}

	function catchall($method) {
		$plugin = PluginHost::getInstance()->get_plugin(clean($_REQUEST["plugin"]));

		if ($plugin) {
			if (method_exists($plugin, $method)) {
				$plugin->$method();
			} else {
				print error_json(13);
			}
		} else {
			print error_json(14);
		}
	}
}
