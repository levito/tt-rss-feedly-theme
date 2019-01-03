<?php
class DbUpdater {

	private $pdo;
	private $db_type;
	private $need_version;

	function __construct($pdo, $db_type, $need_version) {
		$this->pdo = Db::pdo(); //$pdo;
		$this->db_type = $db_type;
		$this->need_version = (int) $need_version;
	}

	function getSchemaVersion() {
		$row = $this->pdo->query("SELECT schema_version FROM ttrss_version")->fetch();
		return (int) $row['schema_version'];
	}

	function isUpdateRequired() {
		return $this->getSchemaVersion() < $this->need_version;
	}

	function getSchemaLines($version) {
		$filename = "schema/versions/".$this->db_type."/$version.sql";

		if (file_exists($filename)) {
			return explode(";", preg_replace("/[\r\n]/", "", file_get_contents($filename)));
		} else {
			user_error("DB Updater: schema file for version $version is not found.");
			return false;
		}
	}

	function performUpdateTo($version, $html_output = true) {
		if ($this->getSchemaVersion() == $version - 1) {

			$lines = $this->getSchemaLines($version);

			if (is_array($lines)) {

				$this->pdo->beginTransaction();

				foreach ($lines as $line) {
					if (strpos($line, "--") !== 0 && $line) {
						if (!$this->pdo->query($line)) {
							if ($html_output) {
								print_notice("Query: $line");
								print_error("Error: " . implode(", ", $this->pdo->errorInfo()));
							} else {
								Debug::log("Query: $line");
								Debug::log("Error: " . implode(", ", $this->pdo->errorInfo()));
							}

							return false;
						}
					}
				}

				$db_version = $this->getSchemaVersion();

				if ($db_version == $version) {
					$this->pdo->commit();
					return true;
				} else {
					$this->pdo->rollBack();
					return false;
				}
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

}