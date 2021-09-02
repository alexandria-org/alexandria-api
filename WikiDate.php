<?php

class WikiDate {

	public function __construct($wikiTime) {
		$precision = "day";
		$time = $wikiTime->time;
		$clean = str_replace("-00-00T","-01-01T", $time);
		if ($clean != $time) $precision = "year";
		$this->date = date("Y-m-d", strtotime($clean));
		$this->precision = $precision;
	}

	public function as_string() {
		if ($this->precision == "year") return date("Y", strtotime($this->date));
		return $this->date;
	}

	public function as_date() {
		return $this->date;
	}

	public function precision() {
		return $this->precision;
	}

}

?>
