<?php

function post_process_results($post_processor, $query, &$results) {
	if ($post_processor == "") {
		$post_processor = "a";
	}

	if ($post_processor == "a") {
		post_processor_a($query, $results);
	} elseif ($post_processor == "b") {
		post_processor_b($query, $results);
	} else {
		post_processor_default($query, $results);
	}

}

function post_processor_default($query, &$results) {

	for ($i = 0; $i < count($results); $i++) {

		$result = $results[$i];

		$domain = parse_url($results[$i]["url"], PHP_URL_HOST);
		$domain_parts = explode(".", $domain);

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

		$results[$i] = $result;
	}

	// Limit results to 1000
	$results = array_slice($results, 0, 1000);
}

function post_processor_a($query, &$results) {

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

		if ($result["exact_match"] > 0) {
			$result["score"] *= $result["exact_match"];
		} else {
			$result["score"] *= 0.5;
		}
		if ($result["phrase_match"] > 0) {
			$result["score"] *= 1 + $result["phrase_match"]/count($searchWords);
		} else {
			$result["score"] *= 0.5;
		}
		
		if ($result["is_old"]) {
			$result["score"] *= 0.9;
		}
		if ($url_query) {
			$result["score"] *= 0.9;
		}
		
		if ($path == "/") {
			$result["score"] *= 2;
		}
		
		if (strpos($url,"?")) {
			$result["score"] *= 0.7;
		}

		// give score bonus to short paths.
		$result["score"] *= 1-(strlen($path)*0.001);
		
		$results[$i] = $result;
	}

	// Sort the results by score
	array_multisort(array_column($results, "score"), SORT_DESC, $results);

	// Limit results to 1000
	$results = array_slice($results, 0, 1000);
}

function post_processor_b($query, &$results) {

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

		$result["exact_match_domain"] = 0;
		$result["exact_match_title"] = 0;
		$result["exact_match_snippet"] = 0;


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

		if (stripos($title, $query) !== false){
			$result["exact_match_title"] = 1;
		}
		if (stripos($snippet, $query) !== false){
			$result["exact_match_snippet"] = 1;
		}
		if (stripos($domain, str_replace(" ", "", $query)) !== false or stripos($domain, str_replace(" ", "-", $query)) !== false){
			$result["exact_match_domain"] = 1;
		}
		
		/*if (stripos($domain, $query) !== false or stripos($domain, str_replace(" ","",$query)) !== false){
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
		}*/

		$original_score = $result["score"];
		//var_dump($result);
		/*
		if ($result["exact_match"] > 0) {
			$result["score"] *= $result["exact_match"];
		} else {
			$result["score"] *= 0.5;
		}
		
		
		if ($result["phrase_match"] > 0) {
			$result["score"] *= 1 + $result["phrase_match"]/count($searchWords);
		} else {
			$result["score"] *= 0.5;
		}
		
		if ($result["is_old"]) {
			$result["score"] *= 0.9;
		}
		

		if ($url_query) {
			$result["score"] *= 0.9;
		}
		*/
		
		if ($path == "/") {
			$result["score"] *= 1.2;
		}

		if ($result["is_old"]) {
			$result["score"] *= 0.9;
		}

		if ($result["exact_match_title"]) {
			$result["score"] *= 1.3;
		}

		if ($result["exact_match_snippet"]) {
			$result["score"] *= 1.3;
		}
		if ($result["exact_match_domain"]) {
			$result["score"] *= 1.3;
		}
		
		if (strpos($url,"?")) {
			$result["score"] *= 0.5;
		}
	
		// give score bonus to short paths.
		$result["score"] *= 1-(strlen($path)*0.01);
			
		//var_dump($result);
		//die;
		//$result["title"] = round($original_score, 2)." - ". round($result["score"], 2)." - ".$result["title"];
		$results[$i] = $result;
	}

	// Sort the results by score
	array_multisort(array_column($results, "score"), SORT_DESC, $results);


}


?>
