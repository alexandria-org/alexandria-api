<?php

include("autoloader.php");
include("config.php");
//include("cors.php");

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

handle_cors();


header("Content-Type: application/json");

if (!isset($_GET["q"]) or $_GET["q"] == "") {
	echo json_encode(["status" => "error", "reason" => "Missing query string"]);
	die;
}

$query = $_GET["q"];

function create_node_url($node, $query) {
	return "http://".$node."/?q=" . str_replace("+", "%20", urlencode($query));
}

function create_curl_handle($url) {
	$curl = curl_init();

	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($curl, CURLOPT_ENCODING, "gzip");
	curl_setopt($curl, CURLOPT_URL, $url);

	return $curl;
}

$results = [];

$curl_multi = curl_multi_init();
$curl_handles = [];

foreach ($nodes as $node) {

	$url = create_node_url($node, $query);
	$curl = create_curl_handle($url);

	curl_multi_add_handle($curl_multi, $curl);
	$curl_handles[] = $curl;
}

do {
	$status = curl_multi_exec($curl_multi, $active);
	if ($active) {
		curl_multi_select($curl_multi);
	}
} while ($active && $status == CURLM_OK);

$results = [];
foreach($curl_handles as $curl) {
	$json = curl_multi_getcontent($curl);
	$result = json_decode($json, true, 512, JSON_INVALID_UTF8_IGNORE);
	foreach ($result["results"] as $search_result) {
		$results[] = $search_result;
	}
	curl_multi_remove_handle($curl_multi, $curl);
}
curl_multi_close($curl_multi);

for ($i = 0; $i < count($results); $i++) {

	$result = $results[$i];
	$searchWords = explode(" ", $query);
	$title = $result["title"];
	$snippet = $result["snippet"];
	$url = $result["url"];
	$domain = parse_url($results[$i]["url"], PHP_URL_HOST);
	$domain_parts = explode(".", $domain);
	$path = parse_url($results[$i]["url"], PHP_URL_PATH);
	$url_query = parse_url($results[$i]["url"], PHP_URL_QUERY);

	$result["exact_match"] = 0;
	$result["phrase_match"] = 0;
	$result["year"] = 9999;
	$result["is_old"] = 0;
	$result["is_subdomain"] = 0;
	$result["domain"] = $domain;

	if (count($domain_parts) == 3 and $domain_parts[0] == "www") {
		$result["is_subdomain"] = 0;
	} else if (count($domain_parts) < 3) {
		$result["is_subdomain"] = 0;
	} else {
		$result["is_subdomain"] = 1;
	}

	//gamla artiklar (från 2000 - 2020 får lägre score)
	if (preg_match('/\b\d{4}\b/', $snippet, $matches)) {
		$result["year"] = $matches[0];
		if ($result["year"] < 2020 and $result["year"] > 2000) {
			$result["is_old"] = 1;
		}
	}

	if (stripos($title, $query) !== false){
		$result["exact_match"] += 1;
	}
	if (stripos($domain, $query) !== false or stripos($domain, str_replace(" ","",$query)) !== false){
		$result["exact_match"] += 2;
	}
	
	foreach ($searchWords as $word) {
		if (stripos($title, $word) !== false){
			$result["phrase_match"] += 1;
		}else if (stripos($snippet, $word) !== false){
			$result["phrase_match"] += 0.9;
		}else if (stripos($domain, $word) !== false or stripos($domain, str_replace(" ","",$word)) !== false){
			$result["phrase_match"] += 0.8;
		}
	}

	$result["new_score"] = $result["score"];

	if ($result["exact_match"] > 0) {
		$result["new_score"] *= $result["exact_match"];
	} else {
		$result["new_score"] *= 0.5;
	}
	if ($result["phrase_match"] > 0) {
		$result["new_score"] *= 1 + $result["phrase_match"]/count($searchWords);
	} else {
		$result["new_score"] *= 0.5;
	}
	
	if ($result["is_old"]) {
		$result["new_score"] *= 0.9;
	}
	if ($url_query) {
		$result["new_score"] *= 0.9;
	}
	
	if ($path == "/") {
		$result["new_score"] *= 2;
	}
	
	if (strpos($url,"?")) {
		$result["new_score"] *= 0.7;
	}

	// give score bonus to short paths.
	$result["new_score"] *= 1-(strlen($path)*0.001);
	
	$results[$i] =$result;
}

// Sort the results by new_score
array_multisort(array_column($results, "new_score"), SORT_DESC, $results);

$resultCount = 0;
$printed_domains = [];
$output = [];
foreach ($results as $result) {
	// Make sure each domain is only printed once
	if (!isset($printed_domains[$result["domain"]])) {
		$printed_domains[$result["domain"]] = 1;

		// Remove new_score.
		$result["score"] = $result["new_score"];
		unset($result["new_score"]);

		$output[] = $result;

		$resultCount++;
		if ($resultCount > 50) {
			break;
		}
	}
}

if (isset($_GET['cb'])) {
	echo $_GET['cb'] . "(" . json_encode($output) . ")";
} else {
	echo json_encode($output);
}

?>
