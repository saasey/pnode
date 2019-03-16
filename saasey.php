<?php
   
class cURLHandler {

	public $ch;
	public $user;
	public $users;
	public $servers;
	public $fields;
	public $sessions;
	public $handles;
	public $request;
	public $path_user;
	public $path_server;

	function __construct() {
		$this->request = ($_SERVER['REQUEST_METHOD'] == "GET") ? ($_GET) : ($_POST);
		$this->request['host'] = $_SERVER['REMOTE_ADDR'];
		$this->users = [];
		$this->path_user = "user_logs/";
		$this->path_server = "server_logs/";
	}

	public function run() {

		// begin
		$this->ch = $this->create_multi_handler();

		// aggregate data
		$this->fields = $this->get_user_files();
		$this->sessions = $this->get_sessions($this->request);
		foreach ($this->fields as $value) {
			$user_vars = [];
			$server = null;
			$token = null;
			foreach ($value as $k => $v) {
				if ($k == 'server')
					$servers = $v;
				else if ($k != 'server' && $k != 'session')
					$user_vars[] = $v;
				else if ($k == 'session')
					$token = $v;
			}
			$this->handles[] = $this->prepare_curl_handle($this->servers, $user_vars, $token);
		}

		// swarm!
		$this->execute_multiple_curl_handles($this->handles);
		file_put_contents("users.conf", "");
		$query_str = http_build_query($this->request);
		$site = $this->request['server'] . "/";
		header("Location: $site?$query_str");
	}

	public function create_multi_handler() {
		return curl_multi_init();
	}

	public function get_user_files() {
		$search = [];
		$dim = "";
		$fread = file_get_contents("users.conf");
		$fr_dim = json_decode($fread);
		if (!is_dir($this->path_user))
			mkdir($this->path_user);
		foreach ($fr_dim as $value) {
			if (!file_exists($this->path_user.$value) || $value == "." || $value == "..")
				continue;
			$dim = file_get_contents("users.conf");
			$search[] = json_decode($dim);
			$search = array_unique($search);
		}
		return $search;
	}

	public function prepare_curl_handles($server, $fields, $token) {
		   
		$h = [];
		if ($server == null)
			return $h;

		$this->prepare_curl_handle($server, $fields, $token);
	   
		return $h;
	}

	public function prepare_curl_handle($server_url, $fields, $token){

		$field = [];  
		foreach ($fields as $k => $v)
			$field = array_merge($field, array($k => $v));
		$field = array_merge($field, array("token" => $token));
		$handle = curl_init($server_url);
		curl_setopt($handle, CURLOPT_TIMEOUT, 20);
		curl_setopt($handle, CURLOPT_URL, $server_url);
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($handle, CURLOPT_POST, 1);
		curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($field));
	   
		$len = strlen(json_encode($field));
		curl_setopt($handle, CURLOPT_HTTPHEADER, array(																	  
			'Content-Type' => 'application/json',
			'Content-Length' => $len
			)
		);
 
		return $handle;
	}

	public function add_handles($curl_multi_handler, $handles) {
		foreach($handles as $handle)
			curl_multi_add_handle($curl_multi_handler, $handle);
	}
   
	public function perform_multiexec($curl_multi_handler){
   
		do {
			$mrc = curl_multi_exec($curl_multi_handler, $active);
		} while ($active > 0);
 
		while ($active && $mrc == CURLM_OK) {
			if (curl_multi_select($curl_multi_handler) != -1) {
				do {
					$mrc = curl_multi_exec($curl_multi_handler, $active);
				} while ($active > 0);
			}
		}
	}

	public function perform_curl_close($curl_multi_handler, $handles){
	   
			  // is this necessary
		foreach($handles as $handle){
			curl_multi_remove_handle($curl_multi_handler, $handle);
		}
	 
		curl_multi_close($curl_multi_handler);
	}
   
	public function execute_multiple_curl_handles($handles) {
		$curl_multi_handler = $this->create_multi_handler();
		$this->add_handles($curl_multi_handler, $handles);
		$this->perform_multiexec($curl_multi_handler);
		$this->perform_curl_close($curl_multi_handler, $handles);
	}
   
   
	public function trace($var) {
	   
		echo '<pre>';
		print_r($var);
	}

	//save $this
	public function save_server_log($filename = "server.conf") {
		if (!is_dir($this->path_server))
			mkdir($this->path_server);
		file_put_contents($this->path_server.$filename, json_encode($this));
	}

	// save everything but ['server']
	public function save_user_log($filename) {
		if (!is_dir($this->path_user))
			mkdir($this->path_user);
		if (!file_exists($this->path_user.$filename))
			touch($this->path_user.$filename);
		if ($this->find_user($this->request['host']) == true)
			file_put_contents($this->path_user.$filename, json_encode($this->user));
		else
			file_put_contents($this->path_user.$filename, json_encode($this->request));			
	}

	// load everything
	public function get_server_log($filename = "server.log") {
		$fp = "";
		if (!is_dir($this->path_server))
			mkdir($this->path_server);
		if (!file_exists($this->path_server.$filename))
			return false;
		$dim = file_get_contents($this->path_user.$value);
		$decoded = json_decode($dim);
		foreach ($decoded as $k=>$v)
			$this->$k = $v;
	}

	// you'll find that in this file, we look
	// for SESSID a lot. It's called ['session']
	// to our script. It should be sent with the
	// incoming request.
	public function get_user_log($filename) {
		//$filename = $_COOKIE['PHPSESSID'];
		if (!is_dir($this->path_user))
			mkdir($this->path_user);
		$dim = file_get_contents($this->path_user.$value);
		$decoded = json_decode($dim);
		foreach ($decoded as $k=>$v)
			$this->user->$k = $v;
	}

	// look for an email address amongst the
	// files that are in $this->path_user
	public function find_user($value) {
		$search = [];
		if (!is_dir($this->path_user))
			mkdir($this->path_user);
		$files = scandir($this->path_user);
		$anchor = "";
		foreach ($files as $value) {
			if (!file_exists($this->path_user.$value) || filesize($this->path_user.$value) == 0 || $value == "." || $value == "..")
				continue;
			$dim = file_get_contents($this->path_user.$value);
			$search = json_decode($dim);
			foreach ($search as $k => $v)
				if (isset($search->$k) && $search->$k == $value && $anchor = $k)
					break;
		}
		if (!isset($search->$anchor) || $search->$anchor != $value)
			return false;
		foreach ($search as $k=>$v)
			$this->user[$k] = $v;
		return true;
	}

	// look for an email address amongst the
	// files that are in "users.conf"
	public function find_user_queue($value) {
		$search = [];
		if (!is_dir($this->path_user))
			mkdir($this->path_user);
		if (filesize("users.conf") == 0)
			return false;
		$file = file_get_contents("users.conf");
		$files = json_decode($file);
		$anchor = "";
		foreach ($files as $value) {
			if (!file_exists($this->path_user.$value) || filesize($this->path_user.$value) == 0 || $value == "." || $value == "..")
				continue;
			$dim = file_get_contents($this->path_user.$value);
			$search = json_decode($dim);
			foreach ($search as $k => $v)
				if (isset($search->$k) && $search->$k == $value && $anchor = $k)
					break;
		}
		if (!isset($search->$anchor) || $search->$anchor != $value)
			return false;
		foreach ($search as $k=>$v)
			$this->user[$k] = $v;
		return true;
	}

	// duplicate of save_user_log
	public function update_user($token) {
		$this->save_user_log($token);
	}

	// input the query string
	public function get_servers($request) {
		$this->servers = $request['server'];
		return $request['server'];
	}

	// input the query string
	public function get_sessions($request){
		return $request['session'];
	}

	// return the number of users present
	// and committed to sending info of.
	public function user_count() {
		if (is_array($this->users))
			return sizeof($this->users);
		$this->users = [];
		return 0;
	}

	// make sure there was a request
	public function validate_request(){
		if ($this->request != null)
			return true;
		return false;
	}

	public function update_queue() {
		$user_queue = [];
		if (filesize("users.conf") > 0) {
			$user_conf_opts_rw = file_get_contents("users.conf");

			$json_user_conf = json_decode($user_conf_opts_rw);
			$user_queue = null;
			foreach ($json_user_conf as $v) {
				$user_queue[] = $v;
			}
		}
		$user_queue[] = $this->request['session'];
		$user_queue = array_unique($user_queue);
		$this->update_user($this->request['session']);
		$string = json_encode($user_queue);
		file_put_contents("users.conf", $string);
	}


	// This is the only call you need
	// 
	public function parse_call() {

		if (!file_exists("users.conf"))
			touch("users.conf");
		if (filesize("users.conf") > 0) {
			$user_conf_opts_read = file_get_contents("users.conf");

			$this->users = json_decode($user_conf_opts_read);

			// No stomping on resources.
			if ($this->find_user_queue($this->request['host']) == true) {
				usleep(1000);
			}

			// TRUE == run() and empty files except users' and server.conf
			if (35 <= $this->user_count())
				$this->full_queue_run();

			$this->save_user_log($this->request['session']);
			$this->update_queue();
		}
		else {
			$this->save_user_log($this->request['session']);
			if ($this->users == null)
				$this->users = [];
			$this->users[] = $this->request['session'];
			file_put_contents("users.conf", json_encode($this->users));		
		}
	}

	public function full_queue_run() {
		$this->run();
		$query_str = http_build_query($this->request);
		file_put_contents("users.conf", "");
		if ($this->find_user($this->request['email']) == true)
			$this->update_user($this->request['session']);
	}

}
	/*****************************************************/

	session_start();
	if (!isset($_COOKIE['token']) || $_COOKIE['PHPSESSID'] != $_COOKIE['token'])
		setcookie("token", null, time() - 3600);
	setcookie("token", $_COOKIE['PHPSESSID'], time() + (86400 * 365), "/");

	$handler = new cURLHandler();

	if(!$handler->validate_request()) {
		exit();
	}

	$handler->parse_call();
