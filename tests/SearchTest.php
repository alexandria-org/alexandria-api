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

