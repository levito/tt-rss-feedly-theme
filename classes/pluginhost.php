<?php
class PluginHost {
	private $pdo;
	private $hooks = array();
	private $plugins = array();
	private $handlers = array();
	private $commands = array();
	private $storage = array();
	private $feeds = array();
	private $api_methods = array();
	private $plugin_actions = array();
	private $owner_uid;
	private $last_registered;
	private static $instance;

	const API_VERSION = 2;

	// Hooks marked with *1 are run in global context and available
	// to plugins loaded in config.php only

	const HOOK_ARTICLE_BUTTON = 1;
	const HOOK_ARTICLE_FILTER = 2;
	const HOOK_PREFS_TAB = 3;
	const HOOK_PREFS_TAB_SECTION = 4;
	const HOOK_PREFS_TABS = 5;
	const HOOK_FEED_PARSED = 6;
	const HOOK_UPDATE_TASK = 7; // *1
	const HOOK_AUTH_USER = 8;
	const HOOK_HOTKEY_MAP = 9;
	const HOOK_RENDER_ARTICLE = 10;
	const HOOK_RENDER_ARTICLE_CDM = 11;
	const HOOK_FEED_FETCHED = 12;
	const HOOK_SANITIZE = 13;
	const HOOK_RENDER_ARTICLE_API = 14;
	const HOOK_TOOLBAR_BUTTON = 15;
	const HOOK_ACTION_ITEM = 16;
	const HOOK_HEADLINE_TOOLBAR_BUTTON = 17;
	const HOOK_HOTKEY_INFO = 18;
	const HOOK_ARTICLE_LEFT_BUTTON = 19;
	const HOOK_PREFS_EDIT_FEED = 20;
	const HOOK_PREFS_SAVE_FEED = 21;
	const HOOK_FETCH_FEED = 22;
	const HOOK_QUERY_HEADLINES = 23;
	const HOOK_HOUSE_KEEPING = 24; // *1
	const HOOK_SEARCH = 25;
	const HOOK_FORMAT_ENCLOSURES = 26;
	const HOOK_SUBSCRIBE_FEED = 27;
	const HOOK_HEADLINES_BEFORE = 28;
	const HOOK_RENDER_ENCLOSURE = 29;
	const HOOK_ARTICLE_FILTER_ACTION = 30;
	const HOOK_ARTICLE_EXPORT_FEED = 31;
	const HOOK_MAIN_TOOLBAR_BUTTON = 32;
	const HOOK_ENCLOSURE_ENTRY = 33;
	const HOOK_FORMAT_ARTICLE = 34;
	const HOOK_FORMAT_ARTICLE_CDM = 35; /* RIP */
	const HOOK_FEED_BASIC_INFO = 36;
	const HOOK_SEND_LOCAL_FILE = 37;
	const HOOK_UNSUBSCRIBE_FEED = 38;
	const HOOK_SEND_MAIL = 39;
	const HOOK_FILTER_TRIGGERED = 40;

	const KIND_ALL = 1;
	const KIND_SYSTEM = 2;
	const KIND_USER = 3;

	function __construct() {
		$this->pdo = Db::pdo();

		$this->storage = array();
	}

	private function __clone() {
		//
	}

	public static function getInstance() {
		if (self::$instance == null)
			self::$instance = new self();

		return self::$instance;
	}

	private function register_plugin($name, $plugin) {
		//array_push($this->plugins, $plugin);
		$this->plugins[$name] = $plugin;
	}

	// needed for compatibility with API 1
	function get_link() {
		return false;
	}

	function get_dbh() {
		return Db::get();
	}

	function get_pdo() {
		return $this->pdo;
	}

	function get_plugin_names() {
		$names = array();

		foreach ($this->plugins as $p) {
			array_push($names, get_class($p));
		}

		return $names;
	}

	function get_plugins() {
		return $this->plugins;
	}

	function get_plugin($name) {
		return $this->plugins[strtolower($name)];
	}

	function run_hooks($type, $method, $args) {
		foreach ($this->get_hooks($type) as $hook) {
			$hook->$method($args);
		}
	}

	function add_hook($type, $sender) {
		if (!is_array($this->hooks[$type])) {
			$this->hooks[$type] = array();
		}

		array_push($this->hooks[$type], $sender);
	}

	function del_hook($type, $sender) {
		if (is_array($this->hooks[$type])) {
			$key = array_Search($sender, $this->hooks[$type]);
			if ($key !== FALSE) {
				unset($this->hooks[$type][$key]);
			}
		}
	}

	function get_hooks($type) {
		if (isset($this->hooks[$type])) {
			return $this->hooks[$type];
		} else {
			return array();
		}
	}
	function load_all($kind, $owner_uid = false, $skip_init = false) {

		$plugins = array_merge(glob("plugins/*"), glob("plugins.local/*"));
		$plugins = array_filter($plugins, "is_dir");
		$plugins = array_map("basename", $plugins);

		asort($plugins);

		$this->load(join(",", $plugins), $kind, $owner_uid, $skip_init);
	}

	function load($classlist, $kind, $owner_uid = false, $skip_init = false) {
		$plugins = explode(",", $classlist);

		$this->owner_uid = (int) $owner_uid;

		foreach ($plugins as $class) {
			$class = trim($class);
			$class_file = strtolower(basename($class));

			if (!is_dir(__DIR__."/../plugins/$class_file") &&
					!is_dir(__DIR__."/../plugins.local/$class_file")) continue;

			// try system plugin directory first
			$file = __DIR__ . "/../plugins/$class_file/init.php";
			$vendor_dir = __DIR__ . "/../plugins/$class_file/vendor";

			if (!file_exists($file)) {
				$file = __DIR__ . "/../plugins.local/$class_file/init.php";
				$vendor_dir = __DIR__ . "/../plugins.local/$class_file/vendor";
			}

			if (!isset($this->plugins[$class])) {
				if (file_exists($file)) require_once $file;

				if (class_exists($class) && is_subclass_of($class, "Plugin")) {

					// register plugin autoloader if necessary, for namespaced classes ONLY
					// layout corresponds to tt-rss main /vendor/author/Package/Class.php

					if (file_exists($vendor_dir)) {
						spl_autoload_register(function($class) use ($vendor_dir) {

							if (strpos($class, '\\') !== FALSE) {
								list ($namespace, $class_name) = explode('\\', $class, 2);

								if ($namespace && $class_name) {
									$class_file = "$vendor_dir/$namespace/" . str_replace('\\', '/', $class_name) . ".php";

									if (file_exists($class_file))
										require_once $class_file;
								}
							}
						});
					}

					$plugin = new $class($this);

					$plugin_api = $plugin->api_version();

					if ($plugin_api < PluginHost::API_VERSION) {
						user_error("Plugin $class is not compatible with current API version (need: " . PluginHost::API_VERSION . ", got: $plugin_api)", E_USER_WARNING);
						continue;
					}

					$this->last_registered = $class;

					switch ($kind) {
					case $this::KIND_SYSTEM:
						if ($this->is_system($plugin)) {
							if (!$skip_init) $plugin->init($this);
							$this->register_plugin($class, $plugin);
						}
						break;
					case $this::KIND_USER:
						if (!$this->is_system($plugin)) {
							if (!$skip_init) $plugin->init($this);
							$this->register_plugin($class, $plugin);
						}
						break;
					case $this::KIND_ALL:
						if (!$skip_init) $plugin->init($this);
						$this->register_plugin($class, $plugin);
						break;
					}
				}
			}
		}
	}

	function is_system($plugin) {
		$about = $plugin->about();

		return @$about[3];
	}

	// only system plugins are allowed to modify routing
	function add_handler($handler, $method, $sender) {
		$handler = str_replace("-", "_", strtolower($handler));
		$method = strtolower($method);

		if ($this->is_system($sender)) {
			if (!is_array($this->handlers[$handler])) {
				$this->handlers[$handler] = array();
			}

			$this->handlers[$handler][$method] = $sender;
		}
	}

	function del_handler($handler, $method, $sender) {
		$handler = str_replace("-", "_", strtolower($handler));
		$method = strtolower($method);

		if ($this->is_system($sender)) {
			unset($this->handlers[$handler][$method]);
		}
	}

	function lookup_handler($handler, $method) {
		$handler = str_replace("-", "_", strtolower($handler));
		$method = strtolower($method);

		if (is_array($this->handlers[$handler])) {
			if (isset($this->handlers[$handler]["*"])) {
				return $this->handlers[$handler]["*"];
			} else {
				return $this->handlers[$handler][$method];
			}
		}

		return false;
	}

	function add_command($command, $description, $sender, $suffix = "", $arghelp = "") {
		$command = str_replace("-", "_", strtolower($command));

		$this->commands[$command] = array("description" => $description,
			"suffix" => $suffix,
			"arghelp" => $arghelp,
			"class" => $sender);
	}

	function del_command($command) {
		$command = "-" . strtolower($command);

		unset($this->commands[$command]);
	}

	function lookup_command($command) {
		$command = "-" . strtolower($command);

		if (is_array($this->commands[$command])) {
			return $this->commands[$command]["class"];
		} else {
			return false;
		}
	}

	function get_commands() {
		return $this->commands;
	}

	function run_commands($args) {
		foreach ($this->get_commands() as $command => $data) {
			if (isset($args[$command])) {
				$command = str_replace("-", "", $command);
				$data["class"]->$command($args);
			}
		}
	}

	function load_data() {
		if ($this->owner_uid)  {
			$sth = $this->pdo->prepare("SELECT name, content FROM ttrss_plugin_storage
				WHERE owner_uid = ?");
			$sth->execute([$this->owner_uid]);

			while ($line = $sth->fetch()) {
				$this->storage[$line["name"]] = unserialize($line["content"]);
			}
		}
	}

	private function save_data($plugin) {
		if ($this->owner_uid) {
			$this->pdo->beginTransaction();

			$sth = $this->pdo->prepare("SELECT id FROM ttrss_plugin_storage WHERE
				owner_uid= ? AND name = ?");
			$sth->execute([$this->owner_uid, $plugin]);

			if (!isset($this->storage[$plugin]))
				$this->storage[$plugin] = array();

			$content = serialize($this->storage[$plugin]);

			if ($sth->fetch()) {
				$sth = $this->pdo->prepare("UPDATE ttrss_plugin_storage SET content = ?
					WHERE owner_uid= ? AND name = ?");
				$sth->execute([(string)$content, $this->owner_uid, $plugin]);

			} else {
				$sth = $this->pdo->prepare("INSERT INTO ttrss_plugin_storage
					(name,owner_uid,content) VALUES
					(?, ?, ?)");
				$sth->execute([$plugin, $this->owner_uid, (string)$content]);
			}

			$this->pdo->commit();
		}
	}

	function set($sender, $name, $value, $sync = true) {
		$idx = get_class($sender);

		if (!isset($this->storage[$idx]))
			$this->storage[$idx] = array();

		$this->storage[$idx][$name] = $value;

		if ($sync) $this->save_data(get_class($sender));
	}

	function get($sender, $name, $default_value = false) {
		$idx = get_class($sender);

		if (isset($this->storage[$idx][$name])) {
			return $this->storage[$idx][$name];
		} else {
			return $default_value;
		}
	}

	function get_all($sender) {
		$idx = get_class($sender);

		$data = $this->storage[$idx];

		return $data ? $data : [];
	}

	function clear_data($sender) {
		if ($this->owner_uid) {
			$idx = get_class($sender);

			unset($this->storage[$idx]);

			$sth = $this->pdo->prepare("DELETE FROM ttrss_plugin_storage WHERE name = ?
				AND owner_uid = ?");
			$sth->execute([$idx, $this->owner_uid]);
		}
	}

	// Plugin feed functions are *EXPERIMENTAL*!

	// cat_id: only -1 is supported (Special)
	function add_feed($cat_id, $title, $icon, $sender) {
		if (!$this->feeds[$cat_id]) $this->feeds[$cat_id] = array();

		$id = count($this->feeds[$cat_id]);

		array_push($this->feeds[$cat_id],
			array('id' => $id, 'title' => $title, 'sender' => $sender, 'icon' => $icon));

		return $id;
	}

	function get_feeds($cat_id) {
		return $this->feeds[$cat_id];
	}

	// convert feed_id (e.g. -129) to pfeed_id first
	function get_feed_handler($pfeed_id) {
		foreach ($this->feeds as $cat) {
			foreach ($cat as $feed) {
				if ($feed['id'] == $pfeed_id) {
					return $feed['sender'];
				}
			}
		}
	}

	static function pfeed_to_feed_id($label) {
		return PLUGIN_FEED_BASE_INDEX - 1 - abs($label);
	}

	static function feed_to_pfeed_id($feed) {
		return PLUGIN_FEED_BASE_INDEX - 1 + abs($feed);
	}

	function add_api_method($name, $sender) {
		if ($this->is_system($sender)) {
			$this->api_methods[strtolower($name)] = $sender;
		}
	}

	function get_api_method($name) {
		return $this->api_methods[$name];
	}

	function add_filter_action($sender, $action_name, $action_desc) {
		$sender_class = get_class($sender);

		if (!isset($this->plugin_actions[$sender_class]))
			$this->plugin_actions[$sender_class] = array();

		array_push($this->plugin_actions[$sender_class],
			array("action" => $action_name, "description" => $action_desc, "sender" => $sender));
	}

	function get_filter_actions() {
		return $this->plugin_actions;
	}
}
