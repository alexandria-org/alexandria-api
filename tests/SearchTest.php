<?php

use PHPUnit\Framework\TestCase;

final class SearchTest extends TestCase {

	public function test_make_cached_search() {
		list($results, $time_ms, $total_found) = make_cached_search("test");
		
		$this->assertEquals(count($results), 1000);
	}

}

