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

	/** @var bool Option opt_show_popular_topics (true) or not (false) */
	private $opt_show_popular_topics;

	/**
	 * Constructor
	 *
	 * @param array $a_db database settings
	 * @param string $tag_url the tag url
	 * @param int $offset offset to start topics display
	 * @param int $rows_to_return topics per page
	 * @param int $max_popular max popular topics with tag
	 */
	public function __construct($a_db, $tag_url, $offset, $rows_to_return, $max_popular) {
		// initialize
		$this->db_settings = $a_db;
		$this->tag_url = $tag_url;
		$this->offset = $offset;
		$this->rows_to_return = $rows_to_return;
		$this->max_popular = $max_popular;

		$this->opt_show_popular_topics = OPT_SHOW_POPULAR_TOPICS_PER_CATEGORY;
	}

	/**
	 * Set opt_show_popular_topics
	 *
	 * @param bool $flag opt_show_popular_topics
	 */
	public function opt_show_popular_topics($flag) {
		$this->opt_show_popular_topics = $flag;
	}

	/**
	 * Get topic from topic id
	 *
	 * @return array|bool post data or false
	 */
	public function get_tag() {

		$tag = false;

		// get topics with tag (page topics - always from database) ------------
		$res = $this->get_tag_page_topics();
		$tag["total_topics"] = $res["total_topics"];
		$tag["page_topics"] = $res["page_topics"];

		// get category popular topics (always from database) ------------------
		$tag["a_popular_topics"] = array();
		if($this->opt_show_popular_topics) {
			if($tag["total_topics"] > $this->rows_to_return) {
				$tag["a_popular_topics"] = $this->get_tag_popular_topics($this->tag);
			}
		}

		return $tag;
	}


	/**
	 * Get tag page topics (always from database)
	 *
	 * @return array
	 */
	private function get_tag_page_topics() {
		$a_page_topics_with_tag = array(
			"extra_columns_topics" => array("date_published", "impressions", "comments"),
			"extra_columns_content" => array("ctg_image", "ctg_intro"),
			"publish_status" => TOPIC_STATUS_PUBLISHED,
			"date_from" => null,
			"date_until" => null,
			"with_content_type" => null,
			"with_topic_type" => null,
			"with_topic_type_in" => null,
			"topic_type_column" => null,
			"with_tag" => $this->tag_url,
			"by_author" => null,
			"order_by" => "date_published",
			"sort_order" => "DESC",
			"offset" => $this->offset,
			"rows_to_return" => $this->rows_to_return,
			"memcached_key" => false,
			"count_only" => true
		);
		$total_topics = $this->get_topics_list($this->db_settings, null, $a_page_topics_with_tag);

		$page_topics = array();
		if($total_topics > 0) {
			$a_page_topics_with_tag["count_only"] = false;
			$page_topics = $this->get_topics_list($this->db_settings, null, $a_page_topics_with_tag);
		}

		return array("total_topics" => $total_topics, "page_topics" => $page_topics);

	}

	/**
	 * Get popular topics with tag (always from database)
	 *
	 * @return array
	 */
	public function get_tag_popular_topics() {

		$a_popular_topics_with_tag = array(
			"extra_columns_topics" => array("impressions"),
			"extra_columns_content" => array(),
			"publish_status" => TOPIC_STATUS_PUBLISHED,
			"date_from" => null,
			"date_until" => null,
			"with_content_type" => null,
			"with_topic_type" => null,
			"with_topic_type_in" => null,
			"topic_type_column" => null,
			"with_tag" => $this->tag_url,
			"by_author" => null,
			"order_by" => "impressions",
			"sort_order" => "DESC",
			"offset" => 0,
			"rows_to_return" => 10,
			"memcached_key" => false,
			"count_only" => false
		);

		return $this->get_topics_list($this->db_settings, null, $a_popular_topics_with_tag);
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