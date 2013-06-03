<?php
/**
 * data_source class (database and cache operations)
 *
 * Supported RDMBS: MySQLi
 *
 * @author     Christos Pontikis http://pontikis.net
 * @copyright  Christos Pontikis
 * @license    MIT http://opensource.org/licenses/MIT
 * @version    0.1.0 (26 May 2013)
 *
 */
class data_source extends app_common {

	/** @var object Database connection */
	private $conn;

	/** @var object Memcached "connection" */
	private $mc;

	/**
	 * Constructor
	 *
	 */
	public function __construct() {
		// initialize
		$this->conn = null;
		$this->mc = null;
	}

	/**
	 * Establish database connection
	 * Supported RDMBS: MySQLi
	 *
	 * @param array $a_db database connection settings
	 *
	 *             <pre>
	 *             $a_db = array(
	 *                "db_server" => "localhost",
	 *                "db_name" => "dbname",
	 *                "db_user" => "user",
	 *                "db_passwd" => "passwd",
	 *             );
	 *             </pre>
	 *
	 * @return object Database connection
	 */
	public function db_connect($a_db) {
		if(is_null($this->conn)) {
			$conn = new mysqli($a_db['db_server'], $a_db['db_user'], $a_db['db_passwd'], $a_db['db_name']);
			$conn->set_charset('utf8');
			$this->conn = $conn;

			// custom error handler will catch an E_WARNING, so the following code is deprecated

/*			if($conn->connect_error) {
				echo 'Η σύνδεση με την βάση δεδομένων απέτυχε...' . '<br>' .
					'Error: ' . $conn->connect_errno . ' ' . $conn->connect_error;
				exit;
			} else {
				$conn->set_charset('utf8');
				$this->conn = $conn;
			}*/
		}
		return $this->conn;
	}

	/**
	 * Initialize memcached and add server(s) to cache pool
	 *
	 * @param array $a_mc memcached settings
	 *
	 *        <pre>
	 *        $a_mc = array(
	 *            "mc_pool" => array(
	 *                array(
	 *                    "mc_server" => "127.0.0.1",
	 *                    "mc_port" => "11211",
	 *                    "mc_weight" => 0
	 *                )
	 *            ),
	 *            "use_memcached" => true
	 *        );
	 *        </pre>
	 *
	 * @return object Memcached "connection"
	 */
	public function mc_init($a_mc) {
		if(is_null($this->mc)) {
			$mc_items = 0;
			$mc = new Memcached();
			foreach($a_mc["mc_pool"] as $mc_item) {
				if(array_key_exists("weight", $mc_item)) {
					$res_mc = $mc->addServer($mc_item["mc_server"], $mc_item["mc_port"], $mc_item["weight"]);
				} else {
					$res_mc = $mc->addServer($mc_item["mc_server"], $mc_item["mc_port"]);
				}
				if($res_mc) {
					$mc_items++;
				}
			}
			if($mc_items == 0) {
				$mc = null;
			}
			$this->mc = $mc;
		}
		return $this->mc;
	}

	/**
	 * Pull from memcached
	 *
	 * @param array $a_mc memcached settings
	 * @param string $key the key to search
	 * @return mixed the value for key (false if not found)
	 */
	public function pull_from_memcached($a_mc, $key) {
		$val = false;
		$mc = $this->mc_init($a_mc);
		if(!is_null($mc)) {
			$val = $mc->get($key);
		}
		return $val;
	}

	/**
	 * Push to memcached
	 *
	 * @param array $a_mc memcached settings
	 * @param string $key the key to search
	 * @param mixed $val the value of the key
	 */
	public function push_to_memcached($a_mc, $key, $val) {
		$mc = $this->mc_init($a_mc);
		if(!is_null($mc)) {
			$mc->set($key, $val);
		}
	}

}