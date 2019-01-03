<?php
class Handler_Protected extends Handler {

	function before($method) {
		return parent::before($method) && $_SESSION['uid'];
	}
}
