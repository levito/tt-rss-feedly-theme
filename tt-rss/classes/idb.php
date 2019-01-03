<?php
interface IDb {
	function connect($host, $user, $pass, $db, $port);
	function escape_string($s, $strip_tags = true);
	function query($query, $die_on_error = true);
	function fetch_assoc($result);
	function num_rows($result);
	function fetch_result($result, $row, $param);
	function close();
	function affected_rows($result);
	function last_error();
	function last_query_error();
}
