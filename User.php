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

class User {

	private $wiki; 
	private $username;
	private $exists = true;
	private $blocked = false;
	private $editcount;
	private $groups;
	private $ip = false;
	private $userpage;
	private $hasemail = false;
	
	function __construct( $wikiClass, $username ) {
		global $pgHTTP;
		
		$this->wiki = $wikiClass;
		
		$uiRes = $this->wiki->apiQuery(array(
				'action' => 'query',
				'format' => 'php',
				'list' => 'users|logevents',
				'ususers' => $username,
				'letype' => 'block',
				'letitle' => $username,
				'lelimit' => 1,
				'usprop' => 'editcount|groups|blockinfo|emailable'
		));
		
		$this->username = $uiRes['query']['users'][0]['name'];
		
		if( long2ip(ip2long($this->username) ) == $this->username ) {
			$this->exists = false;
			$this->ip = true;
			
			if (isset($uiRes['query']['logevents'][0]['block']['expiry']) && strtotime($uiRes['query']['logevents'][0]['block']['expiry']) > time()) {
				$this->blocked = true;
			}
		}
		elseif( isset( $uiRes['query']['users'][0]['missing'] ) || isset( $uiRes['query']['users'][0]['invalid'] ) ) {
			$this->exists = false;
			return false;
		}
		else {
			$this->editcount = $uiRes['query']['users'][0]['editcount'];
			
			if( isset( $uiRes['query']['users'][0]['groups'] ) ) 
				$this->groups = $uiRes['query']['users'][0]['groups'];
			
			if( isset( $uiRes['query']['users'][0]['blockedby'] ) ) 
				$this->blocked = true;
			
			if( isset( $uiRes['query']['users'][0]['emailable'] ) ) 
				$this->hasemail = true;
		}
	}
	
	public function isBlocked( $force = false ) {
		global $pgHTTP;
		
		if( $force ) {
			return $this->blocked;
		}
		
		$biRes = $this->wiki->apiQuery(array(
				'action' => 'query',
				'format' => 'php',
				'list' => 'blocks',
				'bkusers' => $this->username,
				'bkprop' => 'id'
		));
		
		if( count( $biRes['query']['blocks'] ) > 0 ) 
			return true;
			
		return false;
	}
	
	public function block() {}
	
	public function unblock() {}
	
	public function get_editcount( $force = false, $database = null ) {
		//First check if $database exists, because that returns a more accurate count
		if( !is_null( $database ) && $database instanceOf Database ) {
			$count = Database::mysql2array($database->select(
				'archive',
				'COUNT(*) as count',
				array( 
					array(
						'ar_user_text',
						'=',
						$param
					)
				)
			));
		
			if( isset( $count[0]['count'] ) ) {
				$del_count = $count[0]['count'];
			}
			else {
				$del_count = 0;
			}
			unset($count);
			
			$count = Database::mysql2array($database->select(
				'revision',
				'COUNT(*) as count',
				array( 
					array(
						'rev_user_text',
						'=',
						$param
					)
				)
			));
		
			if( isset( $count[0]['count'] ) ) {
				$live_count = $count[0]['count'];
			}
			else {
				$live_count = 0;
			}
			
			$this->editcount = $del_count + $live_count;
		}
		else {
			if( $force ) {
				$this->__construct( $this->wiki, $this->username );
			}
		}
		return $this->editcount;
	}
	
	public function get_contribs( $mostrecentfirst = true, $limit = null ) {
		$ucArray = array(
			'code' => 'uc',
			'ucuser' => $this->username,
			'action' => 'query',
			'list' => 'usercontribs',
			'limit' => $limit,
		);
		
		if( $mostrecentfirst ){
			$ucArray['ucdir'] = "older";
		} else {
			$ucArray['ucdir'] = "newer";
		}
		
		$result = $this->wiki->listHandler( $ucArray );
		return $result;
	}
	
	public function has_email() {
		return $this->hasemail;
	}
	
	public function email() {}
	
	public function userrights() {}
	
	public function createaccount() {}
	
	public function deletedcontribs( $content = false, $start = null, $end = null, $dir = 'older', $prop = array( 'revid', 'user', 'parsedcomment', 'minor', 'len', 'content', 'token' ) ) {
		if( !in_array( 'deletedhistory', $this->wiki->getUserRights() ) ) {
			throw new PermissionsError( "User is not allowed to view deleted revisions" );
			return false;
		}
		
		if( $content ) $prop[] = 'content';
		
		$drArray = array(
			'code' => 'dr',
			'list' => 'deletedrevs',
			'druser' => $this->username,
			'drprop' => implode( '|', $prop ),
			'drdir' => $dir
		);
		
		if( !is_null( $start ) ) $drArray['drstart'] = $start;
		if( !is_null( $end ) ) $drArray['drend'] = $end;
		
		return $this->wiki->listHandler( $drArray );
	}

}