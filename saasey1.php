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
	public $refer_by;
	public $path_user;
	public $path_server;
	public $opt_ssl;
	public $page_contents;
	public $percent_diff;
	public $delay;
	public $tax;
	public $relative;
	public $from_addr;

	function __construct() {
		$this->request = ($_SERVER['REQUEST_METHOD'] == "GET") ? ($_GET) : ($_POST);
		$this->request['host'] = $_SERVER['REMOTE_ADDR'];
		$this->request['refer_by'] = [];
		$this->request['relative'] = [];
		$this->request['from_addr'] = [];
		$this->add_referer();
		$this->users = [];
		$this->path_user = "user_logs/";
		$this->path_server = "server_logs/";
		$this->path_tax = "user_tax/";
		$this->option_ssl(false);
		$this->percent_diff = 0.75;
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
		$this->fields = $this->get_user_queue();
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

	// load users in queue
	public function get_user_queue($filename = "users.conf") {
		$fp = "";
		if (!is_dir($this->path_user))
			mkdir($this->path_user);
		$dim = file_get_contents($filename);
		$users = json_decode($dim);
		$files = scandir($this->path_user);
		$this->users = array_intersect($users, (array)$files);
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
		$this->user = json_decode($dim);
	}

	public function detail_scrape() {
		$this->get_user_queue();
		$search = [];
		foreach ($this->users as $value) {
			if (!file_exists($this->path_user.$value) || filesize($this->path_user.$value) == 0 || $value == "." || $value == "..")
				continue;
			$this->get_user_log($value);
			$x = 0;
			$y = sizeof((array)$this->user) + sizeof((array)$this->user->refer_by) + sizeof((array)$this->relative);
			foreach ($this->request as $k=>$v) {
				if (is_array($k) || is_object($k))
					$x += sizeof(array_intersect($v, (array)$this->user->$k));
				else if ($this->request[$k] == $this->user->$k && $x++)
					continue;
			}
			if ($x/$y > $this->percent_diff)
				$search[] = array($x => $this->user->session);
		}
		return $search;
	}

	// look for an email address amongst the
	// files that are in $this->path_user
	public function find_user_first($token) {
		$search = [];
		if (!is_dir($this->path_user))
			mkdir($this->path_user);
		$search = $this->detail_scrape();
		krsort($search);
		if ($search[0] != null)
			return $search[0];
		return false;
	}

	// look for an email address amongst the
	// files that are in $this->path_user
	public function find_user_last($token) {
		$search = [];
		if (!is_dir($this->path_user))
			mkdir($this->path_user);
		$search = $this->detail_scrape();
		ksort($search);
		if ($search[0] != null)
			return $search[0];
		return false;
	}

	// look for an email address amongst the
	// files that are in $this->path_user
	public function find_user_range($token) {
		$search = [];
		if (!is_dir($this->path_user))
			mkdir($this->path_user);
		$search = $this->detail_scrape();
		krsort($search);
		if ($search != null)
			return $search;
		return false;
	}

	// look for an email address amongst the
	// files that are in "users.conf"
	public function find_user_queue($token) {
		$search = [];
		$this->get_user_queue();
		$y = sizeof($this->request);
		$search = $this->detail_scrape();
		if ($search != null)
			return $search;
		return false;
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
		$this->get_user_log($user[0]);
		$options = array(
		  'http' => array(
			'header'  => array("Content-type: application/x-www-form-urlencoded"),
		        'method'  => 'POST',
		        'content' => http_build_query((array)$this->user)
		        )
		);
		array_shift($user);
		
		file_put_contents("users.conf", $user);
		$context = stream_context_create($options);
		$url = $this->opt_ssl . $this->user->server;
		$this->page_contents = file_get_contents($url, false, $context);
		return true;
	}

	public function update_queue() {
		$user_queue = [];
		if (filesize("users.conf") > 0) {
			$user_conf_opts_rw = file_get_contents("users.conf");

			$json_user_conf = json_decode($user_conf_opts_rw);
			$user_queue = null;
		//	echo json_encode($json_user_conf);
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

	public function disassemble_IP($host) {
		if ($host == "::1")
			return;
		if (($trim = str_replace("http://","",$host)) == true)
			$this->option_ssl(false);
		if (($trim = str_replace("https://","",$host)) == true)
			$this->option_ssl(true);
		preg_match("/.\//", $trim, $output);
		if (is_array($output))
			echo json_encode($output);
		if ($output == null)
			return;
		$ipv4 = gethostbyname($output);
		preg_match_all("/(\d{1,3}|\.{0})/", $ipv4, $ip_pieces);
		$ip_pieces = $ip_pieces[0];
		$this->request['from_addr'] = [];
		$this->request['from_addr']['A'] = $ip_pieces[0];
		$this->request['from_addr']['B'] = $ip_pieces[1];
		$this->request['from_addr']['C'] = $ip_pieces[2];
		$this->request['from_addr']['D'] = $ip_pieces[3];
		$this->make_relationships();
	}

	public function make_relationships() {
		$this->get_user_queue();
		$new_relations = [];
		foreach ($this->users as $k => $v1) {
			if ($v1 != "from_addr" || $v1->session == $this->request['session'])
				continue;
			if ($this->request['from_addr']['A'] == $v1->A && $this->request['from_addr']['B'] == $v1->B &&
				$this->request['from_addr']['C'] == $v1->C)
				$new_relations[] = $v->session;
		}
		$unique = array_unique($new_relations);
		$this->request['relative'] = $new_relations;
	}

	public function add_referer () {
		if (isset($_SERVER['HTTP_REFERER']))
			$this->request['refer_by'][] = $_SERVER['HTTP_REFERER'];
		else
			$this->request['refer_by'][] = "local";
		$this->remove_referer();
		return true;
	}

	public function remove_referer() {
		if (sizeof($this->request['refer_by']) == 10)
			array_shift($this->request['refer_by']);
		return sizeof($this->request['refer_by']);
	}

	// This is the only call you need
	// 
	public function parse_call() {
		$this->spoof_check();
		if (!$this->match_server($this->request['host']))
			exit();
		else if (!$this->match_server($this->request['server']))
			exit();
		$host = $this->request['host'];
		$this->disassemble_IP($host);
		$this->delay_connection();
		$this->patch_connection();
	}

	public function spoof_check() {
		if (!file_exists("spoof_list"))
			touch("spoof_list");
		$pre_spoof_filter = file_get_contents("spoof_list");
		$spoof_list = json_decode($pre_spoof_filter);
		if ($spoof_list == null)
			return true;
		if (in_array($this->request['host'],$spoof_list))
			exit();
	}

	public function match_server($host) {
		$trim = str_replace("http://","",$host);
		$trim = str_replace("https://","",$host);
		if ($host == "::1")
			return true;
		else if (preg_match("/localhost./",$host))
			return true;
		else if (filter_var($host, FILTER_VALIDATE_URL) == false
			&& ($check_addr_list = gethostbynamel($host)) == false) {
			$spoof_list[] = $this->request['host'];
			$spoof_list = array_unique($spoof_list);
			file_put_contents("spoof_list", $spoof_list);
			return false;
		}
		return true;
	}

	public function delay_connection() {
		if (!file_exists("d_layer")) {
			touch("d_layer");
			file_put_contents("d_layer", 1);
		}
		else if (sizeof($this->users) > 10) {			
			$x = 0;
			$current_IP = $this->user->A.".".$this->user->B.".".$this->user->C.".";
			foreach ($this->users->relative as $v) {
				if ($v == $this->request['session'])
					$x++;
			}
			if ($x > 10) {
				get_user_queue();
				array_shift($this->users);
				$this->users[] = $this->request['session'];
				file_put_contents("users.conf", json_encode($this->users));
				exit();
			}
		}
		else {
			$delayer_for = file_get_contents("d_layer");
			$delayer_for++;
			file_put_contents("d_layer", $delayer_for%6);
			while ($delayer_for%6 > 0) {
				$delayer_for--;
				usleep($this->delay);
			}
		}
		return true;
	}

	public function patch_connection() {
		if (!file_exists("users.conf"))
			touch("users.conf");
		if (filesize("users.conf") > 0) {

			$this->run_queue();

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

	public function run_queue() {
		if ($this->find_user_queue($this->request['session']) != false)
			$this->send_request();
	}

	public function option_ssl($bool) {
		$this->opt_ssl = "https://";
		if ($bool == false)
			$this->opt_ssl = "http://";
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

	$handler->parse_call();
	$handler->print_page();