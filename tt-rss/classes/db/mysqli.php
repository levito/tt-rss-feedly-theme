<?php
class Db_Mysqli implements IDb {
	private $link;
	private $last_error;

	function connect($host, $user, $pass, $db, $port) {
		if ($port)
			$this->link = mysqli_connect($host, $user, $pass, $db, $port);
		else
			$this->link = mysqli_connect($host, $user, $pass, $db);

		if ($this->link) {
			$this->init();

			return $this->link;
		} else {
			print("Unable to connect to database (as $user to $host, database $db): " . mysqli_connect_error());
			exit(102);
		}
	}

	function escape_string($s, $strip_tags = true) {
		if ($strip_tags) $s = strip_tags($s);

		return mysqli_real_escape_string($this->link, $s);
	}

	function query($query, $die_on_error = true) {
		$result = @mysqli_query($this->link, $query);
		if (!$result) {
			$this->last_error = @mysqli_error($this->link);

			@mysqli_query($this->link, "ROLLBACK");
			user_error("Query $query failed: " . ($this->link ? $this->last_error : "No connection"),
				$die_on_error ? E_USER_ERROR : E_USER_WARNING);
		}

		return $result;
	}

	function fetch_assoc($result) {
		return mysqli_fetch_assoc($result);
	}


	function num_rows($result) {
		return mysqli_num_rows($result);
	}

	function fetch_result($result, $row, $param) {
		if (mysqli_data_seek($result, $row)) {
			$line = mysqli_fetch_assoc($result);
			return $line[$param];
		} else {
			return false;
		}
	}

	function close() {
		return mysqli_close($this->link);
	}

	function affected_rows($result) {
		return mysqli_affected_rows($this->link);
	}

	function last_error() {
		return mysqli_error($this->link);
	}

	function last_query_error() {
		return $this->last_error;
	}

	function init() {
		$this->query("SET time_zone = '+0:0'");

		if (defined('MYSQL_CHARSET') && MYSQL_CHARSET) {
			mysqli_set_charset($this->link, MYSQL_CHARSET);
		}

		return true;
	}

}
