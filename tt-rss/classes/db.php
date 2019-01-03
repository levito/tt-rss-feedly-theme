<?php
class Db
{

	/* @var Db $instance */
	private static $instance;

	/* @var IDb $adapter */
	private $adapter;

	private $link;

	/* @var PDO $pdo */
	private $pdo;

	private function __clone() {
		//
	}

	private function legacy_connect() {

		user_error("Legacy connect requested to " . DB_TYPE, E_USER_NOTICE);

		$er = error_reporting(E_ALL);

		switch (DB_TYPE) {
			case "mysql":
				$this->adapter = new Db_Mysqli();
				break;
			case "pgsql":
				$this->adapter = new Db_Pgsql();
				break;
			default:
				die("Unknown DB_TYPE: " . DB_TYPE);
		}

		if (!$this->adapter) {
			print("Error initializing database adapter for " . DB_TYPE);
			exit(100);
		}

		$this->link = $this->adapter->connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, defined('DB_PORT') ? DB_PORT : "");

		if (!$this->link) {
			print("Error connecting through adapter: " . $this->adapter->last_error());
			exit(101);
		}

		error_reporting($er);
	}

	// this really shouldn't be used unless a separate PDO connection is needed
	// normal usage is Db::pdo()->prepare(...) etc
	public function pdo_connect() {

		$db_port = defined('DB_PORT') && DB_PORT ? ';port=' . DB_PORT : '';
		$db_host = defined('DB_HOST') && DB_HOST ? ';host=' . DB_HOST : '';

		try {
			$pdo = new PDO(DB_TYPE . ':dbname=' . DB_NAME . $db_host . $db_port,
				DB_USER,
				DB_PASS);
		} catch (Exception $e) {
			print "<pre>Exception while creating PDO object:" . $e->getMessage() . "</pre>";
			exit(101);
		}

		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		if (DB_TYPE == "pgsql") {

			$pdo->query("set client_encoding = 'UTF-8'");
			$pdo->query("set datestyle = 'ISO, european'");
			$pdo->query("set TIME ZONE 0");
			$pdo->query("set cpu_tuple_cost = 0.5");

		} else if (DB_TYPE == "mysql") {
			$pdo->query("SET time_zone = '+0:0'");

			if (defined('MYSQL_CHARSET') && MYSQL_CHARSET) {
				$pdo->query("SET NAMES " . MYSQL_CHARSET);
			}
		}

		return $pdo;
	}

	public static function instance() {
		if (self::$instance == null)
			self::$instance = new self();

		return self::$instance;
	}

	public static function get() {
		if (self::$instance == null)
			self::$instance = new self();

		if (!self::$instance->adapter) {
			self::$instance->legacy_connect();
		}

		return self::$instance->adapter;
	}

	public static function pdo() {
		if (self::$instance == null)
			self::$instance = new self();

		if (!self::$instance->pdo) {
			self::$instance->pdo = self::$instance->pdo_connect();
		}

		return self::$instance->pdo;
	}
}
