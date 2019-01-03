<?php

function db_escape_string($s, $strip_tags = true) {
	return Db::get()->escape_string($s, $strip_tags);
}

function db_query($query, $die_on_error = true) {
	return Db::get()->query($query, $die_on_error);
}

function db_fetch_assoc($result) {
	return Db::get()->fetch_assoc($result);
}


function db_num_rows($result) {
	return Db::get()->num_rows($result);
}

function db_fetch_result($result, $row, $param) {
	return Db::get()->fetch_result($result, $row, $param);
}

function db_affected_rows($result) {
	return Db::get()->affected_rows($result);
}

function db_last_error() {
	return Db::get()->last_error();
}

function db_last_query_error() {
	return Db::get()->last_query_error();
}

function db_quote($str){
	return Db::get()->quote($str);
}
