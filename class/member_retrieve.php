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
	 */
	public function __construct($a_db, $a_mc, $member_url,  $offset, $max_topics_per_page, $max_member_bookmarks, $max_author_recent_posts) {
		// initialize
		$this->db_settings = $a_db;
		$this->mc_settings = $a_mc;
		$this->member_url = $member_url;
		$this->offset = $offset;
		$this->max_topics_per_page = $max_topics_per_page;
		$this->max_member_bookmarks = $max_member_bookmarks;
		$this->max_author_recent_posts = $max_author_recent_posts;

		$this->opt_use_memcached = $a_mc["use_memcached"];
		$this->opt_increase_profile_views = $this->conf['opt_log_profile_views'];
		$this->opt_show_member_bookmarks = $this->conf['opt_show_member_bookmarks'];
		$this->opt_show_member_recent_posts = $this->conf['opt_show_member_recent_posts'];
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


		$member['topics_count'] = $this->get_member_topics_count($member['id']);

		$member['bookmarks_count'] = $this->get_member_bookmarks_count($member['id']);

		$member['comments_count'] = $this->get_member_comments_count($member['id']);

		if($this->opt_show_member_recent_posts) {
			$member['a_recent_topics'] = $this->get_member_recent_topics($member['id']);
		}

		if($this->opt_show_member_bookmarks) {
			$member['a_bookmarks'] = $this->get_member_bookmarks($member['id']);
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
			if($member) {
				$this->data_origin = 'memcached';
				return $member;
			}
		}

		$conn = $this->db_connect($this->db_settings);
		$sql = 'SELECT * FROM users WHERE user_status_id=' . USER_ACTIVE . ' AND username = ?';

		/* Prepare statement */
		$stmt = $conn->prepare($sql);
		if($stmt === false) {
			$user_error =  'Wrong SQL: ' . $sql . '<br>' . 'Error: ' . $conn->errno . ' ' . $conn->error;
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
			$member=$rs[0];
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
			$user_error =  'Wrong SQL: ' . $sql . '<br>' . 'Error: ' . $conn->errno . ' ' . $conn->error;
			trigger_error($user_error, E_USER_ERROR);
		}

		/* Bind parameters. TYpes: s = string, i = integer, d = double,  b = blob */
		$stmt->bind_param('s',$this->member_url);

		/* Execute statement */
		$stmt->execute();

		if($stmt->affected_rows != 1) {
			$user_error =  'Database error: Username not unique. ' . $sql;
			trigger_error($user_error, E_USER_ERROR);
		}
		/* close statement */
		$stmt->close();
	}


	public function get_member_topics_count($member_id) {

	}

	public function get_member_bookmarks_count($member_id) {

	}

	public function get_member_comments_count($member_id) {

	}

	public function get_member_recent_topics($member_id) {

	}

	public function get_member_bookmarks($member_id) {

	}


	/**
	 * Destructor
	 */
	public function __destruct() {
		$this->db_disconnect();
	}

}