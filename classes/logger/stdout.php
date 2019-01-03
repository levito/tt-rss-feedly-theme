<?php
class Logger_Stdout {

	/**
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	function log_error($errno, $errstr, $file, $line, $context) {

		switch ($errno) {
		case E_ERROR:
		case E_PARSE:
		case E_CORE_ERROR:
		case E_COMPILE_ERROR:
		case E_USER_ERROR:
			$priority = LOG_ERR;
			break;
		case E_WARNING:
		case E_CORE_WARNING:
		case E_COMPILE_WARNING:
		case E_USER_WARNING:
			$priority = LOG_WARNING;
			break;
		default:
			$priority = LOG_INFO;
		}

		$errname = Logger::$errornames[$errno] . " ($errno)";

		print "[EEE] $priority $errname ($file:$line) $errstr\n";

	}

}
