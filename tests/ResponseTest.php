<?php

use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase {

	public function test_error_response() {
		$this->expectOutputString('{"status":"error","reason":"Testing"}');
		error_response("Testing");
	}

}

