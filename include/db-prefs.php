<?php
	require_once "db.php";

	function get_pref($pref_name, $user_id = false, $die_on_error = false) {
		return Db_Prefs::get()->read($pref_name, $user_id, $die_on_error);
	}

	function set_pref($pref_name, $value, $user_id = false, $strip_tags = true) {
		return Db_Prefs::get()->write($pref_name, $value, $user_id, $strip_tags);
	}