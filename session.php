<?php

class ysession {
	
	/**
	 * Store of the flash/userdata values as well as the session model
	 */
	private $session;
	private $flash = array();
	private $udata = array();
    
	/**
	 * Has the session model been modified
	 */
    private $modified = FALSE;
	
	/**
	 * Cookie information
	 */
	private $_cookie_name;
	private $_cookie_url;
	private $_cookie_exp;
	
	/**
	 * Construct the session
	 *
	 * @param string $cookie 
	 * @param string $url 
	 * @param string $exp 
	 * @author Kelly Lauren Summer Becker
	 */
	public function __construct($cookie = '_session_cookie', $url = FALSE, $exp = 0) {
		/**
		 * Pull the cookie information and save it
		 */
		$this->_cookie_name = $cookie;
		$this->_cookie_url = $url;
		$this->_cookie_exp = $exp;
		
		/**
		 * Delete any old sessions
		 */
        y::$db->cms->delete("_sessions", "WHERE `lasttime` < DATE_SUB(NOW(), INTERVAL 1 DAY)");
		y::$db->cms->delete("_sessions", "WHERE `id` = '0'");
        
		/**
		 * Check if the table exists. if not then create it? (doesnt always work)
		 */
		$this->_check_table();
		
		/**
		 * Get the session or create a new one
		 */
		$this->session = $this->_get();
		
		/**
		 * Set the flash/userdata and save them to their respecive class variables
		 */
		$this->udata = json_decode($this->session->udata, TRUE);
		$this->flash = json_decode($this->session->flash, TRUE);
	}
	
	/**
	 * If the session was modified then save it
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function __destruct() {
        if($this->modified) $this->_save();
	}
	
	/**
	 * Return userdata if it was set
	 *
	 * @param string $key 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function __get($key) {
		return $this->udata[$key];
	}
	
	/**
	 * Set userdata
	 *
	 * @param string $key 
	 * @param string $val 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function __set($key, $val) {
		$this->udata[$key] = $val;
        $this->modified = TRUE;
	}
	
	/**
	 * Is the userdata set
	 *
	 * @param string $key 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function __isset($key) {
		return isset($this->udata[$key]);
	} 
	
	/**
	 * Set flashdata (onetime access data)
	 *
	 * @param string $key 
	 * @param string $val 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function setflash($key, $val) {
		$flash = $this->flash;
		
		if(is_string($flash[$key]))
			$flash[$key] .= $val;
		else $flash[$key] = $val;
		
		$this->flash = $flash;
        		
        $this->modified = TRUE;
	}
	
	/**
	 * Retrieve flashdata (onetime access data)
	 *
	 * @param string $key 
	 * @param string $preserve 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function getflash($key, $preserve = FALSE) {
		$flash = $this->flash;
		$store = $flash[$key];
		if(!$preserve) unset($flash[$key]);
        $this->flash = $flash;

		if(!$preserve) $this->modified = TRUE;
		
		return $store;
	}
    
	/**
	 * Check if the sessions table exists
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
    private function _check_table() {
        $sql = "CREATE TABLE IF NOT EXISTS `_sessions` (
		  `id` varchar(32) NOT NULL,
		  `remoteip` text NOT NULL,
		  `useragent` text NOT NULL,
		  `lasttime` datetime NOT NULL,
		  `flash` text,
		  `udata` text,
		  PRIMARY KEY (`id`)
		) ENGINE=MyISAM DEFAULT CHARSET=latin1;";
		
		y::$db->cms->query($sql);
    }
	
	/**
	 * Save the flash/userdata to the db
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	private function _save() {
		$this->session->udata = json_encode($this->udata);
		$this->session->flash = json_encode($this->flash);
		
		$this->session->save();
	}
	
	/**
	 * Load the session
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	private function _get() {
		/**
		 * If there is no set session cookie then create a new session
		 */
		if(!isset($_COOKIE[$this->_cookie_name])) return $this->_create();
		
		/**
		 * Grab the ID from the cookie then load the DB
		 */
		$id = $_COOKIE[$this->_cookie_name];
		$session = y::$db->cms->select('_sessions', "WHERE `id` = '$id'")->model();
		
		/**
		 * If no session was found in the DB then create a new session
		 */
        if($session->id == NULL) return $this->_create();
        
		/**
		 * Store the current time and save
		 */
		$session->lasttime = date("Y-m-d H:i:s");
		$session->save();
        
		/**
		 * Save the id to session cookie
		 */
	    setcookie($this->_cookie_name, $id, $this->_cookie_exp, '/', $this->_cookie_url);
		
		/**
		 * Return the session
		 */
		return $session;
	}
	
	private function _create() {
		/** 
		 * Generate a random 32 digit token
		 */
		$id = $this->_token(32);
		
		/**
		 * Grab an empty session row and create the base session and save it
		 */
		$session = y::$db->cms->model('_sessions', FALSE, TRUE);
		$session->id = $id;
		$session->useragent = @$_SERVER['HTTP_USER_AGENT'];
		$session->remoteip = $_SERVER['REMOTE_ADDR'];
    	$session->lasttime = date("Y-m-d H:i:s");
		$session->save();

		/**
		 * Save the ID to a session cookie
		 */
	    setcookie($this->_cookie_name, $id, $this->_cookie_exp, '/', $this->_cookie_url);

		/**
		 * Return the session
		 */
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