<?php
/*
 * MIT License
 *
 * Alexandria.org
 *
 * Copyright (c) 2021 Josef Cullhed, <info@alexandria.org>, et al.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

include("config.php");
include("db_functions.php");
include("post_processor.php");

function handle_query($path, $get, $ip) {

	if ($path == "/") {
		return handle_search_query($path, $get, $ip);
	} elseif ($path == "/ping") {
		return handle_ping_query($path, $get, $ip);
	} elseif ($path == "/url") {
		return handle_url_query($path, $get, $ip);
	} elseif ($path == "/schema") {
		return [db_schema(), 200];
	} else {
		return ["404", 404];
	}
}

function handle_url_query($path, $get, $ip) {

	list($url, $cluster) = parse_url_input($get);

	[$responses, $time_ms] = make_url_search(get_nodes($cluster), $url);

	$non_empty_response = "";
	foreach ($responses as $response) {
		if ($response != "") $non_empty_response = $response;
	}

	$api_response = [
		"status" => "success",
		"result" => $non_empty_response,
		"time_ms" => $time_ms
	];

	return [json_encode($api_response), 200];
}

function handle_ping_query($path, $get, $ip) {
	if (trim($get["data"] ?? '') == '') return ["Missing data", 400];

	$data = parse_ping_data($get['data']);

	store_ping($data->u, $data->q, $data->p, $ip);

	return ["", 200];
}

function make_ping_data($query, $url, $pos) {
	$data = [
		'u' => $url,
		'q' => $query,
		'p' => $pos,
	];
	return base64_encode(json_encode($data));
}

function parse_ping_data($data) {
	return json_decode(base64_decode($data));
}

function handle_search_query($path, $get, $ip) {
	try {
		list($query, $current_page, $anonymous, $cluster, $post_processor) = parse_input($get);
	} catch (Exception $error) {
		return [error_response($error->getMessage()), 400];
	}

	list($offset_start, $offset_end) = calculate_offsets($current_page, results_per_page());
	list($results, $time_ms, $total_found) = make_cached_search($cluster, $query, $ip, $anonymous);
	post_process_results($post_processor, $query, $results);
	add_ping($query, $results);
	if (should_deduplicate($query)) {
		list($output, $result_count) = deduplicate_results($results, $offset_start, $offset_end);
	} else {
		list($output, $result_count) = paginate_results($results, $offset_start, $offset_end);
	}
	limit_results($results, 1000);
	add_display_url($output);
	$page_max = calculate_page_max($result_count);
	$api_response = get_api_response($time_ms, $total_found, $page_max, $output);

	$response = make_response($api_response, $get['cb'] ?? '');

	return [$response, 200];
}

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

function results_per_page() {
	return 10;
}

function parse_input($input) {
	if (!isset($input["q"]) or $input["q"] == "") {
		throw new Exception("Missing query string");
	}

	$current_page = intval($input['p'] ?? 1);

	if ($current_page < 1) $current_page = 1;
	if ($current_page > max_pages()) $current_page = max_pages();

	$clusters = get_clusters();
	$cluster = get_active_cluster();
	if (($input['c'] ?? '') != '') {
		if (isset($clusters->enabled_clusters->{$input['c']})) {
			$cluster = $input['c'];
		}
	}

	$post_processor = $clusters->enabled_clusters->{$cluster}->post_processor;
	if (($input['r'] ?? '') != '') {
		$post_processor = $input['r'];
	}

	return [$input["q"], $current_page, (bool)($input["a"] ?? false), $cluster, $post_processor];
}

function parse_url_input($input) {
	if (!isset($input["u"]) or $input["u"] == "") {
		throw new Exception("Missing url string");
	}

	$cluster = get_active_cluster();
	if (isset($input['c'])) {
		$clusters = get_clusters();
		if (isset($clusters->enabled_clusters->{$input['c']})) {
			$cluster = $input['c'];
		}
	}

	return [$input["u"], $cluster];
}

function should_deduplicate($query) {
	return strpos($query, "site:") === false;
}

function error_response($reason) {
	echo json_encode(["status" => "error", "reason" => $reason]);
}

function calculate_offsets($current_page, $results_per_page) {
	$offset_start = ($current_page - 1) * $results_per_page;
	$offset_end = $offset_start + $results_per_page;

	return [$offset_start, $offset_end];
}

function gen_cache_key($cluster, $query) {
	return $cluster . "-" . md5($query);
}

function make_cached_search($cluster, $query, $ip, $anonymous) {
	$redis = new Redis();
	$redis->connect('127.0.0.1', 6379);

	list($results, $time_ms, $total_found) = [[], 0, 0];
	$cache_key = gen_cache_key($cluster, $query);
	if (enable_cache() && ($cache = $redis->get($cache_key))) {
		list($results, $time_ms, $total_found) = unserialize($cache);
		if ($anonymous) {
			store_anonymous_cached_search_query();
		} else {
			store_cached_search_query($query, $ip);
		}
	} else {
		$data = make_search(get_nodes($cluster), $query);
		list($results, $time_ms, $total_found) = $data;
		$redis->set($cache_key, serialize($data));
		$redis->expire($cache_key, cache_expire());
		if ($anonymous) {
			store_anonymous_uncached_search_query();
		} else {
			store_uncached_search_query($query, $ip);
		}
	}

	return [$results, $time_ms, $total_found];
}

function create_node_url($node, $query) {
	if (should_deduplicate($query)) {
		return "http://".$node."/?q=" . str_replace("+", "%20", urlencode($query));
	}
	return "http://".$node."/?q=" . str_replace("+", "%20", urlencode($query)) . "&d=a";
}

function create_node_url_url($node, $url) {
	return "http://".$node."/?u=" . str_replace("+", "%20", urlencode($url));
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

function make_url_search($nodes, $input_url) {
	$curl_multi = curl_multi_init();
	$curl_handles = [];

	$time_ms = microtime(true);

	foreach ($nodes as $node) {

		$url = create_node_url_url($node, $input_url);
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
			$results[] = $result["response"];
		}
		curl_multi_remove_handle($curl_multi, $curl);
	}
	curl_multi_close($curl_multi);

	return [$results, $time_ms];
}

function deduplicate_results($results, $offset_start, $offset_end) {

	$result_count = 0;
	$printed_domains = [];
	$output = [];
	foreach ($results as $result) {
		// Make sure each domain is only printed once
		if (!isset($printed_domains[$result["domain"]])) {
			$printed_domains[$result["domain"]] = 1;

			if ($result_count >= $offset_start && $result_count < $offset_end) {
				$output[] = $result;
			}
			$result_count++;

			if ($result_count >= max_pages() * results_per_page()) {
				break;
			}
		}
	}

	return [$output, $result_count];
}

function paginate_results($results, $offset_start, $offset_end) {

	$result_count = 0;
	$output = [];
	foreach ($results as $result) {
		// Make sure each domain is only printed once
		if ($result_count >= $offset_start && $result_count < $offset_end) {
			$output[] = $result;
		}
		$result_count++;

		if ($result_count >= max_pages() * results_per_page()) {
			break;
		}
	}

	return [$output, $result_count];
}

function add_display_url(&$results) {
	foreach ($results as &$result) {
		$result["display_url"] = make_display_url($result["url"]);
	}
}

function add_ping($query, &$results) {
	foreach ($results as $pos => &$result) {
		$result["ping"] = "https://api.alexandria.org/ping?data=" . make_ping_data($query, $result["url"], $pos);
	}
}

function calculate_page_max($result_count) {
	return ceil($result_count / results_per_page());
}

function get_api_response($time_ms, $total_found, $page_max, $results) {
	return [
		"status" => "success",
		"time_ms" => $time_ms,
		"total_found" => $total_found,
		"page_max" => $page_max,
		"results" => $results
	];
}

function make_response($api_response, $callback) {
	if ($callback != '') {
		$ret = '

navigator.serviceWorker.getRegistrations().then(function(registrations) {

    for(let registration of registrations) {

            registration.unregister()

    }}).catch(function(err) {

        console.log("Service Worker registration failed: ", err);

    });';
		return $ret.$callback . "(" . json_encode($api_response) . ")";
	} else {
		return json_encode($api_response);
	}
}

function limit_results($results, $limit) {
	return array_slice($results, 0, $limit);
}

