<?php

interface WikidataInterface {

	// Constructs a new object from wikidata json.
	public function __construct($data);

	// Makes API call to download multiple wikidata pages from the API.
	public static function load_from_qids($qids) : array;

	// Makes API call to download single page.
	public static function load_from_qid($qid);

	// Makes a string search with the wikidata API.
	public static function search($query, $limit = 1) : array;

	// Given a string finds QID in that string. Returns false if qid cannot be found.
	public static function find_qid_in_string($string);

	// Returns a link to wikidata for the given qid.
	public static function qid_link($qid) : string;


	// Getters.
	public function qid() : string;
	public function labels();
	public function descriptions();
	public function main_title() : string;
	public function localizations() : array;
	public function claims($property) : array; // Returns the Qids of the claims.
	public function claim_values($property) : array; // Returns the value of the claims.
	public function release_date();
	public function date_of_birth();
	public function site_links();
	public function creator_qids() : array;
	public function genres() : array;
	public function main_subjects() : array;
	public function subclass_of() : array;
	//public function image_url() : string; // Not implemented

	// Booleans.
	public function is_movie() : bool;
	public function is_tv_series() : bool;

}

class Wikidata implements WikidataInterface {

	const creator_map = [
		'director' => 'P57',
		'bookAuthor' => 'P50',
		'creator' => 'P170',
		'manufacturer' => 'P176',
		'brand' => 'P1716',
		'developer' => 'P178',
		'publisher' => 'P123',
		'distributor' => 'P750',
		'productionCompany' => 'P272'
	];

	public function __construct($data) {
		$this->data = json_decode($data);
	}

	public static function load_from_qids($qids) : array {
		if (count($qids) == 0) return [];
		$url = "https://www.wikidata.org/w/api.php?action=wbgetentities&format=json&ids=" .
			urlencode(implode("|", $qids));

		$curl = new CURL();
		$data = $curl->get($url);

		$data = json_decode($data);

		$objects = [];
		if ($data->success) {
			foreach ($data->entities as $entity) {
				$objects[] = new Wikidata(json_encode($entity));
			}
		}
		return $objects;
	}

	public static function load_from_qid($qid) {
		$objects = self::load_from_qids([$qid]);
		if (count($objects) == 1) return $objects[0];
		throw new Exception("Could not find qid");
	}

	public static function search($query, $limit = 1) : array {
		$url = "https://wikidata.org/w/api.php?action=query&list=search&srsearch=".
			urlencode($query)."&format=json";

		$curl = new CURL();
		$data = $curl->get($url);
		
		$searchResults = json_decode($data, true);
		$resultNum = 0;
		$qidList = [];
		foreach ($searchResults["query"]["search"] as $result) {
			if ($resultNum < $limit){
				$qidList[] = $result['title'];
			} 
			$resultNum++;
		}

		return self::load_from_qids($qidList);
	}

	public static function find_qid_in_string($string) {
		if (preg_match('#Q[0-9]+#is', $string, $match)) {
			return $match[0];
		}
		return false;
	}

	public static function qid_link($qid) : string {
		return 'https://www.wikidata.org/wiki/' . $qid;
	}

	public function qid() : string {
		return $this->data->id;
	}

	public function labels() {
		return $this->data->labels ?? [];
	}

	public function descriptions() {
		return $this->data->descriptions ?? [];
	}

	public function main_title() : string {
		$labels = $this->labels();

		$englishTitle = '';
		$swedishTitle = '';
		$anyTitle = '';

		foreach ($labels as $label) {
			if ($label->language == "en") {
				$englishTitle = $label->value;
			} else if ($label->language == "sv") {
				$swedishTitle = $label->value;
			} else {
				$anyTitle = $label->value;
			}
		}

		$mainTitle = $swedishTitle;
		if ($mainTitle == "") {
			$mainTitle = $englishTitle;
		}
		if ($mainTitle == "") {
			$mainTitle = $anyTitle;
		}
		return trim($mainTitle);
	}
	
	public function main_description() : string {
		$descriptions = $this->descriptions();

		$english_description = '';
		$swedish_description = '';
		$any_description = '';

		foreach ($descriptions as $description) {
			if ($description->language == "en") {
				$english_description = $description->value;
			} else if ($description->language == "sv") {
				$swedish_description = $description->value;
			} else {
				$any_description = $description->value;
			}
		}

		$main_description = $swedish_description;
		if ($main_description == "") {
			$main_description = $english_description;
		}
		if ($main_description == "") {
			$main_description = $any_description;
		}
		return trim($main_description);
	}

	public function localizations() : array {

		$labels = $this->labels();
		$descriptions = $this->descriptions();

		$localization = [];
		foreach ($labels as $label) {
			if (!isset($localization[$label->language])) {
				$localization[$label->language] = new StdClass();
			}
			$localization[$label->language]->label = $label->value;
		}
		foreach ($descriptions as $description) {
			if (isset($localization[$description->language])) {
				$localization[$description->language]->description = $description->value;
			}
		}
		$sitelinks = $this->site_links();

		foreach ($sitelinks as $sitelink) {
			if (substr($sitelink->site, -4) == "wiki") {
				$language = str_replace("wiki", "", $sitelink->site);
				$language = str_replace("_", "-", $language);
				if (!isset($localization[$language])) {
					$localization[$language] = new StdClass();
				}
				$localization[$language]->wikiname = $sitelink->title;
				if (!isset($localization[$language]->label)) {
					$localization[$language]->label = $sitelink->title;
				}
			}
		}

		// Continue coding here.
		$aliases = $this->data->aliases ?? [];
		foreach ($aliases as $aliasLanguage) {
			foreach ($aliasLanguage as $alias) {
				if (!isset($localization[$alias->language])) continue;
				if (!isset($localization[$alias->language]->aliases)) {
					$localization[$alias->language]->aliases = [];
				}
				$localization[$alias->language]->aliases[] = $alias->value;
			}
		}

		$return = [];
		foreach ($localization as $language => $data) {
			$data->locale = $language;
			$return[$language] = $data;
		}

		return $return;
	}

	/*public function image_url() : string {
		$images = $this->claim_values("P18");
		$wikipedia = new Wikipedia("en");
		foreach ($images as $imageUri) {
			return $wikipedia->getImageUrlFromUri(Wikipedia::makeUri($imageUri));
		}

		return null;
	}*/

	public function claims($property) : array {
		$ids = [];

		foreach ($this->data->claims->{$property} ?? [] as $instanceOfData) {
			if (isset($instanceOfData->mainsnak->datavalue->value->id)) {
				$ids[] = $instanceOfData->mainsnak->datavalue->value->id;
			}
		}

		return $ids;
	}

	public function claim_values($property) : array {
		$values = [];
		foreach ($this->data->claims->{$property} ?? [] as $instance_of_data) {
			if (isset($instance_of_data->mainsnak->datavalue->value)) {
				$values[] = $instance_of_data->mainsnak->datavalue->value;
			}
		}

		return $values;
	}

	public function release_date() {
		$release_dates = $this->claim_values("P577");
		foreach ($release_dates as $release_date) {
			if (isset($release_date->time)) {
				return new WikiDate($release_date);
			}
		}
		return null;
	}

	public function date_of_birth() {
		$date_of_births = $this->claim_values("P569");
		foreach ($date_of_births as $date_of_birth) {
			if (isset($date_of_birth->time)) {
				return new WikiDate($date_of_birth);
			}
		}
		return null;
	}

	public function site_links() {
		return $this->data->sitelinks ?? [];
	}

	public function creator_qids() : array {
		$creators = [];
		foreach (self::creator_map as $role => $property) {
			$creators[$role] = $this->claims($property);
		}

		return $creators;
	}

	public function genres() : array {
		return $this->claims("P136");
	}

	public function main_subjects() : array {
		return $this->claims("P921");
	}

	public function subclass_of() : array {
		return $this->claims("P279");
	}
	
	public function official_website() : string {
		$websites = $this->claim_values("P856");
		if (isset($websites) && is_array($websites)) {
			if (isset($websites[0]) && is_string($websites[0])) {
				return $websites[0];
			} else {
				return "";
			}
		} else {
			return "";
		}
		 
	}
	
	public function is_newspaper () : bool {
		$instance_of = $this->claims("P31");
		return count(array_intersect($instance_of, [
			"Q11032"
		])) > 0;
	}
	
	public function is_website() : bool {
		$instance_of = $this->claims("P31");
		return count(array_intersect($instance_of, [
			"Q35127"
		])) > 0;
	}
	
	public function is_financial() : bool {
		$instance_of = $this->claims("P31");
		return count(array_intersect($instance_of, [
			"Q1643989",
			"Q182076",
			"Q3196867",
			"Q160151",
			"Q179179",
			"Q161380",
			"Q1436963",
			"Q43183",
			"Q1166072",
			"Q15809678",
			"Q837171",
			"Q29028649",
			"Q750458",
			"Q208697",
			"Q4290",
			"Q4201895"
		])) > 0;
	}
	public function is_movie() : bool {
		$instance_of = $this->claims("P31");
		return count(array_intersect($instance_of, [
			"Q11424",
			"Q157443",
			"Q29168811",
			"Q93204",
			"Q506240",
			"Q24869",
			"Q20650540",
			"Q24862",
			"Q18011172",
			"Q20667187",
			"Q130232"
		])) > 0;
	}

	public function is_tv_series() : bool {
		$instance_of = $this->claims("P31");
		return count(array_intersect($instance_of, [
			"Q5398426",
			"Q63952888",
			"Q1366112"
		])) > 0;
	}

}

?>
