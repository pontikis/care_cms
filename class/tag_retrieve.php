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
	 * @param int $max_topics_per_page max topics per page
	 * @param int $max_popular max popular topics with tag
	 */
	public function __construct($a_db, $tag_url, $offset, $max_topics_per_page, $max_popular) {
		// initialize
		global $care_conf;
		$this->db_settings = $a_db;
		$this->tag_url = $tag_url;
		$this->offset = $offset;
		$this->max_topics_per_page = $max_topics_per_page;
		$this->max_popular = $max_popular;

		$this->opt_show_popular_topics = $care_conf['opt_show_popular_topics_with_tag'];
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
	 * Get tag
	 *
	 * @return array tag data
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
			if($tag["total_topics"] > $this->max_topics_per_page) {
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
		$a_page_topics_with_tag_criteria = array(
			"extra_columns_topics" => array("date_published", "impressions", "comments"),
			"extra_columns_content" => array("ctg_image", "ctg_intro"),
			"publish_status" => TOPIC_STATUS_PUBLISHED,
			"with_tag" => $this->tag_url,
			"order_by" => "date_published",
			"sort_order" => "DESC",
			"offset" => $this->offset,
			"rows_to_return" => $this->max_topics_per_page,
			"count_only" => true
		);
		$total_topics = $this->get_topics_list($this->db_settings, null, $a_page_topics_with_tag_criteria);

		$page_topics = array();
		if($total_topics > 0) {
			$a_page_topics_with_tag_criteria["count_only"] = false;
			$page_topics = $this->get_topics_list($this->db_settings, null, $a_page_topics_with_tag_criteria);
		}

		return array("total_topics" => $total_topics, "page_topics" => $page_topics);

	}

	/**
	 * Get popular topics with tag (always from database)
	 *
	 * @return array
	 */
	public function get_tag_popular_topics() {

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

		return $this->get_topics_list($this->db_settings, null, $a_popular_topics_with_tag_criteria);
	}

	/**
	 * Destructor
	 */
	public function __destruct() {
		$this->db_disconnect();
	}

}