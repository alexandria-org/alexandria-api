<?php

include("config.php");
include("functions.php");

handle_cors();

header("Content-Type: application/json");

$results = [];
$results_per_page = 10;
$max_pages = 10;
$current_page = 1;

try {
	list($query, $current_page) = parse_input($_GET);
} catch (Exception $error) {
	error_response($error->getMessage());
	exit();
}

$offset_start = ($current_page - 1) * $results_per_page;
$offset_end = $offset_start + $results_per_page;

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

function make_display_url($url) {
	$parts = parse_url($url);
	$new_host = idn_to_utf8($parts["host"]);
	return str_replace($parts["host"], $new_host, $url);
}

function make_search($nodes, $query) {
	$curl_multi = curl_multi_init();
	$curl_handles = [];

	$time_ms = microtime(true);

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

	$time_ms = (microtime(true) - $time_ms) * 1000;

	$results = [];
	$total_found = 0;
	foreach($curl_handles as $curl) {
		$json = curl_multi_getcontent($curl);
		$result = json_decode($json, true, 512, JSON_INVALID_UTF8_IGNORE);
		if ($result !== null) {
			$total_found += $result["total_found"];
			foreach ($result["results"] as $search_result) {
				$results[] = $search_result;
			}
		}
		curl_multi_remove_handle($curl_multi, $curl);
	}
	curl_multi_close($curl_multi);

	return [$results, $time_ms, $total_found];
}

$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

list($results, $time_ms, $total_found) = [[], 0, 0];
if ($cache = $redis->get($query)) {
	list($results, $time_ms, $total_found) = unserialize($cache);
} else {
	$data = make_search($nodes, $query);
	list($results, $time_ms, $total_found) = $data;
	$redis->set($query, serialize($data));
	$redis->expire($query, 86400);
}

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
		$result["year"] = intval($matches[0]);
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

$result_count = 0;
$printed_domains = [];
$output = [];
foreach ($results as $result) {
	// Make sure each domain is only printed once
	if (!isset($printed_domains[$result["domain"]])) {
		$printed_domains[$result["domain"]] = 1;

		// Remove new_score.
		$result["score"] = $result["new_score"];
		unset($result["new_score"]);

		if ($result_count >= $offset_start && $result_count <= $offset_end) {
			$result["display_url"] = make_display_url($result["url"]);
			$output[] = $result;
		}
		$result_count++;

		if ($result_count >= $max_pages * $results_per_page) {
			break;
		}
	}
}
$page_max = ceil($result_count / $results_per_page);

$api_response = [
	"status" => "success",
	"time_ms" => $time_ms,
	"total_found" => $total_found,
	"page_max" => $page_max,
	"results" => $output
];

if (isset($_GET['cb'])) {
	echo $_GET['cb'] . "(" . json_encode($api_response) . ")";
} else {
	echo json_encode($api_response);
}

