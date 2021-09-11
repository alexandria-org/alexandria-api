<?php

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
		list($query, $current_page) = parse_input(["q" => "test query"]);
		$this->assertEquals($query, "test query");
		$this->assertEquals($current_page, 1);
	}

	public function test_parse_current_page() {
		list($query, $current_page) = parse_input(["q" => "test query", "p" => 2]);
		$this->assertEquals($query, "test query");
		$this->assertEquals($current_page, 2);

		list($query, $current_page) = parse_input(["q" => "test query 2", "p" => 0]);
		$this->assertEquals($query, "test query 2");
		$this->assertEquals($current_page, 1);

		list($query, $current_page) = parse_input(["q" => "test query 3", "p" => max_pages() + 1]);
		$this->assertEquals($query, "test query 3");
		$this->assertEquals($current_page, max_pages());
	}

	public function test_calculate_offsets() {
		$current_page = 1;
		$results_per_page = 10;
		list($offset_start, $offset_end) = calculate_offsets($current_page, $results_per_page);
		$this->assertEquals($offset_start, 0);
		$this->assertEquals($offset_end, 10);
	}

}

