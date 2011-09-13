<?php

class session {
	
	private $session;
	private $flash = array();
	private $udata = array();
	
	private $_cookie_name = '_session_cookie';
	private $_cookie_url = 'gaiasenigma.com';
	
	public function __construct() {
		$this->_check_table();
		$this->session = $this->_get();
		
		$this->udata = json_decode($this->session->udata, TRUE);
		$this->flash = json_decode($this->session->flash, TRUE);
	}
	
	public function __destruct() {
		$this->_save();
	}
	
	public function __get($key) {
		return $this->udata->$key;
	}
	
	public function __set($key, $val) {
		$this->udata->$key = $val;
		$this->_save();
	}
	
	public function __isset($key) {
		return isset($this->udata->$key);
	} 
	
	public function setflash($key, $val) {
		$flash = $this->flash;
		
		if(is_string($flash[$key]))
			$flash[$key] .= $val;
		else $flash[$key] = $val;
		
		$this->flash = $flash;
		
		$this->_save();
	}
	
	public function getflash($key, $preserve = FALSE) {
		$flash = $this->flash;
		$store = $flash[$key];
		if(!$preserve) unset($flash[$key]);
		
		return $store;
	}
	
	private function _save() {
		$this->session->udata = $this->udata;
		$this->session->flash = $this->flash;
		
		$this->session->save();
	}
	
	private function _get() {
		if(!isset($_COOKIE[$this->_cookie_name])) return $this->_create();
		
		$key = $_COOKIE[$this->_cookie_name];
		$session = db::$mysql->cms->select('_sessions', "WHERE `key` = '$key'")->model();
		$session->lasttime = date("Y-m-d H:i:s");
		$session->save();
		
		return $session;
	}
	
	private function _create() {
		$key = $this->_token(32);
		
		$session = db::$mysql->cms->model('_sessions', FALSE);
		$session->key = $key;
		$session->useragent = $_SERVER['HTTP_USER_AGENT'];
		$session->remoteip = $_SERVER['REMOTE_ADDR'];
		$session->save();

		setcookie($this->_cookie_name,$key,0,'/',$this->_cookie_url,false,false);

		return $session;
	}

	/**
	 * Generate a random session ID.
	 *
	 * @param string $len 
	 * @param string $md5 
	 * @return void
	 * @author Andrew Johnson
	 * @website http://www.itnewb.com/v/Generating-Session-IDs-and-Random-Passwords-with-PHP
	 */
	private function _token( $len = 32, $md5 = true ) {

	    # Seed random number generator
	    # Only needed for PHP versions prior to 4.2
	    mt_srand( (double)microtime()*1000000 );

	    # Array of characters, adjust as desired
	    $chars = array(
	        'Q', '@', '8', 'y', '%', '^', '5', 'Z', '(', 'G', '_', 'O', '`',
	        'S', '-', 'N', '<', 'D', '{', '}', '[', ']', 'h', ';', 'W', '.',
	        '/', '|', ':', '1', 'E', 'L', '4', '&', '6', '7', '#', '9', 'a',
	        'A', 'b', 'B', '~', 'C', 'd', '>', 'e', '2', 'f', 'P', 'g', ')',
	        '?', 'H', 'i', 'X', 'U', 'J', 'k', 'r', 'l', '3', 't', 'M', 'n',
	        '=', 'o', '+', 'p', 'F', 'q', '!', 'K', 'R', 's', 'c', 'm', 'T',
	        'v', 'j', 'u', 'V', 'w', ',', 'x', 'I', '$', 'Y', 'z', '*'
	    );

	    # Array indice friendly number of chars; empty token string
	    $numChars = count($chars) - 1; $token = '';

	    # Create random token at the specified length
	    for ( $i=0; $i<$len; $i++ )
	        $token .= $chars[ mt_rand(0, $numChars) ];

	    # Should token be run through md5?
	    if ( $md5 ) {

	        # Number of 32 char chunks
	        $chunks = ceil( strlen($token) / 32 ); $md5token = '';

	        # Run each chunk through md5
	        for ( $i=1; $i<=$chunks; $i++ )
	            $md5token .= md5( substr($token, $i * 32 - 32, 32) );

	        # Trim the token
	        $token = substr($md5token, 0, $len);

	    } return $token;
	}
	
}