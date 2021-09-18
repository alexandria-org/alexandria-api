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

