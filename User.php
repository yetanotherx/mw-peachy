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

	/**
	 * Wiki class
	 * 
	 * @var Wiki
	 * @access private
	 */
	private $wiki; 
	
	/**
	 * Username
	 * 
	 * @var string
	 * @access private
	 */
	private $username;
	
	/**
	 * Whether or not user exists
	 * 
	 * @var bool
	 * @access private
	 */
	private $exists = true;
	
	/**
	 * Whether or not user is blocked
	 * 
	 * @var bool
	 * @access private
	 */
	private $blocked = false;
	
	/**
	 * Rough estimate as to number of edits
	 * 
	 * @var int
	 * @access private
	 */
	private $editcount;
	
	/**
	 * List of groups user is a member of
	 * 
	 * @var array
	 * @access private
	 */
	private $groups;
	
	/**
	 * Whether or not user is an IP
	 * 
	 * @var bool
	 * @access private
	 */
	private $ip = false;
	
	/**
	 * Page class of userpage
	 * 
	 * @var Page
	 * @access private
	 */
	private $userpage;
	
	/**
	 * Whether or not user has email enabled
	 * 
	 * @var bool
	 * @access private
	 */
	private $hasemail = false;
	
	/**
	 * Construction method for the User class
	 * 
	 * @access public
	 * @param Wiki &$wikiClass The Wiki class object
	 * @param mixed $username Username
	 * @return void
	 */
	function __construct( &$wikiClass, $username ) {
		global $pgHTTP;
		
		$this->wiki =& $wikiClass;
		
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
	
	/**
	 * Returns whether or not the user is blocked
	 * 
	 * @access public
	 * @param bool $force Whether or not to use the locally stored cache. Default false.
	 * @return bool
	 */
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
	
	/**
	 * Returns the editcount of the user
	 * 
	 * @access public
	 * @param bool $force Whether or not to use the locally stored cache. Default false.
	 * @param Database &$database Use an instance of the Database class to get a more accurate count
	 * @return int Edit count
	 */
	public function get_editcount( $force = false, &$database = null ) {
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
	
	/**
	 * Returns a list of all user contributions
	 * 
	 * @access public
	 * @param bool $mostrecentfirst Set to true to get the most recent edits first. Default true.
	 * @param bool $limit Only get this many edits. Default null.
	 * @return void
	 */
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
	
	/**
	 * Returns whether or not the user has email enabled
	 * 
	 * @access public
	 * @return bool
	 */
	public function has_email() {
		return $this->hasemail;
	}
	
	public function email( $text = null, $subject = "Wikipedia Email", $ccme = false ) {
		if( !$this->has_email() ) {
			pecho( "Cannot email {$this->username}, user has email disabled", PECHO_WARNING );
			return false;
		}
		
		if( !in_array( 'emailuser', $this->wiki->get_userrights() ) ) {
			throw new PermissionsError( "User is not allowed to email other users" );
			return false;
		}
		
		$tokens = $this->wiki->get_tokens();
		
		$editarray = array(
			'action' => 'emailuser',
			'target' => $this->title,
			'token' => $tokens['email'],
			'subject' => $subject,
			'text' => $text
		);
		
		if( $ccme ) $editarray['ccme'] = 'yes';
		
		Hooks::runHook( 'StartEmail', array( &$editarray ) );
		
		$result = $this->wiki->apiQuery( $editarray, true);
		
		if( isset( $result['error'] ) ) {
			throw new EmailError( $result['error']['code'], $result['error']['info'] );
		}
		elseif( isset( $result['emailuser'] ) ) {
			if( $result['emailuser']['result'] == "Success" ) {
				$this->__construct( $this->wiki, $this->title );
				return true;
			}
			else {
				throw new EmailError( "UnknownEmailError", print_r($result['email'],true));
			}
		}
		else {
			throw new DeleteError( "UnknownEmailError", print_r($result['email'],true));
		}
	}
	
	public function userrights() {}
	
	public function createaccount() {}
	
	/**
	 * List all deleted contributions.
	 * The logged in user must have the 'deletedhistory' right
	 * 
	 * @access public
	 * @param bool $content Whether or not to return content of each contribution. Default false
	 * @param string $start Timestamp to start at. Default null.
	 * @param string $end Timestamp to end at. Default null.
	 * @param string $dir Direction to list. Default 'older'
	 * @param array $prop Information to retrieve. Default array( 'revid', 'user', 'parsedcomment', 'minor', 'len', 'content', 'token' )
	 * @return array 
	 */
	public function deletedcontribs( $content = false, $start = null, $end = null, $dir = 'older', $prop = array( 'revid', 'user', 'parsedcomment', 'minor', 'len', 'content', 'token' ) ) {
		if( !in_array( 'deletedhistory', $this->wiki->get_userrights() ) ) {
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