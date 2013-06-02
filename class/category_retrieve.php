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

	/** @var int Data origin (0 = memcached, 1 = database) */
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
		$this->db_settings = $a_db;
		$this->mc_settings = $a_mc;
		$this->category_url = $category_url;
		$this->offset = $offset;
		$this->max_topics_per_page = $max_topics_per_page;
		$this->max_popular = $max_popular;

		$this->opt_use_memcached = $a_mc["use_memcached"];
		$this->opt_show_popular_topics = OPT_SHOW_POPULAR_TOPICS_PER_CATEGORY;
		$this->opt_show_relative_categories = OPT_SHOW_RELATIVE_CATEGORIES;
		$this->data_origin = null;
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
	 * Get data origin (0 = memcached, 1 = database)
	 *
	 * @return int|null data origin
	 */
	public function get_data_origin() {
		return $this->data_origin;
	}

	/**
	 * Get topic from topic id
	 *
	 * @return array|bool post data or false
	 */
	public function get_category() {

		$category = false;
		$category_key = 'care_category_' . sha1($this->category_url);

		// pull from memcached
		if($this->opt_use_memcached) {
			$category_cached = $this->pull_from_memcached($this->mc_settings, $category_key);
			if($category_cached) {
				$this->data_origin = 0;

				// get category page topics (always from database)
				if($category_cached["list_mode"] == 1) {
					$res = $this->get_category_page_topics($category_cached);
					$category_cached["total_topics"] = $res["total_topics"];
					$category_cached["page_topics"] = $res["page_topics"];
				}

				// get category popular topics (always from database)
				$category_cached["a_popular_topics"] = array();
				if($this->opt_show_popular_topics) {
					$category_cached["a_popular_topics"] = $this->get_category_popular_topics($category_cached);
				}

				return $category_cached;
			}
		}

		// retrieve category from database -------------------------------------
		$category = $this->get_category_properties($this->category_url);
		if(!$category) {
			return false;
		}

		// get relative categories ---------------------------------------------
		if($this->opt_show_relative_categories) {
			$category["a_rel_categories"] = $this->get_relative_categories($category);
		}

		// get category topics (hierarchical view html) ------------------------
		if($category["list_mode"] == 2) {
			$category["topics_toc_html"] = $this->get_category_toc_html($category["id"]);
		} else if($category["list_mode"] == 3) {
			$category["topics_toc_html"] = $this->get_category_toc_no_posts_html($category["id"]);
		}

		// get category subcategories
		if($category['list_mode'] == 2) {
			$category["a_sub_ctgs"] = $this->get_sub_categories($category["id"]);
		}

		// push to memcached ---------------------------------------------------
		if($this->opt_use_memcached) {
			$category["date_cached"] = $this->now('UTC');
			$this->push_to_memcached($this->mc_settings, $category_key, $category);
		}

		// get category topics (page topics - always from database) ------------
		if($category["list_mode"] == 1) {
			$res = $this->get_category_page_topics($category);
			$category["total_topics"] = $res["total_topics"];
			$category["page_topics"] = $res["page_topics"];
		}

		// get category popular topics (always from database) ------------------
		$category["a_popular_topics"] = array();
		if($this->opt_show_popular_topics) {
			$category["a_popular_topics"] = $this->get_category_popular_topics($category);
		}

		return $category;
	}

	/**
	 * Get category record (from database)
	 *
	 * @param string $category_url
	 * @return array|bool
	 */
	public function get_category_properties($category_url) {
		$conn = $this->db_connect($this->db_settings);
		$sql = 'SELECT * FROM categories WHERE url =' . "'" . $conn->real_escape_string($category_url) . "'";
		$rs = $conn->query($sql);
		if($rs === false) {
			echo 'Wrong SQL...' . '<br>' . 'Error: ' . $conn->errno . ' ' . $conn->error;
			exit;
		} else {
			if($rs->num_rows == 1) {
				$rs->data_seek(0);
				$category = $rs->fetch_array(MYSQLI_ASSOC);
				$this->data_origin = 1;
				$rs->free();
			} else {
				$rs->free();
				return false;
			}
		}

		return $category;
	}

	/**
	 * Get relative categories
	 *
	 * @param array $category
	 * @return array
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
				echo 'Wrong SQL...' . '<br>' . 'Error: ' . $conn->errno . ' ' . $conn->error;
				exit;
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
				echo 'Wrong SQL...' . '<br>' . 'Error: ' . $conn->errno . ' ' . $conn->error;
				exit;
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
	 * @param array $category
	 * @return array
	 */
	private function get_category_page_topics($category) {
		$a_category_page_topics = array(
			"extra_columns_topics" => array("date_published", "impressions", "comments"),
			"extra_columns_content" => array("ctg_image", "ctg_intro"),
			"publish_status" => TOPIC_STATUS_PUBLISHED,
			"date_from" => null,
			"date_until" => null,
			"with_content_type" => ($category["ctg_type"] == 1 ? $category["id"] : null),
			"with_topic_type" => ($category["ctg_type"] == 2 ? $category["id"] : null),
			"with_topic_type_in" => null,
			"topic_type_column" => (in_array($category["id"], array(1, 4)) ? 'topic_top_type_id' : 'topic_type_id'),
			"with_tag" => null,
			"by_author" => null,
			"order_by" => "date_published",
			"sort_order" => "DESC",
			"offset" => $this->offset,
			"rows_to_return" => $this->max_topics_per_page,
			"memcached_key" => null,
			"count_only" => true
		);
		$total_topics = $this->get_topics_list($this->db_settings, $this->mc_settings, $a_category_page_topics);

		$page_topics = array();
		if($total_topics > 0) {
			$a_category_page_topics["count_only"] = false;
			$page_topics = $this->get_topics_list($this->db_settings, $this->mc_settings, $a_category_page_topics);
		}

		return array("total_topics" => $total_topics, "page_topics" => $page_topics);

	}

	/**
	 * Get category contents in hierarchical view (as html)
	 *
	 * @param int $parent_id the category id
	 * @return string
	 */
	public function get_category_toc_html($parent_id) {

		static $html = '';
		static $ctg_id;
		$conn = $this->db_connect($this->db_settings);

		$sql = 'SELECT id, category FROM categories WHERE parent_b_id=' . $parent_id . ' ORDER BY display_order_b';

		$rs = $conn->query($sql);
		if($rs === false) {
			echo 'Wrong SQL...' . '<br>' . 'Error: ' . $conn->errno . ' ' . $conn->error;
			exit;
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
					echo 'Wrong SQL...' . '<br>' . 'Error: ' . $conn->errno . ' ' . $conn->error;
					exit;
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
	 * @return string
	 */
	public function get_category_toc_no_posts_html($parent_id) {

		static $html = '';
		$conn = $this->db_connect($this->db_settings);
		$sql = 'SELECT id, category, url FROM categories WHERE parent_b_id=' . $parent_id . ' AND url is not null ORDER BY display_order_b';
		$rs = $conn->query($sql);
		if($rs === false) {
			echo 'Wrong SQL...' . '<br>' . 'Error: ' . $conn->errno . ' ' . $conn->error;
			exit;
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
	 * @param $category
	 * @return array
	 */
	public function get_category_popular_topics($category) {

		if($category['list_mode'] == 3) {
			return array();
		}

		$a_category_popular_topics = array(
			"extra_columns_topics" => array("impressions"),
			"extra_columns_content" => null,
			"publish_status" => TOPIC_STATUS_PUBLISHED,
			"date_from" => null,
			"date_until" => null,
			"with_content_type" => ($category["ctg_type"] == 1 ? $category["id"] : null),
			"with_topic_type" => ($category["ctg_type"] == 2 ? $category["id"] : null),
			"with_topic_type_in" => null,
			"topic_type_column" => (in_array($category["id"], array(1, 4)) ? 'topic_top_type_id' : 'topic_type_id'),
			"with_tag" => null,
			"by_author" => null,
			"order_by" => "impressions",
			"sort_order" => "DESC",
			"offset" => 0,
			"rows_to_return" => $this->max_popular,
			"memcached_key" => null,
			"count_only" => false
		);

		if($category['list_mode'] == 2) {
			$a_sub_ctgs = $category["a_sub_ctgs"];
			if(count($a_sub_ctgs) == 0) {
				return array();
			} else {
				$a_category_popular_topics["with_topic_type"] = null;
				$a_category_popular_topics["with_topic_type_in"] = $category["ctg_type"] == 2 ? '(' . implode(',', $a_sub_ctgs) . ')' : null;
			}
		}

		return $this->get_topics_list($this->db_settings, $this->mc_settings, $a_category_popular_topics);
	}

	/**
	 * Get subcategories of a category using recursion
	 *
	 * @param $category_id
	 * @return mixed
	 */
	public function get_sub_categories($category_id) {

		$conn = $this->db_connect($this->db_settings);
		static $children = array();

		$sql = 'SELECT id FROM categories WHERE parent_b_id=' . $category_id . ' AND url is null ORDER BY display_order_b';

		$rs = $conn->query($sql);
		if($rs === false) {
			echo 'Wrong SQL...' . '<br>' . 'Error: ' . $conn->errno . ' ' . $conn->error;
			exit;
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

		$conn = $this->db_connect($this->db_settings);
		if($conn) {
			$conn->close();
		}
	}

}