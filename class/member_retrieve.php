<?php
/**
 * member_retrieve php class (display member operations)
 *
 * @author     Christos Pontikis http://pontikis.net
 * @copyright  Christos Pontikis
 * @license    MIT http://opensource.org/licenses/MIT
 * @version    0.1.0 (02 Jun 2013)
 *
 */
class member_retrieve extends cms_common {

	/** @var bool Option to use memcached (true) or not (false) */
	private $opt_use_memcached;

	/** @var bool Option opt_show_member_bookmarks (true) or not (false) */
	private $opt_show_member_bookmarks;

	/** @var bool Option opt_show_member_recent_posts (true) or not (false) */
	private $opt_show_member_recent_posts;

	/** @var int Data origin (0 = memcached, 1 = database) */
	private $data_origin;

	/**
	 * Constructor
	 *
	 * @param array $a_db database settings
	 * @param array $a_mc memcached settings
	 * @param string $member_url member url
	 * @param int $max_member_bookmarks max member bookmarks
	 * @param int $max_author_recent_posts max author recent posts
	 */
	public function __construct($a_db, $a_mc, $member_url, $max_member_bookmarks, $max_author_recent_posts) {
		// initialize
		$this->db_settings = $a_db;
		$this->mc_settings = $a_mc;
		$this->member_url = $member_url;
		$this->max_member_bookmarks = $max_member_bookmarks;
		$this->max_author_recent_posts = $max_author_recent_posts;

		$this->opt_use_memcached = $a_mc["use_memcached"];
		$this->opt_show_member_bookmarks = OPT_SHOW_MEMBER_BOOKMARKS;
		$this->opt_show_member_recent_posts = OPT_SHOW_MEMBER_RECENT_POSTS;
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
	 * Set opt_show_member_bookmarks
	 *
	 * @param bool $flag opt_show_popular_topics
	 */
	public function set_opt_show_member_bookmarks($flag) {
		$this->opt_show_member_bookmarks = $flag;
	}

	/**
	 * Set opt_show_member_recent_posts
	 *
	 * @param bool $flag opt_show_relative_categories
	 */
	public function set_opt_show_member_recent_posts($flag) {
		$this->opt_show_member_recent_posts = $flag;
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
	public function get_member() {

		$member = false;
		$member_key = 'care_member_' . sha1($this->member_url);

		// pull from memcached
		if($this->opt_use_memcached) {
			$category_cached = $this->pull_from_memcached($this->mc_settings, $member_key);
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

		// retrieve member from database -------------------------------------
		$member = $this->get_category_properties($this->category_url);
		if(!$member) {
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

		return $member;
	}

	/**
	 * Get member record (from database)
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
	 * Destructor
	 */
	public function __destruct() {

		$conn = $this->db_connect($this->db_settings);
		if($conn) {
			$conn->close();
		}
	}

}