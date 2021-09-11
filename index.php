<?php

include("config.php");
include("functions.php");

handle_cors();

header("Content-Type: application/json");

try {
	list($query, $current_page) = parse_input($_GET);
} catch (Exception $error) {
	error_response($error->getMessage());
	exit();
}

list($offset_start, $offset_end) = calculate_offsets($current_page, results_per_page());
list($results, $time_ms, $total_found) = make_cached_search($query);
post_process_results($results, $query);
list($output, $result_count) = deduplicate_results($results, $offset_start, $offset_end);
add_display_url($output);
$page_max = calculate_page_max($result_count);
$api_response = get_api_response($time_ms, $total_found, $page_max, $output);

print_response($api_response, $_GET['cb'] ?? '');

