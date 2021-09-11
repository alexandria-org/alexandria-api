<?php

use PHPUnit\Framework\TestCase;

final class SearchTest extends TestCase {

	public function test_make_cached_search() {
		list($results, $time_ms, $total_found) = make_cached_search("test");
		$this->assertEquals(count($results), 1000);

		list($offset_start, $offset_end) = calculate_offsets(1, results_per_page());
		post_process_results($results, "test");
		list($output, $result_count) = deduplicate_results($results, $offset_start, $offset_end);

		$this->assertEquals(10, count($output));
	}

	public function test_store_search() {

		store_search_query("testing", false);
		$search_query = latest_search_query();
		
		$this->assertEquals($search_query->search_query, "testing");
		$this->assertFalse((bool)$search_query->search_cached);

		store_search_query("testing 2", true);
		$search_query = latest_search_query();
		
		$this->assertEquals($search_query->search_query, "testing 2");
		$this->assertTrue((bool)$search_query->search_cached);
	}

}

