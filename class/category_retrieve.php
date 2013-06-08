<?php
/**
 * category_retrieve php class (display category operations)
 *
 * @author     Christos Pontikis http://pontikis.net
 * @copyright  Christos Pontikis
 * @license    MIT http://opensource.org/licenses/MIT
 * @version    0.1.0 (30 May 2013)
 *
 */
class category_retrieve extends cms_common {

	/** @var bool Option to use memcached (true) or not (false) */
	private $opt_use_memcached;

	/** @var bool Option opt_show_relative_categories (true) or not (false) */
	private $opt_show_relative_categories;

	/** @var bool Option opt_show_popular_topics (true) or not (false) */
	private $opt_show_popular_topics;

	/** @var string Data origin ('undefined', 'memcached', 'database') */
	private $data_origin;

	/**
	 * Constructor
	 *
	 * @param array $a_db database settings
	 * @param array $a_mc memcached settings
	 * @param string $category_url category url
	 * @param int $offset offset to start topics display
	 * @param int $max_topics_per_page max topics per page
	 * @param int $max_popular max popular topics to display
	 */
	public function __construct($a_db, $a_mc, $category_url, $offset, $max_topics_per_page, $max_popular) {
		// initialize
		global $care_conf;
		$this->db_settings = $a_db;
		$this->mc_settings = $a_mc;
		$this->category_url = $category_url;
		$this->offset = $offset;
		$this->max_topics_per_page = $max_topics_per_page;
		$this->max_popular = $max_popular;

		$this->opt_use_memcached = $a_mc["use_memcached"];
		$this->opt_show_popular_topics = $care_conf['opt_show_popular_topics_per_category'];
		$this->opt_show_relative_categories = $care_conf['opt_show_relative_categories'];
		$this->data_origin = 'undefined';
	}

	/**
	 * Set opt_use_memcached
	 *
	 * @param bool $flag opt_use_memcached value
	 */
	public function set_opt_use_memcached($flag) {
		$this->opt_use_memcached = $flag;
	}

	/**
	 * Set opt_show_relative_categories
	 *
	 * @param bool $flag opt_show_relative_categories
	 */
	public function set_opt_show_relative_categories($flag) {
		$this->opt_show_relative_categories = $flag;
	}

	/**
	 * Set opt_show_popular_topics
	 *
	 * @param bool $flag opt_show_popular_topics
	 */
	public function set_opt_show_popular_topics($flag) {
		$this->opt_show_popular_topics = $flag;
	}

	/**
	 * Get data origin ('undefined', 'memcached', 'database')
	 *
	 * @return string data origin
	 */
	public function get_data_origin() {
		return $this->data_origin;
	}

	/**
	 * Get category
	 *
	 * @return array|bool category data or false (not existed category)
	 */
	public function get_category() {

		// pull from memcached
		if($this->opt_use_memcached) {
			$category_key = 'care_category_' . sha1($this->category_url);
			$category = $this->pull_from_memcached($this->mc_settings, $category_key);
			if($category) {
				$this->data_origin = 'memcached';
			}
		}

		// get category properties ---------------------------------------------
		if($this->data_origin == 'undefined') {
			$category = $this->get_category_properties();
			if(!$category) {
				return false;
			}
		}

		// get relative categories ---------------------------------------------
		if($this->opt_show_relative_categories) {
			if(!array_key_exists('a_rel_categories', $category)) {
				$category["a_rel_categories"] = $this->get_relative_categories($category);
			}
		}

		// get category topics (hierarchical view html) ------------------------
		if($category["list_mode"] == 2) {
			if(!array_key_exists('topics_toc_html', $category)) {
				$category["topics_toc_html"] = $this->get_category_toc_html($category["id"]);
			}
		}
		if($category["list_mode"] == 3) {
			if(!array_key_exists('topics_toc_html', $category)) {
				$category["topics_toc_html"] = $this->get_category_toc_no_posts_html($category["id"]);
			}
		}

		// get category subcategories
		if($category['list_mode'] == 2) {
			if(!array_key_exists('a_sub_ctgs', $category)) {
				$category["a_sub_ctgs"] = $this->get_sub_categories($category["id"]);
			}
		}

		// push to memcached ---------------------------------------------------
		if($this->data_origin == 'database') {
			if($this->opt_use_memcached) {
				$category_key = 'care_category_' . sha1($this->category_url);
				$category["date_cached"] = $this->now('UTC');
				$this->push_to_memcached($this->mc_settings, $category_key, $category);
			}
		}

		// get category topics (page topics - always from database) ------------
		$category["page_topics"] = array();
		if($category["list_mode"] == 1) {
			$res = $this->get_category_page_topics($category);
			$category["total_topics"] = $res["total_topics"];
			$category["page_topics"] = $res["page_topics"];
		}

		// get category popular topics (always from database) ------------------
		$category["a_popular_topics"] = array();
		if($this->opt_show_popular_topics) {
			if($category['list_mode'] != 3) {
				$category["a_popular_topics"] = $this->get_category_popular_topics($category);
			}
		}

		return $category;
	}

	/**
	 * Get category properties
	 *
	 * @return array|bool category record data or false (non existed category)
	 */
	public function get_category_properties() {

		$conn = $this->db_connect($this->db_settings);
		$sql = 'SELECT * FROM categories WHERE url = ?';

		/* Prepare statement */
		$stmt = $conn->prepare($sql);
		if($stmt === false) {
			$user_error = 'Wrong SQL: ' . $sql . '<br>' . 'Error: ' . $conn->errno . ' ' . $conn->error;
			trigger_error($user_error, E_USER_ERROR);
		}
		/* Bind parameters. Types: s = string, i = integer, d = double,  b = blob */
		$stmt->bind_param('s', $this->category_url);
		/* Execute statement */
		$stmt->execute();
		/* get result */
		$res = $stmt->get_result();
		$rs = $res->fetch_all(MYSQLI_ASSOC);
		if(count($rs) == 1) {
			$category = $rs[0];
			$this->data_origin = 'database';
			/* free result */
			$stmt->free_result();
		} else {
			/* free result */
			$stmt->free_result();
			return false;
		}
		/* close statement */
		$stmt->close();

		return $category;
	}

	/**
	 * Get relative categories
	 *
	 * @param array $category
	 * @return array relative categories (title, url)
	 */
	public function get_relative_categories($category) {
		$a_rel_categories = array();
		$a_rel_category = array();
		$conn = $this->db_connect($this->db_settings);

		if($category["list_mode"] == 1) {
			if($category['ctg_type'] == 1) { // content type categories
				$sql = 'SELECT category, url FROM categories WHERE parent_a_id = ' . $category['id'] . ' ORDER BY display_order_a';
			} else { // topic type categories
				$sql = 'SELECT category, url FROM categories WHERE parent_b_id = 2 AND id != ' . $category['id'] . ' ORDER BY display_order_b';
			}
			$rs = $conn->query($sql);
			if($rs === false) {
				$user_error = 'Wrong SQL: ' . $sql . '<br>' . 'Error: ' . $conn->errno . ' ' . $conn->error;
				trigger_error($user_error, E_USER_ERROR);
			}

			if($category['ctg_type'] == 2) { // topic type categories
				if($category['id'] == 1) { // Editorial
					$a_rel_category['category'] = 'Χρήσιμα';
					$a_rel_category['url'] = 'http://' . $_SERVER['SERVER_NAME'] . '/category/' . 'health-tips';
					array_push($a_rel_categories, $a_rel_category);
				} else if($category['id'] == 4) { // Χρήσιμα
					$a_rel_category['category'] = 'Editorial';
					$a_rel_category['url'] = 'http://' . $_SERVER['SERVER_NAME'] . '/category/' . 'editorial';
					array_push($a_rel_categories, $a_rel_category);
				} else {
					$a_rel_category['category'] = 'Editorial';
					$a_rel_category['url'] = 'http://' . $_SERVER['SERVER_NAME'] . '/category/' . 'editorial';
					array_push($a_rel_categories, $a_rel_category);
					$a_rel_category['category'] = 'Χρήσιμα';
					$a_rel_category['url'] = 'http://' . $_SERVER['SERVER_NAME'] . '/category/' . 'health-tips';
					array_push($a_rel_categories, $a_rel_category);
				}
			}

			$rs->data_seek(0);
			while($rel_cat = $rs->fetch_assoc()) {
				$a_rel_category['category'] = $rel_cat['category'];
				$a_rel_category['url'] = 'http://' . $_SERVER['SERVER_NAME'] . '/category/' . $rel_cat['url'];
				array_push($a_rel_categories, $a_rel_category);
			}
		}

		if($category["list_mode"] == 2) {
			$sql = 'SELECT category, url FROM categories ' .
				'WHERE id != ' . $category["id"] . ' AND parent_b_id  = ' . $category["parent_b_id"] . ' ' .
				'ORDER BY display_order_b';
			$rs = $conn->query($sql);
			if($rs === false) {
				$user_error = 'Wrong SQL: ' . $sql . '<br>' . 'Error: ' . $conn->errno . ' ' . $conn->error;
				trigger_error($user_error, E_USER_ERROR);
			}
			$rs->data_seek(0);
			while($rel_cat = $rs->fetch_assoc()) {
				$a_rel_category['category'] = $rel_cat['category'];
				$a_rel_category['url'] = 'http://' . $_SERVER['SERVER_NAME'] . '/category/' . $rel_cat['url'];
				array_push($a_rel_categories, $a_rel_category);
			}
			$rs->free();
		}

		return $a_rel_categories;
	}

	/**
	 * Get category page topics (always from database)
	 *
	 * @param array $category category properties
	 * @return array page topics (title, url, date_published, impressions, comments, ctg_intro, ctg_image)
	 */
	private function get_category_page_topics($category) {
		$a_category_page_topics_criteria = array(
			"extra_columns_topics" => array("date_published", "impressions", "comments"),
			"extra_columns_content" => array("ctg_image", "ctg_intro"),
			"publish_status" => TOPIC_STATUS_PUBLISHED,
			"with_content_type" => ($category["ctg_type"] == 1 ? $category["id"] : null),
			"with_topic_type" => ($category["ctg_type"] == 2 ? $category["id"] : null),
			"topic_type_column" => (in_array($category["id"], array(1, 4)) ? 'topic_top_type_id' : 'topic_type_id'),
			"order_by" => "date_published",
			"sort_order" => "DESC",
			"offset" => $this->offset,
			"rows_to_return" => $this->max_topics_per_page,
			"count_only" => true
		);
		$total_topics = $this->get_topics_list($this->db_settings, $this->mc_settings, $a_category_page_topics_criteria);

		$page_topics = array();
		if($total_topics > 0) {
			$a_category_page_topics_criteria["count_only"] = false;
			$page_topics = $this->get_topics_list($this->db_settings, $this->mc_settings, $a_category_page_topics_criteria);
		}

		return array("total_topics" => $total_topics, "page_topics" => $page_topics);

	}

	/**
	 * Get category contents in hierarchical view (as html)
	 *
	 * @param int $parent_id the category id
	 * @return string category toc (as html)
	 */
	public function get_category_toc_html($parent_id) {

		static $html = '';
		static $ctg_id;
		$conn = $this->db_connect($this->db_settings);

		$sql = 'SELECT id, category FROM categories WHERE parent_b_id=' . $parent_id . ' ORDER BY display_order_b';

		$rs = $conn->query($sql);
		if($rs === false) {
			$user_error = 'Wrong SQL: ' . $sql . '<br>' . 'Error: ' . $conn->errno . ' ' . $conn->error;
			trigger_error($user_error, E_USER_ERROR);
		} else {
			$rows = $rs->num_rows;
		}

		if($rows > 0) {
			$html .= '<ul>' . PHP_EOL;

			$rs->data_seek(0);
			while($row = $rs->fetch_assoc()) {
				$html .= '<li>' . $row['category'] . PHP_EOL;
				$ctg_id = $row['id'];
				$this->get_category_toc_html($row['id']);
				$html .= '</li>' . PHP_EOL;
			}

			$html .= '</ul>' . PHP_EOL;
		} else {
			if($ctg_id > 0) {
				$sql_topics = 'SELECT id, title, url FROM topics WHERE topic_type_id=' . $ctg_id . ' ORDER BY display_order';
				$rs_topics = $conn->query($sql_topics);
				if($rs_topics === false) {
					$user_error = 'Wrong SQL: ' . $sql . '<br>' . 'Error: ' . $conn->errno . ' ' . $conn->error;
					trigger_error($user_error, E_USER_ERROR);
				}
				$rs_topics->data_seek(0);
				$html .= '<ul>' . PHP_EOL;
				while($topic = $rs_topics->fetch_assoc()) {
					$html .= '<li class="toc-topic"><a href="http://' . $_SERVER['SERVER_NAME'] . '/post/' . $topic['id'] . '/' . $topic['url'] . '">' . $topic['title'] . '</a>' . PHP_EOL;
					$html .= '</li>' . PHP_EOL;
				}
				$html .= '</ul>' . PHP_EOL;
			}
		}

		return $html;

	}

	/**
	 * Get category and its subcategories in hierarchical view (as html)
	 *
	 * @param int $parent_id the category id
	 * @return string category toc (as html)
	 */
	public function get_category_toc_no_posts_html($parent_id) {

		static $html = '';
		$conn = $this->db_connect($this->db_settings);
		$sql = 'SELECT id, category, url FROM categories WHERE parent_b_id=' . $parent_id . ' AND url is not null ORDER BY display_order_b';
		$rs = $conn->query($sql);
		if($rs === false) {
			$user_error = 'Wrong SQL: ' . $sql . '<br>' . 'Error: ' . $conn->errno . ' ' . $conn->error;
			trigger_error($user_error, E_USER_ERROR);
		} else {
			$rows = $rs->num_rows;
		}

		if($rows > 0) {
			$html .= '<ul>' . PHP_EOL;
			$rs->data_seek(0);
			while($row = $rs->fetch_assoc()) {
				$html .= '<li><a href="http://' . $_SERVER['SERVER_NAME'] . '/category/' . $row['url'] . '">' . $row['category'] . '</a>' . PHP_EOL;
				$this->get_category_toc_no_posts_html($row['id']);
				$html .= '</li>' . PHP_EOL;
			}
			$html .= '</ul>' . PHP_EOL;
		}

		return $html;
	}

	/**
	 * Get gategory popular topics (always from database)
	 *
	 * @param array $category category properties
	 * @return array popular topics (title, url, impressions, date published)
	 */
	public function get_category_popular_topics($category) {

		$a_category_popular_topics_criteria = array(
			"extra_columns_topics" => array("impressions"),
			"publish_status" => TOPIC_STATUS_PUBLISHED,
			"with_content_type" => ($category["ctg_type"] == 1 ? $category["id"] : null),
			"with_topic_type" => ($category["ctg_type"] == 2 ? $category["id"] : null),
			"topic_type_column" => (in_array($category["id"], array(1, 4)) ? 'topic_top_type_id' : 'topic_type_id'),
			"order_by" => "impressions",
			"sort_order" => "DESC",
			"offset" => 0,
			"rows_to_return" => $this->max_popular,
			"count_only" => false
		);

		if($category['list_mode'] == 2) {
			$a_sub_ctgs = $category["a_sub_ctgs"];
			if(count($a_sub_ctgs) == 0) {
				return array();
			} else {
				$a_category_popular_topics_criteria["with_topic_type"] = null;
				$a_category_popular_topics_criteria["with_topic_type_in"] = $category["ctg_type"] == 2 ? $a_sub_ctgs : null;
			}
		}

		return $this->get_topics_list($this->db_settings, $this->mc_settings, $a_category_popular_topics_criteria);
	}

	/**
	 * Get subcategories of a category using recursion
	 *
	 * @param int $category_id
	 * @return array subcategories IDs
	 */
	public function get_sub_categories($category_id) {

		$conn = $this->db_connect($this->db_settings);
		static $children = array();

		$sql = 'SELECT id FROM categories WHERE parent_b_id=' . $category_id . ' AND url is null ORDER BY display_order_b';

		$rs = $conn->query($sql);
		if($rs === false) {
			$user_error = 'Wrong SQL: ' . $sql . '<br>' . 'Error: ' . $conn->errno . ' ' . $conn->error;
			trigger_error($user_error, E_USER_ERROR);
		} else {
			$rows = $rs->num_rows;
		}

		if($rows > 0) {
			$rs->data_seek(0);
			while($row = $rs->fetch_assoc()) {
				array_push($children, $row['id']);
				$this->get_sub_categories($row['id']);
			}
		}

		return $children;

	}

	/**
	 * Destructor
	 */
	public function __destruct() {
		$this->db_disconnect();
	}

}