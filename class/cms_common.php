<?php
/**
 * cms_common php class (common CMS operations)
 *
 * @author     Christos Pontikis http://pontikis.net
 * @copyright  Christos Pontikis
 * @license    MIT http://opensource.org/licenses/MIT
 * @version    0.1.0 (26 May 2013)
 *
 */
class cms_common extends data_source {

	/**
	 * Constructor
	 */
	public function __construct() {

	}

	/**
	 * Create a topics array (or just count results) using criteria
	 *
	 * @param array $a_db database connection settings
	 * @param array|null $a_mc memcached settings
	 * @param array $a_criteria
	 *
	 * <pre>
	 * $a_criteria = array(
	 *     "extra_columns_topics" => array("col1", "col2"), (or null)
	 *     "extra_columns_content" => array("col1", "col2"), (or null)
	 *     "publish_status" => int,
	 *     "date_from" => "date", (UTC date with format YYYYMMDDHHMMSS or null)
	 *     "date_until" => "date", (UTC date with format YYYYMMDDHHMMSS or null)
	 *     "with_content_type" => int, (ctg_id or null)
	 *     "with_topic_type" => int, (ctg_id or null)
	 *     "with_topic_type_in" => array(id1,id2,id3), (array of IDs or null)
	 *     "topic_type_column" => "topic_type_id", (topic_type_id/topic_top_type_id or null)
	 *     "exclude_news" => bool, (false/true)
	 *     "with_tag" => "tag_name", (or null)
	 *     "by_author" => int,  author_is or null
	 *     "order_by" => "order_column", (date_published/impressions or null)
	 *     "sort_order" => "DESC", (ASC/DESC or null)
	 *     "offset" => int, (0 or an integer)
	 *     "rows_to_return" => int, (0 or a possitive integer)
	 *     "memcached_key" => "key" (or null)
	 *     "expiration" => 0 (0 means never or number of seconds until expiration)
	 *     "count_only" => bool (false/true)
	 * );
	 * </pre>
	 *
	 * @return array|int (topics array or topics count)
	 */
	public function get_topics_list($a_db, $a_mc, $a_criteria) {

		$use_memcached = !is_null($a_mc) &&
			array_key_exists('memcached_key', $a_criteria) &&
			!is_null($a_criteria["memcached_key"]) &&
			strlen($a_criteria["memcached_key"]) > 0;

		$expiration = $use_memcached &&
			array_key_exists('expiration', $a_criteria) &&
			!is_null($a_criteria["expiration"]) &&
			$this->is_positive_integer($a_criteria["expiration"]);

		// pull from memcached
		if($use_memcached) {
			$a_topics_cached = $this->pull_from_memcached($a_mc, $a_criteria["memcached_key"]);
			if($a_topics_cached !== false) {
				return $a_topics_cached;
			}
		}

		// retrieve from database
		$conn = $this->db_connect($a_db);

		$count_only = array_key_exists('count_only', $a_criteria) && !is_null($a_criteria["count_only"]) && $a_criteria["count_only"];

		if(!array_key_exists('extra_columns_topics', $a_criteria) || is_null($a_criteria["extra_columns_topics"]) || !is_array($a_criteria["extra_columns_topics"])) {
			$a_criteria["extra_columns_topics"] = array();
		}
		if(!array_key_exists('extra_columns_content', $a_criteria) || is_null($a_criteria["extra_columns_content"]) || !is_array($a_criteria["extra_columns_content"])) {
			$a_criteria["extra_columns_content"] = array();
		}

		if($count_only) {
			$selectSQL = 'SELECT count(id) as topics_count FROM topics ';
		} else {
			$a_topics = array();
			$a_topic = array();

			if(count($a_criteria["extra_columns_content"]) == 0) {
				if(count($a_criteria["extra_columns_topics"]) == 0) {
					$selectSQL = 'SELECT id,title,url FROM topics ';
				} else {
					$selectSQL = 'SELECT id,title,url,' . implode(",", $a_criteria["extra_columns_topics"]) . ' FROM topics ';
				}
			} else {
				if(count($a_criteria["extra_columns_topics"]) == 0) {
					$selectSQL = 'SELECT t.id,t.title,t.url,c.' .
						implode(",c.", $a_criteria["extra_columns_content"]) .
						' FROM topics t LEFT OUTER JOIN content c ON t.content_id=c.id ';
				} else {
					$selectSQL = 'SELECT t.id,t.title,t.url,t.' .
						implode(",t.", $a_criteria["extra_columns_topics"]) .
						',c.' .
						implode(",c.", $a_criteria["extra_columns_content"]) .
						' FROM topics t LEFT OUTER JOIN content c ON t.content_id=c.id ';
				}
			}
		}

		$whereSQL = 'WHERE publish_status_id=' . TOPIC_STATUS_PUBLISHED . ' AND date_published IS NOT null ' .
			'AND publish_status_id=' . $a_criteria["publish_status"] . ' ' .
			'AND date_published <= ' . "'" . (is_null($a_criteria["date_until"]) ? $this->now('UTC') : $a_criteria["date_until"]) . "' ";
		if(array_key_exists('date_from', $a_criteria) && !is_null($a_criteria["date_from"])) {
			$whereSQL .= 'AND date_published >= ' . "'" . $a_criteria["date_from"] . "' ";
		}
		if(array_key_exists('with_content_type', $a_criteria) && !is_null($a_criteria["with_content_type"])) {
			$whereSQL .= 'AND content_type_id=' . $a_criteria["with_content_type"] . ' ';
		}
		if(array_key_exists('with_topic_type', $a_criteria) && !is_null($a_criteria["with_topic_type"])) {
			$whereSQL .= 'AND ' . $a_criteria["topic_type_column"] . '=' . $a_criteria["with_topic_type"] . ' ';
		}
		if(array_key_exists('with_topic_type_in', $a_criteria) && !is_null($a_criteria["with_topic_type_in"]) && count($a_criteria["with_topic_type_in"]) > 0
			&& array_key_exists('topic_type_column', $a_criteria) && !is_null($a_criteria["topic_type_column"])
		) {
			$whereSQL .= 'AND ' . $a_criteria["topic_type_column"] . ' IN (' . implode(",", $a_criteria["with_topic_type_in"]) . ') ';
		}
		if(array_key_exists('with_tag', $a_criteria) && !is_null($a_criteria["with_tag"])) {
			$whereSQL .= 'AND tags LIKE ' . "'%|" . $conn->real_escape_string($a_criteria["with_tag"]) . "|%'" . ' ';
		}
		if(array_key_exists('by_author', $a_criteria) && !is_null($a_criteria["by_author"]) && $this->is_positive_integer($a_criteria["by_author"])) {
			$whereSQL .= 'AND author_id=' . $a_criteria["by_author"] . ' ';
		}
		if(array_key_exists('exclude_news', $a_criteria) && !is_null($a_criteria["exclude_news"]) && $a_criteria["exclude_news"]) {
			$whereSQL .= 'AND (topic_type_id NOT IN (11,12) OR topic_type_id IS NULL) '; // 11 = news_hellas 12 = news_world
		}

		if(!$a_criteria["count_only"]) {
			$orderSQL = 'ORDER BY ' . $a_criteria["order_by"] . ' ' . $a_criteria["sort_order"] . ' ';
			$limitSQL = 'LIMIT ' . $a_criteria["offset"] . ',' . $a_criteria["rows_to_return"];
		}

		if($count_only) {
			$sql = $selectSQL . $whereSQL;
		} else {
			$sql = $selectSQL . $whereSQL . $orderSQL . $limitSQL;
		}

		$rs = $conn->query($sql);
		if($rs === false) {
			$user_error = 'Wrong SQL: ' . $sql . '<br>' . 'Error: ' . $conn->errno . ' ' . $conn->error;
			trigger_error($user_error, E_USER_ERROR);
		}
		$rs->data_seek(0);

		if($count_only) {
			$topics = $rs->fetch_array(MYSQLI_ASSOC);
			$total_topics = $topics['topics_count'];
		} else {
			while($topic = $rs->fetch_assoc()) {
				$a_topic['id'] = $topic['id'];
				$a_topic['title'] = $topic['title'];
				$a_topic['url'] = 'http://' . $_SERVER['SERVER_NAME'] . '/post/' . $topic['id'] . '/' . $topic['url'];
				foreach($a_criteria["extra_columns_topics"] as $col) {
					$a_topic[$col] = $topic[$col];
				}
				foreach($a_criteria["extra_columns_content"] as $col) {
					$a_topic[$col] = $topic[$col];
				}
				array_push($a_topics, $a_topic);
			}
		}

		$rs->free();

		// push to memcached
		if($use_memcached) {
			if($expiration) {
				$this->push_to_memcached($a_mc, $a_criteria["memcached_key"], $a_topics, $a_criteria["expiration"]);
			} else {
				$this->push_to_memcached($a_mc, $a_criteria["memcached_key"], $a_topics);
			}
		}

		if($count_only) {
			return $total_topics;
		} else {
			return $a_topics;
		}

	}


	/**
	 * Get recent topics
	 *
	 * @param array $a_db database connection settings
	 * @param array $a_mc memcached settings
	 * @param int $max_recent max recent topics to return
	 * @return array recent topics array
	 */
	public function get_recent_topics($a_db, $a_mc, $max_recent) {
		$a_recent_topics_criteria = array(
			"publish_status" => TOPIC_STATUS_PUBLISHED,
			"order_by" => "date_published",
			"sort_order" => "DESC",
			"offset" => 0,
			"rows_to_return" => $max_recent,
			"memcached_key" => "care_recent_topics",
			"count_only" => false
		);
		return $this->get_topics_list($a_db, $a_mc, $a_recent_topics_criteria);
	}

}