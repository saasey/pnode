<?php

trait pCurl {

	abstract public function run();
	abstract public function create_multi_handler();
	abstract public function prepare_curl_handles($server, $fields, $token);
	abstract public function prepare_curl_handle($server_url, $fields, $token);
	abstract public function add_handles($curl_multi_handler, $handles);
	abstract public function perform_multiexec($curl_multi_handler);
	abstract public function perform_curl_close($curl_multi_handler, $handles);
	abstract public function execute_multiple_curl_handles($handles);
	abstract public function trace($var);

}