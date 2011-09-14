<?php

/**
 * Database Loader Library - With multiple DB support.
 * Uses PDO supports multiple DB formats - ONLY MySQL has been tested
 *
 * @package default
 * @author Kelly Lauren Summer Becker
 */

class db {
	
	public static $db;
	
	private static $databases;
	
	public static function init() {
		self::$databases = array(
			'cms' => array(
				'driver' => 'mysql',
				'hostname' => 'localhost',
				'username' => 'root',
				'password' => '',
				'database' => 'yenn_cms'
			)
			/*'sqlite' => array(
				'driver' => 'sqlite',
				'hostname' => '/opt/databases/mydb.sq3',
				'username' => NULL,
				'password' => NULL,
				'database' => NULL
			)*/
		);
		foreach(self::$databases as $hand=>$db) {
			self::$db->$hand = self::x($hand);
		}
	}
	
	private static function x($db) {
		$dbc = self::$databases[$db];
		if($dbc['driver'] == 'mysql') $dsn = $dbc['driver'].':host='.$dbc['hostname'].';dbname='.$dbc['database'].';';
		if($dbc['driver'] == 'sqlite') $dsn = $dbc['driver'].':'.$dbc['hostname'].';';
		
		return new database($dsn, $dbc['username'], $dbc['password'], $db);
	}
	
} db::init();

class database {
	
	// PDO Object
	private $dbh;
	
	// Connected DB and Table
	public $db_tbl;
	
	// Time/Histories
	public static $time;
	public static $history = array();
	
	public function __construct($dsn, $u = false, $p = false, $db = false) {
		$this->dbh = new PDO($dsn, $u, $p);
		
		if(!$this->dbh) throw new Exception("Could not connect to database \"$u:$p@$dsn\"");
		
		$this->db_tbl['db'] = $db;
	}
	
	public function query($sql, $vsprintf = FALSE) {
		if(is_array($vsprintf)) $sql = vsprintf($sql, $vsprintf);
		else if($vsprintf !== FALSE) $sql = vsprintf($sql, $vsprintf);
		
		$time = microtime(true);
		$result = $this->dbh->query($sql);
		$time = (microtime(true) - $time) * 1000;
		
		self::$time += $time;
		self::$history[] = array('sql' => $sql, 'ms' => round($time,3), 'time' => date("m/d/Y h:i:s a"));
		
		if(DEBUG){
			echo "<pre>";
			print_r(self::$history);
			echo "</pre>";
		}
		
		return new database_result($result, $sql, $this->db_tbl, $this->dbh);
	}
	
	public function insert($table, $array, $vsprintf = FALSE) {
		$update = $this->_fragment($array);
		return $this->query("INSERT INTO `$table` SET $update;", $vsprintf);
	}

	public function select($table, $conditions = '', $vsprintf = FALSE) {
		$this->db_tbl['tbl'] = $table;
		return $this->query("SELECT * FROM `$table` $conditions;", $vsprintf);
	}

	public function update($table, $array, $conditions, $vsprintf = FALSE) {
		$this->db_tbl['tbl'] = $table;
		$update = $this->_fragment($array);
		return $this->query("UPDATE `$table` SET $update $conditions;", $vsprintf);
	}

	public function delete($table, $conditions, $vsprintf = FALSE) {
		return $this->query("DELETE FROM `$table` $conditions;", $vsprintf);
	}

	public function select_by_id($table, $id, $vsprintf = FALSE) {
		return $this->select($table, "WHERE `id` = '$id'", $vsprintf);
	}

	public function update_by_id($table, $array, $id, $vsprintf = FALSE) {
		return $this->update($table, $array, "WHERE `id` = '$id'", $vsprintf);
	}

	public function delete_by_id($table, $id, $vsprintf = FALSE) {
		return $this->delete($table, "WHERE `id` = '$id'", $vsprintf);
	}

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
	
	public function model($table, $id = false) {
		return new database_model($this->db_tbl['db'], $table, $id);
	}
	
	private function _fragment($array) {
		$return = array();
		foreach($array as $key=>$val) {
			$val = $this->_string_escape($val);
			$return[] = "`$key` = '$val'";
		}
		return implode(', ', $return);	
	}
	
	private function _string_escape($str) 
	{ 
	   $len=strlen($str); 
	    $escapeCount=0; 
	    $targetString=''; 
	    for($offset=0;$offset<$len;$offset++) { 
	        switch($c=$str{$offset}) { 
	            case "'": 
	            // Escapes this quote only if its not preceded by an unescaped backslash 
	                    if($escapeCount % 2 == 0) $targetString.="\\"; 
	                    $escapeCount=0; 
	                    $targetString.=$c; 
	                    break; 
	            case '"': 
	            // Escapes this quote only if its not preceded by an unescaped backslash 
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
	
	private $dbh;

	public $result;
	public $query;
	public $db_tbl;
	
	public function __construct($result, $query, $db_tbl, $dbh) {
		$this->result = $result;
		$this->query = $query;
		$this->db_tbl = $db_tbl;
		$this->dbh = $dbh;
	}
	
	public function __destruct() {
		$this->result->closeCursor();
	}
	
	public function insertId() {
		return $this->dbh->lastInsertId();
	}
	
	public function all() {
		return $this->result->fetchAll();
	}
	
	public function row($type = 'assoc') {
		if($type == 'assoc') $grab = PDO::FETCH_ASSOC;
		else if($type == 'num') $grab = PDO::FETCH_NUM;
		else if($type == 'object') $grab = PDO::FETCH_OBJ;
		else $grab = 'FETCH_ASSOC';
		
		return $this->result->fetch($grab);
	}
	
	public function count() {
		return $this->result->rowCount();
	}
	
	public function model() {
		$row = $this->row();
		$db_tbl = $this->db_tbl;
		return db::$db->{$db_tbl['db']}->model($db_tbl['tbl'], $row['id']);
	}

}

class database_model {
	
	private $_tbl;
	private $_db;
	
	private static $memory;
	private static $this_memory;
	private static $cache = array();
	
	private $data;
	
	private $modified = FALSE;

	public function __construct($db, $tbl, $id) {
		
		// Determine memory usaage
		$init_mem = memory_get_usage(true);
		$this->_tbl = $tbl;
		$this->_db = $db;
		
		if(is_numeric($id)) {
			if(!isset(self::$cache[$db][$tbl][$id]))
				self::$cache[$db][$tbl][$id] = db::$db->$db->select_by_id($tbl, $id)->row();
			
			$this->data =& self::$cache[$db][$tbl][$id];
		}
		else $this->data = db::$db->$db->get_fields($tbl, true);
		
		self::$this_memory = (memory_get_usage(true) - $init_mem);
		self::$memory += self::$this_memory;
	}
	
	public function __destruct() {
		if($this->modified) $this->save();
	}
	
	public function __isset($field) {
		return isset($this->data[$field]);
	}
	
	public function __get($field) {
		if(!isset($this->data[$field])) return NULL;
		
		return $this->data[$field];
	}
	
	public function __set($field, $nval) {
		if(!array_key_exists($field, $this->data)) return;
		if($field == 'id') return;
		
		$init_mem = memory_get_usage(true);
		$this->modified = TRUE;
		
		$this->data[$field] = $nval;
		
		self::$memory += (memory_get_usage(true) - $init_mem);
	}
	
	public function __toString() {
		return "DB Model: #[$this->id] in table $this->_tbl on DB $this->_db. Is using $this->memory bytes of memory.";
	}
	
	public function get_array() {
		return $this->data;
	}
	
	public function save($data = false) {
		if(is_array($data)) {
			foreach($data as $key=>$val) {
				if($key == 'id') continue;
				$this->$key = $val;
			}
		}
		if(!$this->modified) return false;
		
		$save = array();
		foreach($this->data as $key=>$val) {
			if($key == 'id') continue;
			$save[$key] = $val;
		}
			
		$this->modified = false;
		if($this->id) db::$db->{$this->_db}->update_by_id($this->_tbl, $save, $this->id);
		else $this->data['id'] = db::$db->{$this->_db}->insert($this->_tbl, $save)->insertId();
	}
	
	public function delete() {
		if(isset($this->id)) {
			db::$db->{$this->_db}->delete_by_id($this->_tbl, $this->id);
			unset(self::$cache[$this->_db][$this->_tbl][$this->id]);
		}
	}
}