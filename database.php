<?php

/**
 * Database Loader Library - With multiple DB support.
 * Uses PDO supports multiple DB formats - ONLY MySQL has been tested
 *
 * @package default
 * @author Kelly Lauren Summer Becker
 */
class db {
	
	/**
	 * Database Class Handle
	 */
	public static $db;
	
	/**
	 * Database Config Array
	 */
	private static $databases;
	
	/**
	 * Returns a model by its map
	 *
	 * @param string $map_model 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public static function map($map_model) {
		$id = substr(strstr($map_model,'('), 1, -1);
		$db_tbl = explode('.',strstr($map_model,'(', TRUE));
		
		if($id)
			return self::$db->{$db_tbl[0]}->select_by_id($db_tbl[1], $id)->model();
		else
			return self::$db->{$db_tbl[0]}->model($db_tbl[1], FALSE);
	}
	
	/**
	 * Loads all the databases to the $db handle
	 *
	 * @param string $databases 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public static function init($databases = false) {
		self::$db = new StdClass;
		
		/**
		 * If a array of databases was passed use it
		 */
		if(is_array($databases)) self::$databases = $databases;
		
		/**
		 * Else use the databases listed here
		 */
		else { 
			self::$databases = array(
				'cms' => array(
					'driver' => 'mysql',
					'hostname' => 'localhost',
					'username' => 'root',
					'password' => 'paperplate',
					'database' => 'yenn-demo_cms'
				)
				/*'sqlite' => array(
					'driver' => 'sqlite',
					'hostname' => '/opt/databases/mydb.sq3',
					'username' => NULL,
					'password' => NULL,
					'database' => NULL
				)*/
			);
		}
		
		/**
		 * Initialize each database on a handle
		 */
		foreach(self::$databases as $hand=>$db) {
			self::$db->$hand = self::x($hand);
		}
	}
	
	/**
	 * Initializes Databases and returns the object
	 *
	 * @param string $db 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	private static function x($db) {
		$dbc = self::$databases[$db];
		
		/**
		 * Format the DSN based on the driver being used
		 */
		if($dbc['driver'] == 'mysql') $dsn = $dbc['driver'].':host='.$dbc['hostname'].';dbname='.$dbc['database'].';';
		if($dbc['driver'] == 'sqlite') $dsn = $dbc['driver'].':'.$dbc['hostname'].';';
		
		/**
		 * Return the database object
		 */
		return new database($dsn, $dbc['username'], $dbc['password'], $db);
	}
	
}

class database {
	
	/**
	 * Database Object
	 */
	private $dbh;
	
	/**
	 * Connected Database and Table - Passed along for modeling
	 */
	public $db_tbl;
	
	/**
	 * Time it takes to process the queries
	 * and Query history
	 */
	public static $time;
	public static $history = array();
	
	/**
	 * Initialize the PDO object
	 *
	 * @param string $dsn 
	 * @param string $u 
	 * @param string $p 
	 * @param string $db 
	 * @author Kelly Lauren Summer Becker
	 */
	public function __construct($dsn, $u = false, $p = false, $db = false) {
		$this->dbh = new PDO($dsn, $u, $p);
				
		$this->db_tbl['db'] = $db;
	}
	
	/**
	 * Run a SQL query
	 */
	public function query($sql, $vsprintf = false) {
		/**
		 * Sprint the string with either a value or an array
		 */
		if(is_array($vsprintf)) $sql = vsprintf($sql, $vsprintf);
		else if($vsprintf !== false) $sql = vsprintf($sql, $vsprintf);
		
		/**
		 * Start the timer
		 */
		$time = microtime(true);
		
		/**
		 * Run the query
		 */
		$result = $this->dbh->query($sql);
		
		/**
		 * Stop the timer and return how long the query took
		 */
		$time = (microtime(true) - $time) * 1000;
		
		/**
		 * Record total query processing time and history
		 */
		self::$time += $time;
		self::$history[] = array('sql' => $sql, 'ms' => round($time,3), 'time' => date("m/d/Y h:i:s a"));
		
		/**
		 * Debugging code
		 *****
		 * if(DEBUG) {
		 *    echo "<pre>";
		 *    print_r(self::$history);
		 *    echo "</pre>";
		 * }
		 */
		
		/**
		 * Return the database result object
		 */
		return new database_result($result, $sql, $this->db_tbl, $this->dbh);
	}
	
	/**
	 * Insert a row into a database
	 *
	 * @param string $table 
	 * @param string $array 
	 * @param string $vsprintf 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function insert($table, $array, $vsprintf = false) {
		$update = $this->_fragment($array);
		return $this->query("INSERT INTO `$table` SET $update;", $vsprintf);
	}

	/**
	 * Select row(s) from a database
	 *
	 * @param string $table 
	 * @param string $conditions 
	 * @param string $vsprintf 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function select($table, $conditions = '', $vsprintf = false) {
		$this->db_tbl['tbl'] = $table;
		return $this->query("SELECT * FROM `$table` $conditions;", $vsprintf);
	}

	/**
	 * Update row(s) in a database
	 *
	 * @param string $table 
	 * @param string $array 
	 * @param string $conditions 
	 * @param string $vsprintf 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function update($table, $array, $conditions, $vsprintf = false) {
		$this->db_tbl['tbl'] = $table;
		$update = $this->_fragment($array);
		return $this->query("UPDATE `$table` SET $update $conditions;", $vsprintf);
	}

	/**
	 * Delete row(s) in a database
	 *
	 * @param string $table 
	 * @param string $conditions 
	 * @param string $vsprintf 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function delete($table, $conditions, $vsprintf = false) {
		return $this->query("DELETE FROM `$table` $conditions;", $vsprintf);
	}

	/**
	 * Select a row by ID from a database
	 *
	 * @param string $table 
	 * @param string $id 
	 * @param string $vsprintf 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function select_by_id($table, $id, $vsprintf = false) {
		return $this->select($table, "WHERE `id` = '$id'", $vsprintf);
	}

	/**
	 * Update a row by ID in a database
	 *
	 * @param string $table 
	 * @param string $array 
	 * @param string $id 
	 * @param string $vsprintf 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function update_by_id($table, $array, $id, $vsprintf = false) {
		return $this->update($table, $array, "WHERE `id` = '$id'", $vsprintf);
	}

	/**
	 * Delete a row by ID in a database
	 *
	 * @param string $table 
	 * @param string $id 
	 * @param string $vsprintf 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function delete_by_id($table, $id, $vsprintf = false) {
		return $this->delete($table, "WHERE `id` = '$id'", $vsprintf);
	}

	/**
	 * Get the columns for a table in a database
	 *
	 * @param string $table 
	 * @param string $as_keys 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function get_fields($table, $as_keys = false) {
		$cols = $this->query("SHOW COLUMNS FROM `$table`;");
		$fields = array();
		
		if($cols->count() > 0) {
			$array = $cols->all();
			foreach($array as $col) {
				if($as_keys) $fields[$col['Field']] = NULL;
				else $fields[$col['Field']] = $col;
   			}
			return $fields;
		}
		else return false;
	}
	
	/**
	 * Return a model of the database table
	 *
	 * @param string $table 
	 * @param string $id 
	 * @param string $set_id 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function model($table, $id = false, $set_id = false) {
		return new database_model($this->db_tbl['db'], $table, $id, $set_id);
	}
	
	/**
	 * Convert an array of values to SQL insert values
	 *
	 * @param string $array 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	private function _fragment($array) {
		$return = array();
		foreach($array as $key=>$val) {
			$val = $this->_string_escape($val);
			$return[] = "`$key` = '$val'";
		}
		return implode(', ', $return);	
	}
	
	/**
	 * Escape strings but only once
	 *
	 * @param string $str 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	private function _string_escape($str) 
	{ 
	   $len=strlen($str); 
	    $escapeCount=0; 
	    $targetString=''; 
	    for($offset=0;$offset<$len;$offset++) { 
	        switch($c=$str{$offset}) { 
	            case "'": 
	                    if($escapeCount % 2 == 0) $targetString.="\\"; 
	                    $escapeCount=0; 
	                    $targetString.=$c; 
	                    break; 
	            case '"': 
	                    if($escapeCount % 2 == 0) $targetString.="\\"; 
	                    $escapeCount=0; 
	                    $targetString.=$c; 
	                    break; 
	            case '\\': 
	                    $escapeCount++; 
	                    $targetString.=$c; 
	                    break; 
	            default: 
	                    $escapeCount=0; 
	                    $targetString.=$c; 
	        } 
	    } 
	    return $targetString; 
	}
	
}

class database_result {
	
	/**
	 * Store the db object
	 */
	private $dbh;

	/**
	 * Store a copy of the PDOInstance, the query, and the DB and Table
	 */
	public $result;
	public $query;
	public $db_tbl;
	
	/**
	 * Set the class variables
	 *
	 * @param string $result 
	 * @param string $query 
	 * @param string $db_tbl 
	 * @param string $dbh 
	 * @author Kelly Lauren Summer Becker
	 */
	public function __construct($result, $query, $db_tbl, $dbh) {
		$this->result = $result;
		$this->query = $query;
		$this->db_tbl = $db_tbl;
		$this->dbh = $dbh;
	}
	
	/**
	 * If there is a DB result destroy the DB session so another call may be made
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function __destruct() {
		if($this->result)
			$this->result->closeCursor();
	}
	
	/**
	 * Pull the last insert ID
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function insertId() {
		return $this->dbh->lastInsertId();
	}
	
	/**
	 * Return an array of all the rows affected by the query
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function all() {
		return $this->result->fetchAll();
	}
	
	/**
	 * Pull an array of a row affected by the query one by one
	 *
	 * @param string $type 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function row($type = 'assoc') {
		if($type == 'assoc') $grab = PDO::FETCH_ASSOC;
		else if($type == 'num') $grab = PDO::FETCH_NUM;
		else if($type == 'object') $grab = PDO::FETCH_OBJ;
		else $grab = 'FETCH_ASSOC';
		
		return $this->result->fetch($grab);
	}
	
	/**
	 * Count all the affected rows
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function count() {
		return $this->result->rowCount();
	}
	
	/**
	 * Return a database model
	 *
	 * @param string $set_id 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function model($set_id = false) {
		$row = $this->row();
		$db_tbl = $this->db_tbl;
		return db::$db->{$db_tbl['db']}->model($db_tbl['tbl'], $row['id'], $set_id);
	}

}

class database_model {
	
	/**
	 * Database and Table Store
	 */
	private $_db;
	private $_tbl;
	
	/**
	 * Predefined ID on insert
	 */
	private $_set_id;
	private $_new = TRUE;
	
	/**
	 * Cache and used memory
	 */
	private static $memory;
	private static $this_memory;
	private static $cache = array();
	
	/**
	 * Stored Data
	 */
	private $data;
	
	/**
	 * Has the model bee modified
	 */
	private $modified = false;

	/**
	 * Initialize the model
	 *
	 * @param string $db 
	 * @param string $tbl 
	 * @param string $id 
	 * @param string $set_id 
	 * @author Kelly Lauren Summer Becker
	 */
	public function __construct($db, $tbl, $id = false, $set_id = false) {
		
		/**
		 * Get Initial Memory Usage
		 */
		$init_mem = memory_get_usage(true);
		
		/**
		 * Set default db/table in a var
		 */
		$this->_db = $db;
		$this->_tbl = $tbl;
		
		/**
		 * Is an ID being manually set?
		 */
		$this->_set_id = $set_id;
		
		/**
		 * If an ID is provided load the row, and store it to the cache
		 */
		if($id) {
			if(!isset(self::$cache[$db][$tbl][$id]))
				self::$cache[$db][$tbl][$id] = db::$db->$db->select_by_id($tbl, $id)->row();
			
			$this->data =& self::$cache[$db][$tbl][$id];
		}
		
		/**
		 * If no ID is provided then load the fields
		 */
		else $this->data = db::$db->$db->get_fields($tbl, true);
		
		/**
		 * If an id is in the model its not a new model
		 */
		if($this->id) $this->_new = false;
		
		/**
		 * Recalcuate the used memory and store it
		 */
		self::$this_memory = (memory_get_usage(true) - $init_mem);
		self::$memory += self::$this_memory;
	}
	
	/**
	 * If the model was modified then save it in the destruct
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function __destruct() {
		if($this->modified) $this->save();
	}
	
	/**
	 * Return isset() on the object var
	 *
	 * @param string $field 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function __isset($field) {
		return isset($this->data[$field]);
	}
	
	/**
	 * Return the $this->data value for $field
	 *
	 * @param string $field 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function __get($field) {
		if(!isset($this->data[$field])) return NULL;
		
		return $this->data[$field];
	}
	
	/**
	 * Set a new $this->data value for $field
	 *
	 * @param string $field 
	 * @param string $nval 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function __set($field, $nval) {
		if(!array_key_exists($field, $this->data)) return;
		if($field == 'id'&&!$this->_set_id) return;
		
		$init_mem = memory_get_usage(true);
		$this->modified = TRUE;
		
		$this->data[$field] = $nval;
		
		self::$memory += (memory_get_usage(true) - $init_mem);
	}
	
	/**
	 * Return the map of this model
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function _map() {
		return $this->_db.'.'.$this->_tbl.'('.$this->id.')';
	}
	
	/**
	 * If no method is called return the DB Model info
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function __toString() {
		return "DB Model: #[$this->id] in table $this->_tbl on DB $this->_db. Is using $this->memory bytes of memory.";
	}
	
	/**
	 * Return the $this->data model as an array
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function get_array() {
		return $this->data;
	}
	
	/**
	 * Save the $this->data into the table as a new row or update
	 *
	 * @param string $data 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function save($data = false) {
		
		/**
		 * If $data is passed then process the array into the various $this->data values
		 */
		if(is_array($data)) {
			foreach($data as $key=>$val) {
				if($key == 'id'&&!$this->_set_id) continue;
				$this->$key = $val;
			}
		}
		
		/**
		 * If nothing was modified dont spend memory running the query
		 */
		if(!$this->modified) return false;
		
		/**
		 * Process the query save
		 */
		$save = array();
		foreach($this->data as $key=>$val) {
			if($key == 'id'&&!$this->_set_id) continue;
			$save[$key] = $val;
		}
		
		/**
		 * Make the file as modified and then update/insert the values
		 */
		$this->modified = false;
		if($this->id&&!$this->_new) db::$db->{$this->_db}->update_by_id($this->_tbl, $save, $this->id);
		else $this->data['id'] = db::$db->{$this->_db}->insert($this->_tbl, $save)->insertId();
	}
	
	/**
	 * Delete the row from the db (Poof no more model)
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function delete() {
		if(isset($this->id)) {
			db::$db->{$this->_db}->delete_by_id($this->_tbl, $this->id);
			unset(self::$cache[$this->_db][$this->_tbl][$this->id]);
		}
	}
}