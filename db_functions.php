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

function connect_db() {
	$dsn = 'mysql:dbname=alexandria;host=127.0.0.1';
	$user = 'alexandria';
	$password = '';
	$dbh = new PDO($dsn, $user, $password, [
		PDO::ATTR_PERSISTENT => false,
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_TIMEOUT => 60,
		PDO::ATTR_STRINGIFY_FETCHES => false,
		PDO::ATTR_EMULATE_PREPARES => false
	]);
	$dbh->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
	$dbh->query("SET NAMES utf8mb4");

	return $dbh;
}

$_dbh = connect_db();

function db() {
	global $_dbh;
	return $_dbh;
}

function db_perform($query, $params) {
	$statement = db()->prepare($query);
	$statement->execute($params);
}

function db_select($query, $params) {
	$statement = db()->prepare($query);
	$statement->execute($params);

	return $statement->fetch();
}

function store_ping($url, $query, $ping_pos, $ip) {
	$db_query = "INSERT INTO ping (ping_url, ping_query, ping_pos, ping_ip) VALUES(?, ?, ?, ?)";
	db_perform($db_query, [$url, $query, $ping_pos, $ip]);
}

function store_uncached_search_query($search_query, $ip) {
	$query = "INSERT INTO search (search_query, search_cached, search_ip) VALUES(?, 0, ?)";
	db_perform($query, [$search_query, $ip]);
}

function store_cached_search_query($search_query, $ip) {
	$query = "INSERT INTO search (search_query, search_cached, search_ip) VALUES(?, 1, ?)";
	db_perform($query, [$search_query, $ip]);
}

function store_anonymous_uncached_search_query() {
	$query = "INSERT INTO search (search_query, search_cached, search_ip) VALUES('', 0, '')";
	db_perform($query, []);
}

function store_anonymous_cached_search_query() {
	$query = "INSERT INTO search (search_query, search_cached, search_ip) VALUES('', 1, '')";
	db_perform($query, []);
}

function latest_search_query() {
	$query = "SELECT * FROM search WHERE search_id = (SELECT MAX(search_id) FROM search)";
	return db_select($query, []);
}

function latest_ping() {
	$query = "SELECT * FROM ping WHERE ping_id = (SELECT MAX(ping_id) FROM ping)";
	return db_select($query, []);
}
