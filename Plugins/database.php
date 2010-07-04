<?php

/*
This file is part of Peachy MediaWiki Bot API

Peachy is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * DatabaseBase class, specifies the general functions for the Database classes
 * @abstract
 */
abstract class DatabaseBase {

	protected $mLastQuery;
	
	protected $mConn = false;
	
	protected $mServer;
	protected $mPort;
	protected $mUser;
	protected $mPassword;
	protected $mDB;
	protected $mPrefix;
	
	protected $mOpened = false;
	
	function __construct( $server, $port, $user, $password, $dbname ) {
		$this->mServer = $server;
		$this->mPort = $port;
		$this->mUser = $user;
		$this->mPassword = $password;
		$this->mDB = $dbname;
		
		if( !is_null( $server ) ) {
			$this->open();
		}
	}
	
	abstract function open();
	
	function close() {
		return true;
	} 
	
	public function query( $sql ) {
		$this->mLastQuery = $sql;
		
		$ret = $this->resultObject( $ret );
		if( !$ret ) {
			throw new DBError( $error, $errno, $sql ); 
		}
		
		return $ret; 
	}
	
	abstract function doQuery( $sql );
	
	function resultObject( $res ) {
	}
	
	abstract function fetchObject( $res ); 
	abstract function fetchRow( $res ); 
	abstract function numRows( $res ); 
	abstract function numFields( $res ); 
	abstract function get_field_name( $res, $n ); 
	abstract function get_insert_id(); 
	abstract function lastErrno(); 
	abstract function lastError(); 
	abstract function affectedRows(); 
	abstract function strencode( $s );
	
	/**
	 * SELECT frontend
	 * @param array|string $table Table(s) to select from. If it is an array, the tables will be JOINed.
	 * @param string|array $columns Columns to return
	 * @param string|array $where Conditions for the WHERE part of the query. Default null.
	 * @param array $options Options to add, such as GROUP BY, HAVING, ORDER BY, LIMIT, EXPLAIN. Default an empty array.
	 * @param array $join_on If selecting from more than one table, this adds an ON statement to the query. Defualt an empty array.
	 * @return object MySQL object
	 */
	function select( $table, $columns, $where = array(), $options = array(), $join_on = array() ) {
		
		if( is_array( $table ) ) {
			if( $this->mPrefix != '' ) {
				foreach( $table AS $id => $t ) {
					$table[$id] = $this->mPrefix . $t;
				}
			}
			
			if( !count( $join_on ) ) {
				$from = 'FROM ' . implode( ',', $table );
				$on = null;
			}
			else {
				$tmp = array_shift( $table );
				$from = 'FROM ' . $tmp;
				$from .= ' JOIN ' . implode( ' JOIN ', $table );
				
				$on = array();
				foreach( $join_on as $col => $val ) {
					$on[] = "$col = $val"
				}
				$on = 'ON ' . implode( ' AND ', $on );
			}
		}
		else {
			$from = 'FROM ' . $this->mPrefix . $table;
			$on = null;
		}
		
		
		if( is_array( $columns ) ) {
			$columns = implode( ',', $columns );
		}
		
		
		if( !is_null( $where ) ) {
			if( is_array( $where ) ) {
			
				$where_tmp = array();
				
				foreach( $where as $col => $val ) {
					
					if( is_array( $val ) ) {
						$opr = $val[0];
						$val = $this->strencode( $val[1] );
						
						$where_tmp[] = "`$col` $opr '$val'"
					}
					else {
						$val = $this->strencode( $val );
						$where_tmp[] = "`$col` = '$val'"
					}				
				}
				$where = implode( ' AND ', $where_tmp );
			}
			$where = "WHERE $where";
		}
		else {
			$where = null;
		}
		
		if( !is_array( $options ) ) {
			$options = array( $options );
		}
		
		$newoptions = array();
		$limit = null;
		$explain = null;
		
		foreach( $options as $option => $val ) {
			switch( $option ) {
				case 'LIMIT':
					$limit = "LIMIT $val";
					break;
				case 'EXPLAIN':
					$explain = "EXPLAIN $val";
					break;
				default:
					$newoptions[] = "$option $val";
			}
		}
		
		$newoptions = implode( ' ', $newoptions );
		
		$sql = "$explain SELECT $columns $from $on $where $newoptions $limit";
		
		return $this->doQuery( $sql );
	}
	
	function insert() {
	}
	
	function update() {
	}
	
	function delete() {
	}
	
	function replace() {
	}
	
	function tableExists( $tableName ) {
	}
	
	function set_prefix( $prefix ) {
		$this->mPrefix = $prefix;
	}
	
	function is_opened() {
		return $this->mOpened;
	}
	
}

/**
 * Database class, the actual class the user directly interfaces with.
 */
class Database {

	private $type = 'mysqli';
	private $server, $port, $user, $password, $db;

	function __construct( $server, $port, $user, $password, $db ) {
		$this->server = $server;
		$this->port = $port;
		$this->user = $user;
		$this->password = $password;
		$this->db = $db;
	}
	
	public function setType( $type ) {
		$this->type = $type;
	}
	
	public function init() {
		global $IP;
		
		if( !class_exists( 'mysqli' ) ) {
			$this->type = 'mysql';
		}
		
		switch( $this->type ) {
			case 'mysqli':
				require_once( $IP . 'Plugins/database/MySQLi.php' );
				return new DatabaseMySQLi( $this->server, $this->port, $this->user, $this->password, $this->db );
				break;
			case 'mysql':
				require_once( $IP . 'Plugins/database/MySQL.php' );
				return new DatabaseMySQL( $this->server, $this->port, $this->user, $this->password, $this->db );
				break;
			case 'pgsql':
				require_once( $IP . 'Plugins/database/PgSQL.php' );
				return new DatabasePgSQL( $this->server, $this->port, $this->user, $this->password, $this->db );
				break;
			default:
				require_once( $IP . 'Plugins/database/MySQLi.php' );
				return new DatabaseMySQLi( $this->server, $this->port, $this->user, $this->password, $this->db );
				break;
		}	
	}

	public static function load( $server, $port = 3306, $user, $pass, $dbname = null ) {
		if( func_num_args() > 4 ) {
			$Server = $server;
			$Port = ':' . $port;
			$User = $user;
			$Password = $pass;
			$DB = $dbname;
		}
		else {
			$Server = $server;
			$Port = '';
			$User = $port;
			$Password = $user;
			$DB = $pass;
		}	
		
		return new Database( $Server, $Port, $User, $Password, $DB );	
	}
	
	
}