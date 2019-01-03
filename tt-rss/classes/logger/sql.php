<?php
class Logger_SQL {

	private $pdo;

	function log_error($errno, $errstr, $file, $line, $context) {

		// separate PDO connection object is used for logging
		if (!$this->pdo) $this->pdo = Db::instance()->pdo_connect();

		if ($this->pdo && get_schema_version() > 117) {

			$owner_uid = $_SESSION["uid"] ? $_SESSION["uid"] : null;

			$sth = $this->pdo->prepare("INSERT INTO ttrss_error_log
				(errno, errstr, filename, lineno, context, owner_uid, created_at) VALUES
				(?, ?, ?, ?, ?, ?, NOW())");
			$sth->execute([$errno, $errstr, $file, $line, $context, $owner_uid]);

			return $sth->rowCount();
		}

		return false;
	}

}
