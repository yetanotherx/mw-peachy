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
		
		if( !$ret ) {
			throw new DBError( $error, $errno, $sql ); 
		}
		
		return $this->resultObject( $ret ); 
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
	
	function select() {
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