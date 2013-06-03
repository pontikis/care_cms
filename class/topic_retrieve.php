<?php
/**
 * topic_display php class (display topic operations)
 *
 * @author     Christos Pontikis http://pontikis.net
 * @copyright  Christos Pontikis
 * @license    MIT http://opensource.org/licenses/MIT
 * @version    0.1.0 (25 May 2013)
 *
 */
class topic_retrieve extends cms_common {

	/** @var bool Option to use memcached (true) or not (false) */
	private $opt_use_memcached;

	/** @var bool Option to increase impressions (true) or not (false) */
	private $opt_increase_impressions;

	/** @var int Data origin (0 = memcached, 1 = database) */
	private $data_origin;

	/**
	 * Constructor
	 *
	 * @param array $a_db database settings
	 * @param array $a_mc memcached settings
	 * @param int $topic_id topic id
	 * @param int $max_recent_topics max recent topics to display
	 */
	public function __construct($a_db, $a_mc, $topic_id, $max_recent_topics) {
		// initialize
		$this->db_settings = $a_db;
		$this->mc_settings = $a_mc;
		$this->topic_id = $topic_id;
		$this->max_recent_topics = $max_recent_topics;

		$this->opt_use_memcached = $a_mc["use_memcached"];
		$this->opt_increase_impressions = OPT_LOG_TOPIC_IMPRESSIONS;
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
	 * Set opt_increase_impressions
	 *
	 * @param bool $flag opt_increase_impressions value
	 */
	public function set_opt_increase_impressions($flag) {
		$this->opt_increase_impressions = $flag;
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
	public function get_topic() {
		$topic = false;
		$topic_id = $this->topic_id;
		$topic_key = 'care_topic_' . $topic_id;
		$a_recent_topics = array(
			"extra_columns_topics" => null,
			"extra_columns_content" => null,
			"publish_status" => TOPIC_STATUS_PUBLISHED,
			"date_from" => null,
			"date_until" => null,
			"with_content_type" => null,
			"with_topic_type" => null,
			"with_topic_type_in" => null,
			"topic_type_column" => null,
			"with_tag" => null,
			"by_author" => null,
			"order_by" => "date_published",
			"sort_order" => "DESC",
			"offset" => 0,
			"rows_to_return" => $this->max_recent_topics,
			"memcached_key" => "care_recent_topics",
			"count_only" => false
		);

		// pull from memcached
		if($this->opt_use_memcached) {
			$topic_cached = $this->pull_from_memcached($this->mc_settings, $topic_key);
			if($topic_cached) {
				$this->data_origin = 0;
				if($this->opt_increase_impressions) {
					$this->increase_impressions();
					$topic_cached["impressions"] = $topic_cached["impressions"] + 1;
					$this->push_to_memcached($this->mc_settings, $topic_key, $topic_cached);
				}

				// get recent topics
				$topic_cached["recent_topics"] = $this->get_topics_list($this->db_settings, $this->mc_settings, $a_recent_topics);

				return $topic_cached;
			}
		}

		// retrieve from database
		$topic = $this->get_topic_properties();
		if(!$topic) {
			return false;
		}

		// create meta keywords from tags
		$str_tags = $topic['tags'];
		$a_tags = array();
		$meta_keywords = '';
		if($str_tags) {
			$str_tags_len = mb_strlen($str_tags);
			$a_tags = explode("|", mb_substr($str_tags, 1, $str_tags_len - 2));
			$meta_keywords = implode(",", $a_tags) . ', ';
		}
		$topic["a_tags"] = $a_tags;
		$topic["meta_keywords"] = $meta_keywords;

		// get author name
		$topic_author = $this->get_topic_author($topic["author_id"]);
		$topic["author_username"] = $topic_author["username"];
		$topic["author_fullname"] = $topic_author["fullname"];

		// get content type
		$content_type = $this->get_topic_category($topic["content_type_id"]);
		$topic["content_type_url"] = $content_type["url"];
		$topic["content_type_title"] = $content_type["title"];

		// get topic type
		if(is_null($topic["topic_type_id"])) {
			$topic_type = $this->get_topic_category($topic["topic_top_type_id"]);
		} else {
			$topic_type = $this->get_topic_ctg_from_subctg($topic["topic_type_id"]);
		}
		$topic["topic_type_url"] = $topic_type["url"];
		$topic["topic_type_title"] = $topic_type["title"];

		// increase impressions
		if($this->opt_increase_impressions) {
			$this->increase_impressions();
			$topic["impressions"] = $topic["impressions"] + 1;
		}

		// push to memcached
		if($this->opt_use_memcached) {
			$topic["date_cached"] = $this->now('UTC');
			$this->push_to_memcached($this->mc_settings, $topic_key, $topic);
		}

		// get recent topics
		$topic["recent_topics"] = $this->get_topics_list($this->db_settings, $this->mc_settings, $a_recent_topics);

		return $topic;
	}

	/**
	 * Get topic record (from database)
	 *
	 * @return array|bool
	 */
	public function get_topic_properties() {

		$conn = $this->db_connect($this->db_settings);
		$sql = 'SELECT * FROM topics t LEFT OUTER JOIN content c ON (t.content_id = c.id) ' .
			'WHERE t.id = ? ' .
			'AND publish_status_id=' . TOPIC_STATUS_PUBLISHED . ' ' .
			'AND date_published IS NOT null ' .
			'AND date_published <= ' . "'" . $this->now('UTC') . "'";


		/* Prepare statement */
		$stmt = $conn->prepare($sql);
		if($stmt === false) {
			$user_error =  'Wrong SQL: ' . $sql . '<br>' . 'Error: ' . $conn->errno . ' ' . $conn->error;
			trigger_error($user_error, E_USER_ERROR);
		}
		/* Bind parameters. Types: s = string, i = integer, d = double,  b = blob */
		$stmt->bind_param('i', $this->topic_id);
		/* Execute statement */
		$stmt->execute();
		/* get result */
		$res = $stmt->get_result();
		$rs = $res->fetch_all(MYSQLI_ASSOC);
		if(count($rs) == 1) {
			$topic=$rs[0];
			$this->data_origin = 1;
			/* free result */
			$stmt->free_result();
		} else {
			/* free result */
			$stmt->free_result();
			return false;
		}
		/* close statement */
		$stmt->close();

		return $topic;
	}

	/**
	 * Get topic author username, fullaname
	 *
	 * @param int $author_id topic author_id
	 * @return array (author id, username, fullaname)
	 */
	public function get_topic_author($author_id) {

		$topic_author_key = 'care_author_' . $author_id;

		// pull from memcached
		if($this->opt_use_memcached) {
			$val = $this->pull_from_memcached($this->mc_settings, $topic_author_key);
			if($val) {
				return $val;
			}
		}

		// retrieve from database
		$conn = $this->db_connect($this->db_settings);
		$sql = 'SELECT username, firstname, lastname FROM users WHERE id=' . $author_id;
		$res_author = $conn->query($sql);
		if($res_author === false) {
			$user_error =  'Wrong SQL: ' . $sql . '<br>' . 'Error: ' . $conn->errno . ' ' . $conn->error;
			trigger_error($user_error, E_USER_ERROR);
		} else {
			$res_author->data_seek(0);
			$author = $res_author->fetch_array(MYSQLI_ASSOC);
			$author_fullname = $author['firstname'] . ' ' . $author['lastname'];
			$topic_author = array("id" => $author_id, "username" => $author['username'], "fullname" => $author_fullname);
			$res_author->free();
		}

		// push to memcached
		if($this->opt_use_memcached) {
			$this->push_to_memcached($this->mc_settings, $topic_author_key, $topic_author);
		}

		return $topic_author;
	}

	/**
	 * Get topic category
	 *
	 * @param int $cat_id category id
	 * @return array (category id, url, title)
	 */
	public function get_topic_category($cat_id) {

		$topic_category_key = 'care_topic_category_' . $cat_id;

		// pull from memcached
		if($this->opt_use_memcached) {
			$val = $this->pull_from_memcached($this->mc_settings, $topic_category_key);
			if($val) {
				return $val;
			}
		}

		// retrieve from database
		$conn = $this->db_connect($this->db_settings);
		$sql = 'SELECT category, url FROM categories WHERE id=' . $cat_id;
		$res_ctg = $conn->query($sql);
		if($res_ctg === false) {
			$user_error =  'Wrong SQL: ' . $sql . '<br>' . 'Error: ' . $conn->errno . ' ' . $conn->error;
			trigger_error($user_error, E_USER_ERROR);
		} else {
			$res_ctg->data_seek(0);
			$cat = $res_ctg->fetch_array(MYSQLI_ASSOC);
			$topic_category_title = mb_strtoupper(removeAccents($cat['category']));
			$topic_category = array("id" => $cat_id, "url" => $cat['url'], "title" => $topic_category_title);
			$res_ctg->free();
		}

		// push to memcached
		if($this->opt_use_memcached) {
			$this->push_to_memcached($this->mc_settings, $topic_category_key, $topic_category);
		}

		return $topic_category;
	}

	/**
	 * Get topic category from subcategory
	 *
	 * @param int $cat_id category id
	 * @return array (category id, url, title)
	 */
	public function get_topic_ctg_from_subctg($cat_id) {

		$topic_category_key = 'care_topic_category_' . $cat_id;

		// pull from memcached
		if($this->opt_use_memcached) {
			$val = $this->pull_from_memcached($this->mc_settings, $topic_category_key);
			if($val) {
				return $val;
			}
		}

		// retrieve from database
		$conn = $this->db_connect($this->db_settings);

		$topic_category_url = null;
		$current_cat_id = $cat_id;
		while(is_null($topic_category_url)) {
			$sql = 'SELECT category, url, parent_b_id FROM categories WHERE id=' . $current_cat_id;
			$res_ctg = $conn->query($sql);
			if($res_ctg === false) {
				$user_error =  'Wrong SQL: ' . $sql . '<br>' . 'Error: ' . $conn->errno . ' ' . $conn->error;
				trigger_error($user_error, E_USER_ERROR);
			} else {
				$res_ctg->data_seek(0);
				$cat = $res_ctg->fetch_array(MYSQLI_ASSOC);
			}
			$current_cat_id = $cat['parent_b_id'];
			$topic_category_url = $cat['url'];
			$topic_category_title = mb_strtoupper(removeAccents($cat['category']));
			$topic_category = array("id" => $cat_id, "url" => $topic_category_url, "title" => $topic_category_title);
		};
		$res_ctg->free();

		// push to memcached
		if($this->opt_use_memcached) {
			$this->push_to_memcached($this->mc_settings, $topic_category_key, $topic_category);
		}

		return $topic_category;
	}

	/**
	 * Increase Topic Impressions
	 */
	public function increase_impressions() {
		$topic_id = $this->topic_id;
		$conn = $this->db_connect($this->db_settings);
		$sql = 'UPDATE topics SET impressions = impressions + 1 WHERE id=' . $topic_id;
		if(!$conn->query($sql)) {
			$user_error =  'Wrong SQL: ' . $sql . '<br>' . 'Error: ' . $conn->errno . ' ' . $conn->error;
			trigger_error($user_error, E_USER_ERROR);
		}
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
