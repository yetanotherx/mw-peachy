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

class DatabaseMySQL extends DatabaseBase {

	public function get_type() {
		return 'mysql';
	}
	
	public function doQuery( $sql ) {
		$ret = mysql_query( $sql, $this->mConn );
		return $ret; 
	}
	
	public function open() {
		if( !function_exists( 'mysql_connect' ) ) {
			throw new DependancyError( "MySQL", "http://us2.php.net/manual/en/book.mysql.php" );
		}
		
		$this->close(); 
		
		$this->mOpened = false; 
		
		if ( ini_get( 'mysql.connect_timeout' ) <= 3 ) {
			$numAttempts = 2;
		} 
		else {
			$numAttempts = 1;
		} 
		
		for ( $i = 0; $i < $numAttempts && !$this->mConn; $i++ ) {
			if ( $i > 1 ) {
				usleep( 1000 );
			}
			
			$this->mConn = mysql_connect( $this->mServer . $this->mPort, $this->mUser, $this->mPassword, true );
		} 
		
		if( $this->mConn && !is_null( $this->mDB ) ) {
			$this->mOpened = @mysql_select_db( $this->mDB, $this->mConn );
		}
		else {
			$this->mOpened = (bool) $this->mConn;
		}
		
		return $this->mOpened;
	}
	
	public function close() {
		$this->mOpened = false;
		
		if( $this->mConn ) {
			return mysql_close( $this->mConn ); 
		}
		else {
			return true;
		}
	}
	
	public function fetchObject( $res ) {
		$row = mysql_fetch_object( $res ); 
		
		if( $this->lastErrno() ) {
			throw new DBError( $this->lastErrno(), $this->lastError() );
		}
		
		return $row;
	}
	
	public function fetchRow( $res ) {
		$row = mysql_fetch_array( $res ); 
		
		if( $this->lastErrno() ) {
			throw new DBError( $this->lastErrno(), $this->lastError() );
		}
		
		return $row;
	}
	
	public function numRows( $res ) {
		$row = mysql_num_rows( $res ); 
		
		if( $this->lastErrno() ) {
			throw new DBError( $this->lastErrno(), $this->lastError() );
		}
		
		return $row;
	}
	
	public function numFields( $res ) {
		$row = mysql_num_fields( $res ); 
		
		if( $this->lastErrno() ) {
			throw new DBError( $this->lastErrno(), $this->lastError() );
		}
		
		return $row;
	}
	
	public function get_field_name( $res, $n ) {
		return mysql_field_name( $res, $n ); 
	}
	
	public function get_insert_id() { 
		return mysql_insert_id( $this->mConn ); 
	} 
	
	public function lastErrno() {
		if ( $this->mConn ) {
			return mysql_errno( $this->mConn );
		} 
		else {
			return mysql_errno();
		}
	}
	
	public function lastError() {
		if ( $this->mConn ) {
			$error = mysql_error( $this->mConn );
			if ( !$error ) {
				$error = mysql_error();
			}
		} 
		else {
			$error = mysql_error();
		}
		
		return $error;
	}

	public function affectedRows() { 
		return mysql_affected_rows( $this->mConn ); 
	} 
	
	public function strencode( $s ) {
		$sQuoted = mysql_real_escape_string( $s, $this->mConn );

		if( !$sQuoted ) {
			$this->ping();
			$sQuoted = mysql_real_escape_string( $s, $this->mConn );
		}
	
		return $sQuoted;
	} 
	
	public function ping() {
		$ping = mysql_ping( $this->mConn );
		
		if ( $ping ) {
			return true;
		}

		mysql_close( $this->mConn );
		
		$this->mOpened = false;
		
		$this->mConn = false;
		
		$this->open( $this->mServer, $this->mUser, $this->mPassword, $this->mDBname );
		
		return true; 
	}
	
	
	
	
}