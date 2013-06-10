<?php
/**
 * site_search php class (search operations)
 *
 * @author     Christos Pontikis http://pontikis.net
 * @copyright  Christos Pontikis
 * @license    MIT http://opensource.org/licenses/MIT
 * @version    0.1.0 (10 Jun 2013)
 *
 */
class site_search extends data_source {

	/** @var int Quick search quick max results to return */
	private $opt_search_quick_max_results;

	/** @var bool Exclude common words (true/false) */
	private $opt_search_exclude_common_words;

	/** @var array Common words to exclude from search */
	private $opt_search_exclude_as_common;

	/**
	 * Constructor
	 *
	 * @param array $a_db database settings
	 */
	public function __construct($a_db) {
		// initialize
		global $care_conf;

		$this->db_settings = $a_db;
		$this->opt_search_quick_max_results = $care_conf['opt_search_quick_max_results'];
		$this->opt_search_exclude_common_words = $care_conf['opt_search_exclude_common_words'];
		$this->opt_search_exclude_as_common = $care_conf['opt_search_exclude_as_common'];

	}

	/**
	 * Set opt_search_quick_max_results
	 *
	 * @param int $res opt_search_quick_max_results
	 */
	public function set_opt_search_quick_max_results($res) {
		$this->opt_search_quick_max_results = $res;
	}

	/**
	 * Set opt_search_quick_max_results
	 *
	 * @param bool $flag Exclude common words (true/false)
	 */
	public function set_opt_search_exclude_common_words($flag) {
		$this->opt_search_exclude_common_words = $flag;
	}

	/**
	 * Set opt_search_quick_max_results
	 *
	 * @param array $arr Common words to exclude from search
	 */
	public function set_opt_search_exclude_as_common($arr) {
		$this->opt_search_exclude_as_common = $arr;
	}

	/**
	 * Quick search - top jquery-ui autocomplete search box
	 *
	 * Given term will be slpitted in words. The condition is each word must be contained to result
	 *
	 * @param string $term given term
	 * @return string json to create jquery-ui autocomplete
	 */
	public function quick_search($term) {

		$url = '';
		$title = '';
		$part_type = '';
		$a_part_to_search = array();
		$a_parts = array();

		$a_json = array();
		$a_json_row = array();
		$now = "'" . $this->now('UTC') . "'";

		$a_json_null = array();
		array_push($a_json_null, array("id" => "#", "value" => $term, "label" => "Εξειδικεύστε την αναζήτηση..."));
		$json_null = json_encode($a_json_null);

		$a_json_invalid = array();
		array_push($a_json_invalid, array("id" => "#", "value" => $term, "label" => "Μόνο γράμματα και αριθμοί επιτρέπονται..."));
		$json_invalid = json_encode($a_json_invalid);

		// replace multiple spaces with one
		$term = preg_replace('/\s+/', ' ', $term);

		// SECURITY HOLE *******************************************************
		// allow space, any unicode letter and digit, underscore and dash
		if(preg_match("/[^\040\pL\pN_-]/u", $term)) {
			return $json_invalid;
		}
		// *********************************************************************

		$parts = explode(' ', $term);

		if($this->opt_search_exclude_common_words) {
			$parts = array_diff($parts, $this->opt_search_exclude_as_common);
			$parts = array_values($parts);
		}
		$p = count($parts);

		if($p == 0) {
			return $json_null;
		}

		/**
		 * $stmt->bind_param('s', $param); does not accept params array
		 * and if call_user_func_array will be used array params need to passed by reference
		 */
		for($i = 0; $i < $p; $i++) {
			$part_type .= 's';
		}
		$a_parts[] = & $part_type;

		foreach($parts as $part) {
			array_push($a_part_to_search, '%' . $part . '%');
		}
		for($i = 0; $i < $p; $i++) {
			$a_parts[] = & $a_part_to_search[$i];
		}

		$sql = 'SELECT url, title FROM search_data WHERE date_published is not null AND date_published <=' . $now;
		for($i = 0; $i < $p; $i++) {
			$sql .= ' AND content LIKE ?';
		}
		if($this->opt_search_quick_max_results > 0) {
			$sql .= ' LIMIT 0,' . $this->opt_search_quick_max_results;
		}

		$conn = $this->db_connect($this->db_settings);
		/* Prepare statement */
		$stmt = $conn->prepare($sql);
		if($stmt === false) {
			$user_error = 'Wrong SQL: ' . $sql . '<br>' . 'Error: ' . $conn->errno . ' ' . $conn->error;
			trigger_error($user_error, E_USER_ERROR);
		}

		/* Bind parameters. TYpes: s = string, i = integer, d = double,  b = blob */
		//$stmt->bind_param('s', $param); does not accept params array
		call_user_func_array(array($stmt, 'bind_param'), $a_parts);

		/* Execute statement */
		$stmt->execute();

		$stmt->bind_result($url, $title);

		while($stmt->fetch()) {
			$label = $title;
			for($i = 0; $i < $p; $i++) {
				// highlight search results
				$label = $this->mb_str_ireplace($parts[$i], '<span class="hl_results">' . $parts[$i] . '</span>', $label);
			}
			$a_json_row["id"] = $url;
			$a_json_row["value"] = $title;
			$a_json_row["label"] = $label;
			array_push($a_json, $a_json_row);
		}
		$json = json_encode($a_json);

		return $json;
	}

}