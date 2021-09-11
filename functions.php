<?php

function handle_cors() {
	if (isset($_SERVER['HTTP_ORIGIN'])) {
		header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
		header('Access-Control-Allow-Credentials: true');
		header('Access-Control-Max-Age: 86400');
	}

	if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
		if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
			if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
				header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
			}

			if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
				header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
			}
		}
		exit(0);
	}
}

function max_pages() {
	return 10;
}

function parse_input($input) {
	if (!isset($input["q"]) or $input["q"] == "") {
		throw new Exception("Missing query string");
	}

	$current_page = intval($input['p'] ?? 1);

	if ($current_page < 1) $current_page = 1;
	if ($current_page > max_pages()) $current_page = max_pages();

	return [$input["q"], $current_page];
}

function error_response($reason) {
	echo json_encode(["status" => "error", "reason" => $reason]);
}

