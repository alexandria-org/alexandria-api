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

$cluster_config_file = json_decode(file_get_contents("cluster.json"));

function enable_cache() {
	return false;
}

function get_clusters() {
	global $cluster_config_file;
	return $cluster_config_file;
}

function get_cluster($cluster) {
	$clusters = get_clusters();
	if (!isset($clusters->enabled_clusters->{$cluster})) throw new Exception("No such cluster $cluster");
	return $clusters->enabled_clusters->{$cluster};
}

function get_hosts() {
	$clusters = get_clusters();
	return $clusters->hosts;
}

function get_active_cluster() {
	$clusters = get_clusters();
	return $clusters->active_cluster;
}

function get_nodes($cluster = "a") {
	$nodes = [];
	$cluster = get_cluster($cluster);
	$hosts = get_hosts();
	foreach ($cluster->nodes as $node_name) {
		$host = $hosts->{$node_name} ?? "";
		if ($host != "") {
			$nodes[] = $host;
		}
	}
	return $nodes;
}

function cache_expire() {
	return 86400*7; // 1 week
}

