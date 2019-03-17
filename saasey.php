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
	public $opt_ssl;
	public $page_contents;
	public $percent_diff;
	public $delay;
	public $tax;

	function __construct() {
		$this->request = ($_SERVER['REQUEST_METHOD'] == "GET") ? ($_GET) : ($_POST);
		$this->request['host'] = $_SERVER['REMOTE_ADDR'];
		$this->users = [];
		$this->path_user = "user_logs/";
		$this->path_server = "server_logs/";
		$this->path_tax = "user_tax/";
		$this->opt_ssl = true;
		$this->percent_diff = 0.5;
		$this->delay = 1175;
		if (!is_dir($this->path_tax))
			mkdir($this->path_tax);
		if (!file_exists($this->path_tax.$this->request['session']))
			file_put_contents($this->path_tax.$this->request['session'], 0);
		$this->tax = file_get_contents($this->path_tax.$this->request['session']);
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

	// This is where we translate our user files into the curl call
	public function prepare_curl_handle($server_url, $fields, $token){

		$field = [];  
		foreach ($fields as $k => $v)
			$field = array_merge($field, array($k => $v));
		$field = array_merge($field, array("token" => $token));
		$handle = curl_init($server_url);
		$user_agent=$_SERVER['HTTP_USER_AGENT'];

		curl_setopt($handle, CURLOPT_TIMEOUT, 20);
		curl_setopt($handle, CURLOPT_URL, $server_url);
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($handle, CURLOPT_POST, 1);
		curl_setopt($handle, CURLOPT_FOLLOWLOCATION,true);
		curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($field));
		curl_setopt($handle, CURLOPT_BINARYTRANSFER, true);
		curl_setopt($handle, CURLOPT_ENCODING, "");
		curl_setopt($handle, CURLOPT_USERAGENT, $user_agent);
	   
		$len = strlen(json_encode($field));
		curl_setopt($handle, CURLOPT_HTTPHEADER, array(																	  
			'Content-Type' => 'application/x-www-form-urlencoded',
			'Content-Length' => $len
			)
		);

		$this->page_contents = curl_exec($handle);
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
		file_put_contents($this->path_user.$filename, json_encode($this->request));			
	}

	// load everything
	public function get_server_log($filename = "server.log") {
		$fp = "";
		if (!is_dir($this->path_server))
			mkdir($this->path_server);
		if (!file_exists($this->path_server.$filename))
			return false;
		$dim = file_get_contents($this->path_user.$filename);
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
		$dim = file_get_contents($this->path_user.$filename);
		$decoded = json_decode($dim);
		foreach ($decoded as $k=>$v)
			$this->user[$k] = $v;
	}

	// look for an email address amongst the
	// files that are in $this->path_user
	public function find_user($token) {
		$search = [];
		if (!is_dir($this->path_user))
			mkdir($this->path_user);
		$files = scandir($this->path_user);
		$anchor = "";
		$bool = 0;
		foreach ($files as $value) {
			if (!file_exists($this->path_user.$value) || filesize($this->path_user.$value) == 0 || $value == "." || $value == "..")
				continue;
			$dim = file_get_contents($this->path_user.$value);
			$search = json_decode($dim);
			foreach ($search as $k => $v) {
				if (isset($search->$k) && $search->$k == $token) {
					$anchor = $k;
					$bool = 1;
					foreach ($search as $k=>$v)
						$this->user[$k] = $v;
					return true;
				}
			}
		}
		return false;
	}

	// look for an email address amongst the
	// files that are in "users.conf"
	public function find_user_queue($token) {
		$search = [];
		if (!is_dir($this->path_user))
			mkdir($this->path_user);
		if (filesize("users.conf") == 0)
			return false;
		$file = file_get_contents("users.conf");
		$files = json_decode($file);
		$anchor = "";
		$bool = 0;
		foreach ($files as $value) {
			if (!file_exists($this->path_user.$value) || filesize($this->path_user.$value) == 0 || $value == "." || $value == "..")
				continue;
			$dim = file_get_contents($this->path_user.$value);
			$search = json_decode($dim);
			foreach ($search as $k => $v) {
				if (isset($search->$k) && $search->$k == $token) {
					$anchor = $k;
					$bool = 1;
					foreach ($search as $k=>$v)
						$this->user[$k] = $v;
					return true;
				}
			}
		}
		return false;
	}

	// duplicate of save_user_log
	public function update_user($token) {
		$this->save_user_log($token);
	}

	public function deep_search() {
		$x = 0;
		$y = 0;
		$z = 0;
		$user_conf_opts_read = file_get_contents("users.conf");

		$this->users = json_decode($user_conf_opts_read);
		foreach ($this->users as $user) {
			$x = 0;
			$y = 0;
			$this->get_user_log($user);
			foreach ($this->user as $k => $v) {
				if ($this->find_user_queue($v) == true)
					$x++;
				$y++;
			}
			if ($y > 0 && $x/$y > $this->percent_diff) {
				if (!is_dir($this->path_tax))
					mkdir($this->path_tax);
				if (!file_exists($this->path_tax.$this->request['session'])) {
					touch($this->path_tax.$this->request['session']);
					$this->tax = 0;
				}
				else {
					$this->tax = file_get_contents($this->path_tax.$this->request['session']);
					$this->tax++;
					file_put_contents($this->path_tax.$this->request['session'], $this->tax);
				}
				return $x/$y;
			}
		}
		return 0;
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
	public function validate_request() {
		if ($this->request != null && sizeof($this->request) != 1)
			return true;
		return false;
	}

	public function send_request() {
		$file = file_get_contents("users.conf");
		$user = json_decode($file);
		if ($this->find_user_queue($user[0]) == false)
			return false;
		$req = [];
		foreach ($this->user as $k => $v) {
			$req = array_merge($req, array($k => $v));
		}
		$options = array(
		  'http' => array(
			'header'  => array("Content-type: application/x-www-form-urlencoded"),
		        'method'  => 'POST',
		        'content' => http_build_query($req)
		        )
		);
		array_shift($user);
		
		file_put_contents("user.conf", $user);
		$context  = stream_context_create($options);
		$url = "https://" . $this->user['server'];
		if (!$this->opt_ssl)
			$url = "http://" . $this->user['server'];
		$this->page_contents = file_get_contents($url, false, $context);
		return true;
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

			// No stomping on resources.
			if ($this->deep_search() > $this->percent_diff) {
				usleep($this->delay * $this->tax);
			}

			// TRUE == run() and empty files except users' and server.conf
			if ($this->tax > 3)
				file_put_contents($this->path_tax.$this->request['session'], 0);
			$this->run_user_queue();

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

	public function run_user_queue() {
		if ($this->find_user_queue($this->request['session']) == true)
			$this->send_request();
	}

	public function option_ssl($bool) {
		$this->opt_ssl = $bool;
		return $bool;
	}

	public function print_page() {
		echo $this->page_contents;
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
	$handler->option_ssl(false);
	$handler->parse_call();
	$handler->print_page();
