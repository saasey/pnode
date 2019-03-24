<?php

abstract class pUser {

	abstract public function save_user_log($filename);
	abstract public function get_user_queue($filename = "users.conf");
	abstract public function get_user_log($filename);
	abstract public function detail_scrape();
	abstract public function find_user_first($token);
	abstract public function find_user_last($token);
	abstract public function find_user_range($token);
	abstract public function find_user_queue($token);
	abstract public function update_user($token);
	abstract public function get_servers($request);
	abstract public function get_sessions($request);
	abstract public function user_count();
	abstract public function update_queue();
	abstract public function disassemble_IP($host);
	abstract public function make_relationships();
	abstract public function add_referer();
	abstract public function remove_referer();
	abstract public function relative_count();

}