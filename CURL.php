<?php

class CURL {

	private static $curl = null;
	private static $instance = null;

	public function __construct($user_agent = "alexandria.org") {
		if (self::$curl === null) {
			self::$curl = curl_init();
		}
		curl_setopt(self::$curl, CURLOPT_USERAGENT, $user_agent);
		curl_setopt(self::$curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt(self::$curl, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt(self::$curl, CURLOPT_ENCODING, "gzip");
	}

	public function get($url, $headers = []) {
		curl_setopt(self::$curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt(self::$curl, CURLOPT_POST, false);
		curl_setopt(self::$curl, CURLOPT_CUSTOMREQUEST, "GET");
		curl_setopt(self::$curl, CURLOPT_URL, $url);
		curl_setopt(self::$curl, CURLOPT_HTTPHEADER, $headers);
		return curl_exec(self::$curl);
	}

}

?>
