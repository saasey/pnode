<?php

trait pCon {

	public $path_user;
	public $path_server;

	public function setup() {
	// Default Directories and files for configuation in pUrl	//
		$this->path_user = "user_logs/";			//
		$this->path_server = "server_logs/";			//
		if (!is_dir($this->path_user))				//
			mkdir($this->path_user);			//
		if (!is_dir($this->path_server))			//
			mkdir($this->path_server);			//
		if (!file_exists("spoof_list"))				//
			touch("spoof_list");				//
		if (!file_exists("users.conf"))				//
			touch("users.conf");				//
	}

}