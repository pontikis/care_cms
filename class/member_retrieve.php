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

	/** @var bool Option to increase profile view (true) or not (false) */
	private $opt_increase_profile_views;

	/** @var bool Option opt_show_member_bookmarks (true) or not (false) */
	private $opt_show_member_bookmarks;

	/** @var bool Option opt_show_member_recent_posts (true) or not (false) */
	private $opt_show_member_recent_posts;

	/** @var int Option opt_popular_by_author_expiration (0 never or number of seconds) */
	private $opt_popular_by_author_expiration;

	/** @var string Data origin ('undefined', 'memcached', 'database') */
	private $data_origin;

	/**
	 * Constructor
	 *
	 * @param array $a_db database settings
	 * @param array $a_mc memcached settings
	 * @param string $member_url member url
	 * @param int $offset offset to start topics display
	 * @param int $max_topics_per_page max topics per page
	 * @param int $max_member_bookmarks max member bookmarks
	 * @param int $max_author_recent_posts max author recent posts
	 * @param int $max_author_popular_posts max author popular posts
	 */
	public function __construct($a_db, $a_mc, $member_url, $offset, $max_topics_per_page, $max_member_bookmarks, $max_author_recent_posts, $max_author_popular_posts) {
		// initialize
		global $care_conf;

		$this->db_settings = $a_db;
		$this->mc_settings = $a_mc;
		$this->member_url = $member_url;
		$this->offset = $offset;
		$this->max_topics_per_page = $max_topics_per_page;
		$this->max_member_bookmarks = $max_member_bookmarks;
		$this->max_author_recent_posts = $max_author_recent_posts;
		$this->max_author_popular_posts = $max_author_popular_posts;

		$this->opt_use_memcached = $a_mc["use_memcached"];
		$this->opt_increase_profile_views = $care_conf['opt_log_profile_views'];
		$this->opt_show_member_bookmarks = $care_conf['opt_show_member_bookmarks'];
		$this->opt_show_member_recent_posts = $care_conf['opt_show_member_recent_posts'];
		$this->opt_popular_by_author_expiration = $care_conf['opt_popular_by_author_expiration'];
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
	 * Set opt_increase_profile_views
	 *
	 * @param bool $flag opt_increase_profile_views
	 */
	public function set_opt_increase_profile_views($flag) {
		$this->opt_increase_profile_views = $flag;
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
	 * Set opt_popular_by_author_expiration
	 *
	 * @param int number of seconds until expiration (0  means never)
	 */
	public function set_opt_popular_by_author_expiration($exp) {
		$this->opt_popular_by_author_expiration = $exp;
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
	 * Get member
	 *
	 * @return array|bool member data or false (not existed or not active member)
	 */
	public function get_member() {

		// get member properties ------------------------------------------------
		$member = $this->get_member_properties();
		if(!$member) {
			return false;
		}

		// increase profile views ----------------------------------------------
		if($this->opt_increase_profile_views) {
			// increase profile views to display on member page
			$member["profile_views"] = $member["profile_views"] + 1;
			// increase profile views in database
			$this->increase_profile_views();
			// increase profile views in memcached
			if($this->opt_use_memcached) {
				$member_key = 'care_member_' . sha1($this->member_url);
				$this->push_to_memcached($this->mc_settings, $member_key, $member);
			}
		}

		// get topics count
		$member['total_topics'] = $this->get_member_topics_count($member['id']);
		// get bookmarks count
		$member['a_total_bookmarks'] = $this->get_member_bookmarks_count($member['id']);
		// get total comment count
		$member['total_comments'] = $this->get_member_comments_count($member['id']);

		// get member recent bookmarks
		$member['a_recent_bookmarks'] = array();
		if($this->opt_show_member_bookmarks) {
			$member['a_recent_bookmarks'] = $this->get_member_recent_bookmarks($member['id']);
		}

		// get member recent topics
		$member['a_recent_topics'] = array();
		if($this->opt_show_member_recent_posts) {
			$member['a_recent_topics'] = $this->get_member_recent_topics($member['id'], $this->max_author_recent_posts);
		}

		return $member;
	}


	/**
	 * Get member properties
	 *
	 * @return array|bool member record data or false  (not existed or not active member)
	 */
	public function get_member_properties() {

		$member_key = 'care_member_' . sha1($this->member_url);

		// pull from memcached
		if($this->opt_use_memcached) {
			$member = $this->pull_from_memcached($this->mc_settings, $member_key);
			if($member !== false) {
				$this->data_origin = 'memcached';
				return $member;
			}
		}

		$conn = $this->db_connect($this->db_settings);
		$sql = 'SELECT * FROM users WHERE user_status_id=' . USER_ACTIVE . ' AND username = ?';

		/* Prepare statement */
		$stmt = $conn->prepare($sql);
		if($stmt === false) {
			$user_error = 'Wrong SQL: ' . $sql . '<br>' . 'Error: ' . $conn->errno . ' ' . $conn->error;
			trigger_error($user_error, E_USER_ERROR);
		}
		/* Bind parameters. Types: s = string, i = integer, d = double,  b = blob */
		$stmt->bind_param('s', $this->member_url);
		/* Execute statement */
		$stmt->execute();
		/* get result */
		$res = $stmt->get_result();
		$rs = $res->fetch_all(MYSQLI_ASSOC);
		if(count($rs) == 1) {
			$member = $rs[0];
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


		$member_fullname = $member['username'];
		if(mb_strlen($member['lastname']) > 0) {
			$member_fullname = '';
			if($member['salutation']) {
				$member_fullname = $member['salutation'] . ' ';
			}
			$member_fullname .= $member['firstname'] . ' ' . $member['lastname'];
		}
		$member['fullname'] = $member_fullname;


		if($member['photo']) {
			$photo_url = USER_PHOTO_URL . $member['photo'];
		} else {
			if($member['gravatar_email']) {
				$photo_url = $this->get_gravatar($member['gravatar_email'], 420);
			} else {
				if($member['gender'] == GENDER_MALE) {
					$photo_url = USER_PHOTO_URL . '/user_male.png';
				} else if($member['gender'] == GENDER_FEMALE) {
					$photo_url = USER_PHOTO_URL . '/user_female.png';
				} else {
					$photo_url = USER_PHOTO_URL . '/user.png';
				}
			}
		}
		$member['photo_url'] = $photo_url;

		// push to memcached
		if($this->opt_use_memcached) {
			$member["date_cached"] = $this->now('UTC');
			$this->push_to_memcached($this->mc_settings, $member_key, $member);
		}

		return $member;

	}

	/**
	 * Increase profile views
	 */
	public function increase_profile_views() {

		$conn = $this->db_connect($this->db_settings);
		$sql = 'UPDATE users SET profile_views = profile_views + 1 WHERE username = ?';

		/* Prepare statement */
		$stmt = $conn->prepare($sql);
		if($stmt === false) {
			$user_error = 'Wrong SQL: ' . $sql . '<br>' . 'Error: ' . $conn->errno . ' ' . $conn->error;
			trigger_error($user_error, E_USER_ERROR);
		}

		/* Bind parameters. TYpes: s = string, i = integer, d = double,  b = blob */
		$stmt->bind_param('s', $this->member_url);

		/* Execute statement */
		$stmt->execute();

		if($stmt->affected_rows != 1) {
			$user_error = 'Database error: Username not unique. ' . $sql;
			trigger_error($user_error, E_USER_ERROR);
		}
		/* close statement */
		$stmt->close();
	}


	/**
	 * Get member topics count (except news hellas and news world)
	 *
	 * @param $member_id
	 * @return int
	 */
	public function get_member_topics_count($member_id) {

		$member_topics_count_key = 'care_member_topics_count_' . $member_id;

		// pull from memcached
		if($this->opt_use_memcached) {
			$total_topics = $this->pull_from_memcached($this->mc_settings, $member_topics_count_key);
			if($total_topics !== false) {
				return $total_topics;
			}
		}

		$a_member_topics_count_criteria = array(
			"publish_status" => TOPIC_STATUS_PUBLISHED,
			"by_author" => $member_id,
			"exclude_news" => true,
			"memcached_key" => $member_topics_count_key,
			"count_only" => true
		);
		$total_topics = $this->get_topics_list($this->db_settings, $this->mc_settings, $a_member_topics_count_criteria);

		// push to memcached
		if($this->opt_use_memcached) {
			$this->push_to_memcached($this->mc_settings, $member_topics_count_key, $total_topics);
		}

		return $total_topics;
	}


	/**
	 * Get member bookmarks count
	 *
	 * @param int $member_id member id
	 * @return array ('all' => count_bookmarks_all, 'public' => count_bookmarks_public)
	 */
	public function get_member_bookmarks_count($member_id) {

		$member_bookmarks_count_key = 'care_member_bookmarks_count_' . $member_id;

		// pull from memcached
		if($this->opt_use_memcached) {
			$a_total_bookmarks = $this->pull_from_memcached($this->mc_settings, $member_bookmarks_count_key);
			if($a_total_bookmarks !== false) {
				return $a_total_bookmarks;
			}
		}

		$conn = $this->db_connect($this->db_settings);

		for($i = 0; $i < 2; $i++) {

			$sql = 'SELECT count(id) as total_bookmarks FROM bookmarks WHERE users_id = ?';
			if($i == 1) {
				$sql .= ' AND is_public=1';
			}

			/* Prepare statement */
			$stmt = $conn->prepare($sql);
			if($stmt === false) {
				$user_error = 'Wrong SQL: ' . $sql . '<br>' . 'Error: ' . $conn->errno . ' ' . $conn->error;
				trigger_error($user_error, E_USER_ERROR);
			}
			/* Bind parameters. Types: s = string, i = integer, d = double,  b = blob */
			$stmt->bind_param('i', $member_id);
			/* Execute statement */
			$stmt->execute();
			/* get result */
			$res = $stmt->get_result();
			$rs = $res->fetch_all(MYSQLI_ASSOC);

			$total_bookmarks = $rs[0]['total_bookmarks'];

			/* free result */
			$stmt->free_result();

			/* close statement */
			$stmt->close();

			if($i == 0) {
				$a_total_bookmarks['all'] = $total_bookmarks;
			}
			if($i == 1) {
				$a_total_bookmarks['public'] = $total_bookmarks;
			}
		}

		// push to memcached
		if($this->opt_use_memcached) {
			$this->push_to_memcached($this->mc_settings, $member_bookmarks_count_key, $a_total_bookmarks);
		}

		return $a_total_bookmarks;

	}

	/**
	 * Get member comments count
	 *
	 * @param int $member_id
	 * @return int
	 */
	public function get_member_comments_count($member_id) {

		$member_comments_count_key = 'care_member_comments_count_' . $member_id;

		// pull from memcached
		if($this->opt_use_memcached) {
			$total_comments = $this->pull_from_memcached($this->mc_settings, $member_comments_count_key);
			if($total_comments !== false) {
				return $total_comments;
			}
		}

		$conn = $this->db_connect($this->db_settings);
		$sql = 'SELECT count(id) as total_comments FROM comments WHERE users_id = ?';

		/* Prepare statement */
		$stmt = $conn->prepare($sql);
		if($stmt === false) {
			$user_error = 'Wrong SQL: ' . $sql . '<br>' . 'Error: ' . $conn->errno . ' ' . $conn->error;
			trigger_error($user_error, E_USER_ERROR);
		}
		/* Bind parameters. Types: s = string, i = integer, d = double,  b = blob */
		$stmt->bind_param('i', $member_id);
		/* Execute statement */
		$stmt->execute();
		/* get result */
		$res = $stmt->get_result();
		$rs = $res->fetch_all(MYSQLI_ASSOC);

		$total_comments = $rs[0]['total_comments'];

		/* free result */
		$stmt->free_result();

		/* close statement */
		$stmt->close();

		// push to memcached
		if($this->opt_use_memcached) {
			$this->push_to_memcached($this->mc_settings, $member_comments_count_key, $total_comments);
		}

		return $total_comments;
	}

	/**
	 * Get member recent bookmarks
	 *
	 * @param int $member_id
	 * @return array
	 */
	public function get_member_recent_bookmarks($member_id) {

		$member_recent_bookmarks_key = 'care_member_recent_bookmarks_' . $member_id;

		// pull from memcached
		if($this->opt_use_memcached) {
			$a_recent_bookmarks = $this->pull_from_memcached($this->mc_settings, $member_recent_bookmarks_key);
			if($a_recent_bookmarks !== false) {
				return $a_recent_bookmarks;
			}
		}

		$conn = $this->db_connect($this->db_settings);

		for($i = 0; $i < 2; $i++) {
			$sql = 'SELECT t.id,t.url,t.title ' .
				'FROM bookmarks b INNER JOIN topics t ON (b.topics_id = t.id) ' .
				'WHERE b.users_id = ? ';
			if($i == 1) {
				$sql .= 'AND b.is_public=1 ';
			}
			$sql .= 'ORDER BY b.date_inserted DESC ' .
				'LIMIT 0,' . $this->max_member_bookmarks;

			/* Prepare statement */
			$stmt = $conn->prepare($sql);
			if($stmt === false) {
				$user_error = 'Wrong SQL: ' . $sql . '<br>' . 'Error: ' . $conn->errno . ' ' . $conn->error;
				trigger_error($user_error, E_USER_ERROR);
			}
			/* Bind parameters. Types: s = string, i = integer, d = double,  b = blob */
			$stmt->bind_param('i', $member_id);
			/* Execute statement */
			$stmt->execute();
			/* get result */
			$res = $stmt->get_result();
			$a_bookmarks = $res->fetch_all(MYSQLI_ASSOC);
			/* free result */
			$stmt->free_result();

			/* close statement */
			$stmt->close();

			$e = 0;
			foreach($a_bookmarks as $a_bookmark) {
				$a_bookmarks[$e]['url'] = 'http://' . $_SERVER['SERVER_NAME'] . '/post/' . $a_bookmark['id'] . '/' . $a_bookmark['url'];
				$e++;
			}

			if($i == 0) {
				$a_recent_bookmarks['all'] = $a_bookmarks;
			}
			if($i == 1) {
				$a_recent_bookmarks['public'] = $a_bookmarks;
			}

		}

		// push to memcached
		if($this->opt_use_memcached) {
			$this->push_to_memcached($this->mc_settings, $member_recent_bookmarks_key, $a_recent_bookmarks);
		}

		return $a_recent_bookmarks;

	}

	/**
	 * Get member recent topics (except news hellas and news world)
	 *
	 * @param int $member_id member id
	 * @param int $max_recent max topics to display
	 * @return array topics array (url, title)
	 */
	public function get_member_recent_topics($member_id, $max_recent) {

		$member_recent_topics_key = 'care_member_recent_topics_' . $member_id;

		// pull from memcached
		if($this->opt_use_memcached) {
			$a_recent_topics = $this->pull_from_memcached($this->mc_settings, $member_recent_topics_key);
			if($a_recent_topics !== false) {
				return $a_recent_topics;
			}
		}

		$a_member_recent_topics_criteria = array(
			"publish_status" => TOPIC_STATUS_PUBLISHED,
			"order_by" => "date_published",
			"sort_order" => "DESC",
			"by_author" => $member_id,
			"exclude_news" => true,
			"offset" => $this->offset,
			"rows_to_return" => $this->max_topics_per_page,
			"memcached_key" => $member_recent_topics_key,
			"count_only" => false
		);
		$a_recent_topics = $this->get_topics_list($this->db_settings, $this->mc_settings, $a_member_recent_topics_criteria);

		// push to memcached
		if($this->opt_use_memcached) {
			$this->push_to_memcached($this->mc_settings, $member_recent_topics_key, $a_recent_topics);
		}

		return $a_recent_topics;

	}

	/**
	 * Get member page topics (except news hellas and news world)
	 *
	 * @param int $member_id member id
	 * @return array topics array (url, title, date_published, impressions, comments, ctg_intro, ctg_image)
	 */
	public function get_member_page_topics($member_id) {

		$a_page_topics_by_author_criteria = array(
			"extra_columns_topics" => array("date_published", "impressions", "comments"),
			"extra_columns_content" => array("ctg_image", "ctg_intro"),
			"publish_status" => TOPIC_STATUS_PUBLISHED,
			"by_author" => $member_id,
			"exclude_news" => true,
			"order_by" => "date_published",
			"sort_order" => "DESC",
			"offset" => $this->offset,
			"rows_to_return" => $this->max_topics_per_page,
			"count_only" => false
		);
		return $this->get_topics_list($this->db_settings, null, $a_page_topics_by_author_criteria);

	}

	/**
	 * Get popular topics by author, excluding news (use memcached with expiration)
	 *
	 * @param int $member_id member id
	 * @return array (url, title, imnpressions)
	 */
	public function get_member_popular_topics($member_id) {

		$popular_by_author_key = 'care_popular_by_author_' . $member_id;

		$a_popular_topics_by_author_criteria = array(
			"extra_columns_topics" => array("impressions"),
			"publish_status" => TOPIC_STATUS_PUBLISHED,
			"by_author" => $member_id,
			"exclude_news" => true,
			"order_by" => "impressions",
			"sort_order" => "DESC",
			"offset" => 0,
			"rows_to_return" => $this->max_author_popular_posts,
			"memcached_key" => $popular_by_author_key,
			"expiration" => $this->opt_popular_by_author_expiration
		);

		return $this->get_topics_list($this->db_settings, $this->mc_settings, $a_popular_topics_by_author_criteria);
	}


	/**
	 * Get member page bookmarks
	 *
	 * @param int $member_id
	 * @param bool $is_public
	 * @return array (id, url, title, date_published, comments, ctg_intro, ctg_image)
	 */
	public function get_member_page_bookmarks($member_id, $is_public = true) {

		$conn = $this->db_connect($this->db_settings);
		$sql = 'SELECT t.id,t.url,t.title,t.date_published,t.impressions,c.ctg_intro,c.ctg_image ' .
			'FROM bookmarks b INNER JOIN topics t ON (b.topics_id = t.id) ' .
			'INNER JOIN content c ON (t.content_id = c.id) ' .
			'WHERE b.users_id = ? ';
		if($is_public) {
			$sql .= 'AND b.is_public=1 ';
		}
		$sql .= 'ORDER BY b.date_inserted DESC ' .
			'LIMIT ' . $this->offset . ',' . $this->max_topics_per_page;

		/* Prepare statement */
		$stmt = $conn->prepare($sql);
		if($stmt === false) {
			$user_error = 'Wrong SQL: ' . $sql . '<br>' . 'Error: ' . $conn->errno . ' ' . $conn->error;
			trigger_error($user_error, E_USER_ERROR);
		}
		/* Bind parameters. Types: s = string, i = integer, d = double,  b = blob */
		$stmt->bind_param('i', $member_id);
		/* Execute statement */
		$stmt->execute();
		/* get result */
		$res = $stmt->get_result();
		$page_bookmarks = $res->fetch_all(MYSQLI_ASSOC);
		/* free result */
		$stmt->free_result();

		/* close statement */
		$stmt->close();

		$i = 0;
		foreach($page_bookmarks as $page_bookmark) {
			$page_bookmarks[$i]['url'] = 'http://' . $_SERVER['SERVER_NAME'] . '/post/' . $page_bookmark['id'] . '/' . $page_bookmark['url'];
			$i++;
		}
		return $page_bookmarks;
	}

	/**
	 * Destructor
	 */
	public function __destruct() {
		$this->db_disconnect();
	}

}