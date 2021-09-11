<?php

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

function store_search_query($search_query, $cached) {
	$query = "INSERT INTO search (search_query, search_cached) VALUES(?, ?)";
	db_perform($query, [$search_query, $cached ? 1 : 0]);
}

function latest_search_query() {
	$query = "SELECT * FROM search WHERE search_id = (SELECT MAX(search_id) FROM search)";
	return db_select($query, []);
}
