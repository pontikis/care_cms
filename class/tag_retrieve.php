<?php
/**
 * tag_retrieve php class (display tag operations)
 *
 * @author     Christos Pontikis http://pontikis.net
 * @copyright  Christos Pontikis
 * @license    MIT http://opensource.org/licenses/MIT
 * @version    0.1.0 (30 May 2013)
 *
 */
class tag_retrieve extends cms_common {

	/** @var bool Option to use memcached (true) or not (false) */
	private $opt_use_memcached;

	/** @var bool Option opt_show_popular_topics (true) or not (false) */
	private $opt_show_popular_topics;

	/** @var int Option opt_popular_with_tag_expiration (0 never or number of seconds) */
	private $opt_popular_with_tag_expiration;

	/**
	 * Constructor
	 *
	 * @param array $a_db database settings
	 * @param array $a_mc memcached settings
	 * @param string $tag_url the tag url
	 * @param int $offset offset to start topics display
	 * @param int $max_topics_per_page max topics per page
	 * @param int $max_popular max popular topics with tag
	 */
	public function __construct($a_db, $a_mc, $tag_url, $offset, $max_topics_per_page, $max_popular) {
		// initialize
		global $care_conf;
		$this->db_settings = $a_db;
		$this->mc_settings = $a_mc;
		$this->tag_url = $tag_url;
		$this->offset = $offset;
		$this->max_topics_per_page = $max_topics_per_page;
		$this->max_popular = $max_popular;

		$this->opt_use_memcached = $a_mc["use_memcached"];
		$this->opt_show_popular_topics = $care_conf['opt_show_popular_topics_with_tag'];
		$this->opt_popular_with_tag_expiration = $care_conf['opt_popular_with_tag_expiration'];
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
	 * Set opt_show_popular_topics
	 *
	 * @param bool $flag opt_show_popular_topics
	 */
	public function set_opt_show_popular_topics($flag) {
		$this->opt_show_popular_topics = $flag;
	}

	/**
	 * Set opt_popular_with_tag_expiration
	 *
	 * @param int number of seconds until expiration (0  means never)
	 */
	public function set_opt_popular_with_tag_expiration($exp) {
		$this->opt_popular_with_tag_expiration = $exp;
	}

	/**
	 * Get tag
	 *
	 * @return array tag data
	 */
	public function get_tag() {

		$tag = false;

		// get topics count with tag -------------------------------------------
		$tag["total_topics"] = $this->get_tag_topics_count();

		// get page topics with tag (always from database) ---------------------
		$tag["page_topics"] = array();
		if($tag["total_topics"] > 0) {
			$tag["page_topics"] = $this->get_tag_page_topics();
		}

		// get popular topics with tag (use memcached with expiration) ---------
		$tag["a_popular_topics"] = array();
		if($this->opt_show_popular_topics) {
			if($tag["total_topics"] > $this->max_topics_per_page) {
				$tag["a_popular_topics"] = $this->get_tag_popular_topics();
			}
		}

		return $tag;
	}

	/**
	 * Get tag topics count
	 *
	 * @return int
	 */
	private function get_tag_topics_count() {

		$tag_topics_count_key = 'care_tag_topics_count_' . sha1($this->tag_url);

		// pull from memcached
		if($this->opt_use_memcached) {
			$total_topics = $this->pull_from_memcached($this->mc_settings, $tag_topics_count_key);
			if($total_topics !== false) {
				return $total_topics;
			}
		}

		$a_tag_topics_count_criteria = array(
			"publish_status" => TOPIC_STATUS_PUBLISHED,
			"with_tag" => $this->tag_url,
			"count_only" => true
		);

		if($this->opt_use_memcached) {
			$a_tag_topics_count_criteria['memcached_key'] = $tag_topics_count_key;
			$total_topics = $this->get_topics_list($this->db_settings, $this->mc_settings, $a_tag_topics_count_criteria);
		} else {
			$total_topics = $this->get_topics_list($this->db_settings, null, $a_tag_topics_count_criteria);
		}


		// push to memcached
		if($this->opt_use_memcached) {
			$this->push_to_memcached($this->mc_settings, $tag_topics_count_key, $total_topics);
		}

		return $total_topics;
	}

	/**
	 * Get tag page topics (always from database)
	 *
	 * @return array
	 */
	private function get_tag_page_topics() {
		$a_page_topics_with_tag_criteria = array(
			"extra_columns_topics" => array("date_published", "impressions", "comments"),
			"extra_columns_content" => array("ctg_image", "ctg_intro"),
			"publish_status" => TOPIC_STATUS_PUBLISHED,
			"with_tag" => $this->tag_url,
			"order_by" => "date_published",
			"sort_order" => "DESC",
			"offset" => $this->offset,
			"rows_to_return" => $this->max_topics_per_page,
			"count_only" => false
		);
		return $this->get_topics_list($this->db_settings, null, $a_page_topics_with_tag_criteria);
	}

	/**
	 * Get popular topics with tag (use memcached with expiration)
	 *
	 * @return array
	 */
	public function get_tag_popular_topics() {

		$popular_with_tag_key = 'care_popular_with_tag_' . sha1($this->tag_url);

		$a_popular_topics_with_tag_criteria = array(
			"extra_columns_topics" => array("impressions"),
			"publish_status" => TOPIC_STATUS_PUBLISHED,
			"with_tag" => $this->tag_url,
			"order_by" => "impressions",
			"sort_order" => "DESC",
			"offset" => 0,
			"rows_to_return" => $this->max_popular,
			"count_only" => false
		);

		if($this->opt_use_memcached) {
			$a_popular_topics_with_tag_criteria['memcached_key'] = $popular_with_tag_key;
			$a_popular_topics_with_tag_criteria['expiration'] = $this->opt_popular_with_tag_expiration;
		}

		return $this->get_topics_list($this->db_settings, $this->mc_settings, $a_popular_topics_with_tag_criteria);
	}

	/**
	 * Destructor
	 */
	public function __destruct() {
		$this->db_disconnect();
	}

}