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

use PHPUnit\Framework\TestCase;

final class SearchTest extends TestCase {

	public function test_make_cached_search() {
		list($results, $time_ms, $total_found) = make_cached_search("t", "test", "", false);

		list($offset_start, $offset_end) = calculate_offsets(1, results_per_page());
		post_process_results("a", "test", $results);
		$this->assertEquals(1000, count($results));
		list($output, $result_count) = deduplicate_results($results, $offset_start, $offset_end);

		$this->assertEquals(10, count($output));
	}

	public function test_store_search() {

		store_uncached_search_query("testing", "127.0.0.1");
		$search_query = latest_search_query();
		
		$this->assertEquals($search_query->search_query, "testing");
		$this->assertEquals($search_query->search_ip, "127.0.0.1");
		$this->assertFalse((bool)$search_query->search_cached);

		store_cached_search_query("testing 2", "127.0.0.1");
		$search_query = latest_search_query();
		
		$this->assertEquals($search_query->search_query, "testing 2");
		$this->assertTrue((bool)$search_query->search_cached);
	}

	public function test_search() {
		$query = "testing_" . uniqid();
		list($response, $code) = handle_query("/", [
			"q" => $query,
			"p" => 1,
			"a" => 0
		], "123.123.123.123");

		$json = json_decode($response);
		$this->assertEquals(200, $code);
		$this->assertEquals("success", $json->status);
		$this->assertEquals(0, count($json->results));

		$search_query = latest_search_query();
		$this->assertEquals($search_query->search_query, $query);
		$this->assertEquals($search_query->search_ip, "123.123.123.123");

		// Test anonymous search.
		$query2 = "testing_" . uniqid();
		list($response, $code) = handle_query("/", [
			"q" => $query2,
			"p" => 1,
			"a" => 1
		], "123.123.123.123");

		$json = json_decode($response);
		$this->assertEquals(200, $code);
		$this->assertEquals("success", $json->status);
		$this->assertEquals(0, count($json->results));

		$search_query = latest_search_query();
		$this->assertEquals($search_query->search_query, "");
		$this->assertEquals($search_query->search_ip, "");
	}

	public function test_url_search() {
		list($response, $code) = handle_query("/url", [
			"u" => "http://example.com/",
		], "123.123.123.123");

		$json = json_decode($response);
		$this->assertEquals(200, $code);
		$this->assertEquals("success", $json->status);
		$this->assertTrue(strpos($json->result, "http://example.com/") === 0);
		$this->assertTrue($json->time_ms > 0);
	}

	public function test_ping() {
		list($response, $code) = handle_query("/ping", [
			"data" => "eyJ1IjoiaHR0cHM6Ly9uZXdzLnljb21iaW5hdG9yLmNvbS8iLCJxIjoibmV3cyIsInAiOjJ9",
		], "12.12.12.12");

		$ping = latest_ping();
		$this->assertEquals("https://news.ycombinator.com/", $ping->ping_url);
		$this->assertEquals("news", $ping->ping_query);
		$this->assertEquals("12.12.12.12", $ping->ping_ip);
	}

}

