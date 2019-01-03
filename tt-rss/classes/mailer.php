<?php
class Mailer {
	// TODO: support HTML mail (i.e. MIME messages)

	private $last_error = "Unable to send mail: check local configuration.";

	function mail($params) {

		$to_name = $params["to_name"];
		$to_address = $params["to_address"];
		$subject = $params["subject"];
		$message = $params["message"];
		$message_html = $params["message_html"];
		$from_name = $params["from_name"] ? $params["from_name"] : SMTP_FROM_NAME;
		$from_address = $params["from_address"] ? $params["from_address"] : SMTP_FROM_ADDRESS;

		$additional_headers = $params["headers"] ? $params["headers"] : [];

		$from_combined = $from_name ? "$to_name <$to_address>" : $to_address;
		$to_combined = $to_name ? "$to_name <$to_address>" : $to_address;

		Logger::get()->log("Sending mail from $from_combined to $to_combined <$to_address> [$subject]: $message");

		// HOOK_SEND_MAIL plugin instructions:
		// 1. return 1 or true if mail is handled
		// 2. return -1 if there's been a fatal error and no further action is allowed
		// 3. any other return value will allow cycling to the next handler and, eventually, to default mail() function
		// 4. set error message if needed via passed Mailer instance function set_error()

		foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_SEND_MAIL) as $p) {
			$rc = $p->hook_send_mail($this, $params);

			if ($rc == 1)
				return $rc;

			if ($rc == -1)
				return 0;
		}

		$headers = [ "From: $from_combined", "Content-Type: text/plain; charset=UTF-8" ];

		return mail($to_combined, $subject, $message, implode("\r\n", array_merge($headers, $additional_headers)));
	}

	function set_error($message) {
		$this->last_error = $message;
	}

	function error() {
		return $this->last_error;
	}
}
