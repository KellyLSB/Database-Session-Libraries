<?php

class db {
	
	public static $mysql;
	
	private static $databases;
	
	public static function init() {
		self::$databases = array(
			'cms' => array(
				'hostname' => 'localhost',
				'username' => 'root',
				'password' => '',
				'database' => 'yenn_cms'
			)
		);
		foreach(self::$databases as $hand=>$db) {
			self::$mysql->$hand = self::x($hand);
		}
	}
	
	private static function x($db) {
		$dbc = self::$databases[$db];
		return new mysql($dbc['hostname'], $dbc['username'], $dbc['password'], $dbc['database'], $db);
	}
	
} db::init();

class mysql {
	
	// PDO Object
	private $dbh;
	
	// Connected DB and Table
	public $db_tbl;
	
	// Time/Histories
	public static $time;
	public static $history = array();
	
	public function __construct($h, $u, $p, $d = false, $db = false) {
		$this->dbh = new PDO("mysql:host=$h;dbname=$d", $u, $p);
		
		if(!$this->dbh) throw new Exception("Could not connect to database $u:$p@$h/$d");
		
		$this->db_tbl['db'] = $db;

		return $this->dbh;
	}
	
	public function query($sql, $vsprintf = FALSE) {
		if($vsprintf) $sql = vsprintf($sql, $vsprintf);
		else if($vsprintf !== FALSE) $sql = vsprintf($sql, $vsprintf);
		
		$time = microtime(true);
		$result = $this->dbh->query($sql);
		$time = (microtime(true) - $time) * 1000;
		
		self::$time += $time;
		self::$history[] = array('sql' => $sql, 'ms' => round($time,3), 'time' => date("m/d/Y h:i:s a"));
		
		return new mysql_result($result, $sql, $this->db_tbl);
	}

	public function select($table, $conditions = '', $vsprintf = FALSE) {
		$this->db_tbl['tbl'] = $table;
		return $this->query("SELECT * FROM `$table` $conditions", $vsprintf);
	}

	public function update($table, $array, $conditions, $vsprintf = FALSE) {
		$this->db_tbl['tbl'] = $table;
		$update = $this->_insert($array);
		return $this->query("UPDATE `$table` SET $update $conditions", $vsprintf);
	}

	public function delete($table, $conditions, $vsprintf = FALSE) {
		return $this->query("DELETE FROM `$table` $conditions", $vsprintf);
	}

	public function select_by_id($table, $id, $vsprintf = FALSE) {
		
		$this->select($table, "WHERE `id` = '$id'", $vsprintf);
	}

	public function update_by_id($table, $array, $id, $vsprintf = FALSE) {
		return $this->update($table, $array, "WHERE `id` = '$id'", $vsprintf);
	}

	public function delete_by_id($table, $id, $vsprintf = FALSE) {
		return $this->delete($table, "WHERE `id` = '$id'", $vsprintf);
	}

	public function get_fields($table, $as_keys = false) {
		$cols = $this->query("SHOW COLUMNS FROM ".$table);
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
		return new mysql_model($this->db_tbl['db'], $table, $id);
	}
	
	private function _insert($array) {
		$return = array();
		foreach($array as $key=>$val) 
			$return[] = "`$key` = '$val'";
		return explode(', ', $return);	
	}
	
}

class mysql_result {

	public $result;
	public $query;
	public $db_tbl;
	
	public function __construct($result, $query, $db_tbl) {
		$this->result = $result;
		$this->query = $sql;
		$this->db_tbl = $db_tbl;
	}
	
	public function all() {
		return $this->result->fetchAll();
	}
	
	public function row() {
		return $this->result->fetch();
	}
	
	public function count() {
		return $this->result->rowCount();
	}
	
	public function model() {
		$row = $this->row();
		$db_tbl = $this->db_tbl;
		return db::m($db_tbl['db'], $db_tbl['tbl'], $row['id']);
	}

}

class mysql_model {
	
	private $_tbl;
	private $_db;
	private $_id;
	
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
		$this->_id = $id;
		
		if(is_numeric($id)) {
			if(!isset(self::$cache[$db][$tbl][$id]))
				self::$cache[$db][$tbl][$id] = db::x($db)->select_by_id($tbl, $id)->row();
			
			$this->data =& self::$cache[$db][$tbl][$id];
		}
		else $this->data = db::x($db)->get_fields($tbl, true);
		
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
		return "DB Model: #[$this->_id] in table $this->_tbl on DB $this->_db. Is using $this->memory bytes of memory.";
	}
	
	public function get_array() {
		return $this->data;
	}
	
	public function save($data = false) {
		if(is_array($data)) {
			foreach($data as $key=>$val) {
				if($key = 'id') continue;
				$this->$key = $val;
			}
			if(!$this->modified) return false;
			
			$this->modified = false;
			if($this->_id) db::x($this->_db)->update_by_id($this->_tbl, $this->data, $this->_id);
			else {
				$row = db::x($this->_db)->insert($this->_tbl, $this->data)->row();
				$this->data['id'] = $row['id'];
			}
		}
	}
	
	public function delete() {
		if(isset($this->_id)) {
			db::x($this->_db)->delete_by_id($this->_tbl, $this->_id);
			unset(self::$cache[$this->_db][$this->_tbl][$this->_id]);
		}
	}

}

db::$mysql->cms->get_fields('content', true);