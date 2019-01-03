<?php
	require_once "functions.php";

	spl_autoload_register(function($class) {
		$namespace = '';
		$class_name = $class;

		if (strpos($class, '\\') !== FALSE)
			list ($namespace, $class_name) = explode('\\', $class, 2);

		$root_dir = dirname(__DIR__); // we're in tt-rss/include

		// 1. third party libraries with namespaces are loaded from vendor/
		// 2. internal tt-rss classes are loaded from classes/ and use special naming logic instead of namespaces
		// 3. plugin classes are loaded by PluginHandler from plugins.local/ and plugins/ (TODO: use generic autoloader?)

		if ($namespace && $class_name) {
			$class_file = "$root_dir/vendor/$namespace/" . str_replace('\\', '/', $class_name) . ".php";
		} else {
			$class_file = "$root_dir/classes/" . str_replace("_", "/", strtolower($class)) . ".php";
		}

		if (file_exists($class_file))
			include $class_file;

	});
