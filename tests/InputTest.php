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

final class InputTest extends TestCase {

	public function test_parse_empty_input() {
		$this->expectException(Exception::class);
		parse_input([]);
	}	

	public function test_parse_empty_query() {
		$this->expectException(Exception::class);
		parse_input(["q" => ""]);
	}

	public function test_parse_non_empty_query() {
		list($query, $current_page, $anonymous, $cluster) = parse_input(["q" => "test query"]);
		$this->assertEquals($query, "test query");
		$this->assertEquals($current_page, 1);
		$this->assertEquals($anonymous, false);
		$this->assertEquals($cluster, get_active_cluster());
	}

	public function test_parse_current_page() {
		list($query, $current_page, $anonymous) = parse_input(["q" => "test query", "p" => 2]);
		$this->assertEquals($query, "test query");
		$this->assertEquals($current_page, 2);
		$this->assertEquals($anonymous, false);

		list($query, $current_page, $anonymous) = parse_input(["q" => "test query 2", "p" => 0]);
		$this->assertEquals($query, "test query 2");
		$this->assertEquals($current_page, 1);
		$this->assertEquals($anonymous, false);

		list($query, $current_page) = parse_input(["q" => "test query 3", "p" => max_pages() + 1]);
		$this->assertEquals($query, "test query 3");
		$this->assertEquals($current_page, max_pages());
	}

	public function test_parse_anonymous() {
		list($query, $current_page, $anonymous) = parse_input(["q" => "test query", "p" => 1, "a" => 1]);
		$this->assertEquals($query, "test query");
		$this->assertEquals($current_page, 1);
		$this->assertEquals($anonymous, true);

		list($query, $current_page, $anonymous) = parse_input(["q" => "test query", "p" => 1, "a" => 0]);
		$this->assertEquals($query, "test query");
		$this->assertEquals($current_page, 1);
		$this->assertEquals($anonymous, false);
	}

	public function test_calculate_offsets() {
		$current_page = 1;
		$results_per_page = 10;

		list($offset_start, $offset_end) = calculate_offsets($current_page, $results_per_page);
		$this->assertEquals($offset_start, 0);
		$this->assertEquals($offset_end, 10);

		list($offset_start, $offset_end) = calculate_offsets(2, $results_per_page);
		$this->assertEquals($offset_start, 10);
		$this->assertEquals($offset_end, 20);

		list($offset_start, $offset_end) = calculate_offsets(2, 5);
		$this->assertEquals($offset_start, 5);
		$this->assertEquals($offset_end, 10);
	}

	public function test_cluster_input() {
		list($query, $current_page, $anonymous, $cluster) = parse_input(["q" => "test query", "p" => 1, "a" => 1, "c" => "b"]);
		$this->assertEquals($query, "test query");
		$this->assertEquals($current_page, 1);
		$this->assertEquals($anonymous, true);
		$this->assertEquals($cluster, "b");
	}

}

