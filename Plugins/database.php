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
 * @file
 * Database plugin, contains functions that interact with a mysql/postgresql database
 * Much of this is from {@link http://svn.wikimedia.org/svnroot/mediawiki/trunk/phase3/includes/db/} which is licenced under the GPL.
 */

/**
 * DatabaseBase class, specifies the general functions for the Database classes
 * @abstract
 */
abstract class DatabaseBase {

	protected $mLastQuery;
	protected $mLastSelectParams;
	
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
		
		Hooks::runHook( 'DatabasePreRunQuery', array( &$sql ) );
		
		$ret = $this->doQuery( $sql );
		
		Hooks::runHook( 'DatabasePostRunQuery', array( &$ret ) );
		
		$ret = $this->resultObject( $ret );
		if( !$ret ) {
			throw new DBError( $this->lastError, $this->lastErrno, $sql ); 
		}
		
		return $ret; 
	}
	
	abstract function doQuery( $sql );
	
	function resultObject( $result ) {
		if( empty( $result ) ) {
			return false;
		} elseif ( $result instanceof ResultWrapper ) {
			return $result;
		} elseif ( $result === true ) {
			// Successful write query
			return $result;
		} else {
			return new ResultWrapper( $this, $result );
		}
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
	abstract function dataSeek( $res, $row );
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
		
		$this->mLastSelectParams = array( $table, $columns, $where, $options, $join_on );
		
		Hooks::runHook( 'DatabaseRunSelect', array( &$this->mLastSelectParams ) );
		
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
					$on[] = "$col = $val";
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
						
						$where_tmp[] = "`$col` $opr '$val'";
					}
					else {
						$val = $this->strencode( $val );
						$where_tmp[] = "`$col` = '$val'";
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
		
		return $this->query( $sql );
	}
	
	function insert( $table, $values, $options = array(), $select = "INSERT" ) {
	
		##FIXME: Make doc for this, the bot won't find it
		Hooks::runHook( 'DatabaseRun' . ucfirst( strtolower( $select ) ), array( &$table, &$values, &$options, &$select ) );
		
		if ( !count( $values ) ) {
			return true;
		}
		
		if ( !is_array( $options ) ) {
			$options = array( $options );
		}
		
		$cols = array();
		$vals = array();
		foreach( $values as $col => $value ) {
			$cols[] = "`$col`";
			$vals[] = "'" . $this->strencode( $value ) . "'";
		}
		
		$cols = implode( ',', $cols );
		$vals = implode( ',', $vals );
		
		$sql = $select . " " . implode( ' ', $options ) . " INTO {$this->mPrefix}$table ($cols) VALUES ($vals)";

		return $this->query( $sql );
	}
	
	function update( $table, $values, $conds = '*' ) { 
		
		Hooks::runHook( 'DatabaseRunUpdate', array( &$table, &$values, &$conds ) );
		
		$vals = array();
		foreach( $values as $col => $val ) {
			$vals[] = "`$col`" . "= '" . $this->strencode( $val ) . "'";
		}
		$vals = implode( ', ', $vals );
		
		
		$sql = "UPDATE {$this->mPrefix}$table SET " . $vals;
		
		if ( $conds != '*' ) {
		
			$cnds = array();
			
			foreach( $conds as $col => $val ) {
				$cnds[] = "`$col`" . "= '" . $this->strencode( $val ) . "'";
			}
			
			$cnds = implode( ', ', $cnds );
			
			$sql .= " WHERE " . $cnds;
		}
		
		return $this->query( $sql );
	}
		
	function delete( $table, $conds = '*' ) {
		
		Hooks::runHook( 'DatabaseRunDelete', array( &$sql ) );
		
		$sql = "DELETE FROM {$this->mPrefix}$table";
		
		if ( $conds != '*' ) {
		
			$cnds = array();
			foreach( $conds as $col => $val ) {
				$cnds[] = "`$col`" . "= '" . $this->strencode( $val ) . "'";
			}
			$cnds = implode( ' AND ', $cnds );
			
			$sql .= " WHERE " . $cnds;
		}
		
		return $this->query( $sql );
	}
	
	function replace( $table, $values, $options = array() ) {
		return $this->insert( $table, $values, $options, "REPLACE INTO" );
	}
	
	function tableExists( $table, $prefix = true ) {
		if( $prefix ) {
			$prefix = $this->mPrefix;
		}
		else {
			$prefix = null;
		}
		
		Hooks::runHook( 'DatabaseRunTableExists', array( &$table, &$prefix ) );
		
		$res = $this->query( "SELECT 1 FROM {$prefix}{$table} LIMIT 1" );
		
		if( $res ) {
			$this->freeResult( $res );
			return true;
		}
		else {
			return false;
		}
	}
	
	function set_prefix( $prefix ) {
		$this->mPrefix = $prefix;
	}
	
	function is_opened() {
		return $this->mOpened;
	}
	
}

//Iterator is the built-in PHP class that allows other classes to use foreach(), for(), etc
class ResultWrapper implements Iterator {
	var $db, $result, $pos = 0, $currentRow = null;

	/**
	 * Create a new result object from a result resource and a Database object
	 */
	function __construct( $database, $result ) {
		$this->db = $database;
		
		if ( $result instanceof ResultWrapper ) {
			$this->result = $result->result;
		} else {
			$this->result = $result;
		}
	}

	/**
	 * Get the number of rows in a result object
	 */
	function numRows() {
		return $this->db->numRows( $this->result );
	}

	/**
	 * Fetch the next row from the given result object, in object form.
	 * Fields can be retrieved with $row->fieldname, with fields acting like
	 * member variables.
	 *
	 * @param $res SQL result object as returned from Database::query(), etc.
	 * @return MySQL row object
	 * @throws DBUnexpectedError Thrown if the database returns an error
	 */
	function fetchObject() {
		return $this->db->fetchObject( $this->result );
	}

	/**
	 * Fetch the next row from the given result object, in associative array
	 * form.  Fields are retrieved with $row['fieldname'].
	 *
	 * @param $res SQL result object as returned from Database::query(), etc.
	 * @return MySQL row object
	 * @throws DBUnexpectedError Thrown if the database returns an error
	 */
	function fetchRow() {
		return $this->db->fetchRow( $this->result );
	}

	/**
	 * Free a result object
	 */
	function free() {
		$this->db->freeResult( $this->result );
		unset( $this->result );
		unset( $this->db );
	}

	/**
	 * Change the position of the cursor in a result object
	 * See mysql_data_seek()
	 */
	function seek( $row ) {
		$this->db->dataSeek( $this->result, $row );
	}

	/*********************
	 * Iterator functions
	 * Note that using these in combination with the non-iterator functions
	 * above may cause rows to be skipped or repeated.
	 */

	function rewind() {
		if ( $this->numRows() ) {
			$this->db->dataSeek( $this->result, 0 );
		}
		$this->pos = 0;
		$this->currentRow = null;
	}

	function current() {
		if ( is_null( $this->currentRow ) ) {
			$this->next();
		}
		return $this->currentRow;
	}

	function key() {
		return $this->pos;
	}

	function next() {
		$this->pos++;
		
		$this->currentRow = $this->fetchObject();
		
		return $this->currentRow;
	}

	function valid() {
		return $this->current() !== false;
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
		
		Hooks::runHook( 'InitDatabase', array( &$this->type ) );
		
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
		
		Hooks::runHook( 'LoadDatabase', array( &$Server, &$Port, &$User, &$Password, &$DB ) );
		
		return new Database( $Server, $Port, $User, $Password, $DB );	
	}
	
	
}