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

class Wiki {

	protected $base_url;
	protected $username;
	protected $edit_rate;
	protected $maxlag = false;
	protected $apiQueryLimit = 499;
	protected $isFlagged = false;
	private $extensions;
	private $extversions;
	protected $tokens = array();
	protected $userRights = array();
	private $namespaces = null;

	function __construct( $configuration, $extensions = array(), $extversions = array(), $recursed = false, $token = null ) {
		global $pgHTTP, $pgProxy, $pgHTTPEcho, $pgRunPage, $pgVerbose, $pgUA;
		
		$this->base_url = $configuration['baseurl'];
		$this->username = $configuration['username'];
		$this->extensions = $extensions;
		$this->extversions = $extversions;
		
		if( isset( $configuration['editsperminute'] ) && $configuration['editsperminute'] != 0) {
			$this->edit_rate = $configuration['editsperminute'];
		}
		
		if( isset( $configuration['proxyaddr'] ) ) {
			$pgProxy['addr'] = $configuration['proxyaddr'];
			
			if( isset( $configuration['proxytype'] ) ) {
				$pgProxy['type'] = $configuration['proxytype'];
			}
			
			if( isset( $configuration['proxyport'] ) ) {
				$pgProxy['port'] = $configuration['proxyport'];
			}
			
			if( isset( $configuration['proxyuser'] ) && isset( $configuration['proxypass'] ) ) {
				$pgProxy['userpass'] = $configuration['proxyuser'] . ':' . $configuration['proxypass'];
			}
		}
		
		if( isset( $configuration['httpecho'] ) && $configuration['httpecho'] == "true" ) {
			$pgHTTPEcho = true;
		}
		
		if( isset( $configuration['runpage'] ) ) {
			$pgRunPage = $configuration['runpage'];
		}
		
		if( isset( $configuration['useragent'] ) ) {
			$pgUA = $configuration['useragent'];
		}
		
		if( isset( $configuration['verbose'] ) ) {
			$pgVerbose = array();
			
			$tmp = explode('|',$configuration['verbose']);
			
			foreach( $tmp as $setting ) {
				if( $setting == "ALL" ) {
					$pgVerbose = array( 0,1,2,3,4 );
					break;
				}
				else {
					switch( $setting ) {
						case 'NORMAL':
							$pgVerbose[] = 0;
							break;
						case 'NOTICE':
							$pgVerbose[] = 1;
							break;
						case 'WARN':
							$pgVerbose[] = 2;
							break;
						case 'ERROR':
							$pgVerbose[] = 3;
							break;
						case 'FAT':
							$pgVerbose[] = 4;
							break;
					}
				}
			}
			
			unset( $tmp );
		}
		
		$lgarray = array(
			'lgname' => $this->username,
			'lgpassword' => $configuration['password'],
			'action' => 'login',
		);
		
		if( isset( $configuration['maxlag'] ) && $configuration['maxlag'] != "0" ) {
			$this->maxlag = $configuration['maxlag'];
			$lgarray['maxlag'] = $this->maxlag;
		}
		
		if( !is_null( $token ) ) {
			$lgarray['lgtoken'] = $token;
		}
		
		$loginRes = $this->apiQuery($lgarray,true);
		
		if( isset( $loginRes['login']['result'] ) ) {
			switch( $loginRes['login']['result'] ) {
				case 'NoName':
					throw new LoginError( array( 'NoName', 'Username not specified' ) );
					return false;
					break;
				case 'Illegal':
					throw new LoginError( array( 'Illegal', 'Username with illegal characters specified' ) );
					return false;
					break;
				case 'NotExists':
					throw new LoginError( array( 'NotExists', 'Username specified does not exist' ) );
					return false;
					break;
				case 'EmptyPass':
					throw new LoginError( array( 'EmptyPass', 'Password not specified' ) );
					return false;
					break;
				case 'WrongPass':
					throw new LoginError( array( 'WrongPass', 'Incorrect password specified' ) );
					return false;
					break;
				case 'WrongPluginPass':
					throw new LoginError( array( 'WrongPluginPass', 'Incorrect password specified' ) );
					return false;
					break;
				case 'CreateBlocked':
					throw new LoginError( array( 'CreateBlocked', 'IP address has been blocked' ) );
					return false;
					break;
				case 'Throttled':
					if( $recursed ) throw new LoginError( array( 'Throttled', 'Login attempts have been throttled' ) );
					
					$wait = $loginRes['login']['wait'];
					pecho( "Login throttled, waiting $wait seconds.\n\n", 1 );
					sleep($wait);
					
					$recres = $this->__construct( $configuration, $this->extensions, $this->extversions, true );
					return $recres;
					break;
				case 'Blocked':
					throw new LoginError( array( 'Blocked', 'User specified has been blocked' ) );
					return false;
					break;
				case 'NeedToken':
					if( $recursed ) throw new LoginError( array( 'NeedToken', 'Token was not specified' ) );
					
					$token = $loginRes['login']['token'];

					$recres = $this->__construct( $configuration, $this->extensions, $this->extversions, true, $token );
					return $recres;
					break;
				case 'Success':
					pecho( "Successfully logged in to {$this->base_url} as {$this->username}\n\n", 0 );
					
					$userInfoRes = $this->apiQuery(
						array(
							'action' => 'query',
							'meta' => 'userinfo',
							'uiprop' => 'blockinfo|rights|groups'
						)
					);
					
					if( in_array( 'apihighlimits', $userInfoRes['query']['userinfo']['rights'] ) ) {
						$this->apiQueryLimit = 4999;
					}
					
					$this->userRights = $userInfoRes['query']['userinfo']['rights'];
					
					if( in_array( 'bot', $userInfoRes['query']['userinfo']['groups'] ) ) {
						$this->isFlagged = true;
					}
					
					$tokens = $this->apiQuery( array(	
						'action' => 'query',
						'prop' => "info",
						'titles' => 'Main Page',
						'intoken' => 'edit|delete|protect|move|block|unblock|email|import'
					));
					
					if( !isset( $tokens['query']['pages'] ) ) return false;
					
					foreach( $tokens['query']['pages'] as $x ) {
						foreach( $x as $y => $z ) {
							if( in_string( 'token', $y ) ) {
								$this->tokens[str_replace('token','',$y)] = $z;
							}
						}
					}
					

			}
		}
		
	}
	
	public function logout() {
		$this->apiQuery( array( 'action' => 'logout' ), true );
	}
	
	public function apiQuery( $arrayParams = array(), $post = false ) {
		global $pgHTTP;
		
		$arrayParams['format'] = 'php';
		$assert = false;
		
		if( $post && $this->isFlagged && in_array( 'assert', array_values( $post ) ) && $post['assert'] == 'user' ) {
			$post['assert'] = 'bot';
			$assert = true;
		}
		
		if( $post ) {
			$data = unserialize( $pgHTTP->post(
				$this->base_url,
				$arrayParams
			));
			
			if( $assert && $data['edit']['assert'] == 'Failure' ) {
				throw new EditError( 'AssertFailure', print_r( $data['edit'], true ) );
			}
			
			return $data;
		}
		else {
			return unserialize( $pgHTTP->get(
				$this->base_url,
				$arrayParams
			));
		}
	}
	
	public function listHandler( $tArray = array() ) {
		
		$code = $tArray['code'];
		unset( $tArray['code'] );
		$limit = $tArray['limit'];
		unset( $tArray['limit'] );
		
		$tArray['action'] = 'query';
		$tArray[$code . 'limit'] = ($this->apiQueryLimit + 1);
		
		$retrieved = 0;
		
		if(!is_null($limit)){
			if(!is_numeric($limit)){
				throw new BadEntryError("listHandler","limit should be a number or null");
			} else {
				$limit = intval($limit);
				if($limit < 0 || (floor($limit) != $limit)){
					throw new BadEntryError("listHandler","limit should an integer greater than 0");
				}
				if($limit < $eilimit){
					$tArray[$code . 'limit'] = $limit;
				}
			}
		} 
		if(!is_null($tArray[$code . 'namespace'])){
			if(strlen($tArray[$code . 'namespace']) === 0){
				$tArray[$code . 'namespace'] = null;
			} elseif( is_array( $tArray[$code . 'namespace'] ) ) {
				$tArray[$code . 'namespace'] = implode('|', $tArray[$code . 'namespace'] );
			} else {
				$tArray[$code . 'namespace'] = (string)$tArray[$code . 'namespace'];
			}
		}
		
		$endArray = array();
		
		$continue = null;
		
		while( 1 ) { 
			
			if( !is_null( $continue ) ) $tArray[$code . 'continue'] = $continue;
			
			$tRes = $this->apiQuery( $tArray );
				
			if( isset( $tRes['error'] ) ) {
				throw new APIError( array( 'error' => $tRes['error']['code'], 'text' => $tRes['error']['info'] ) );
				return false;
			}
			
			foreach( $tRes['query'] as $x ) {
				foreach( $x as $y ) {
					if( isset( $tArray['lhtitle'] ) ) $y = $y[$tArray['lhtitle']];
					$endArray[] = $y;
				}
			}
			
			if(!is_null($limit)){
				$retrieved = $retrieved + $tArray[$code . 'limit'];
				if($retrieved > $limit){
					$endArray = array_slice($endArray,0,$limit);
					break;
				}
			}
			
			if( isset( $tRes['query-continue'] ) ) {
				foreach( $tRes['query-continue'] as $z ) {
					$continue = $z[$code . 'continue'];
				}
			}
			else {
				break;
			}	
			
		}
		
		return $endArray;
	}
	
	public function get_base_url() {
		return $this->base_url;
	}
	
	public function get_api_limit() {
		return $this->apiQueryLimit;
	}
	
	public function get_maxlag() {
		return $this->maglag;
	}
	
	public function get_edit_rate() {
		return $this->edit_rate;
	}
	
	public function purge( $titles ) {
		global $pgHTTP;
		
		if( is_array( $titles ) ) {
			$titles = implode( '|', $titles );
		}
		
		$SMres = $this->apiQuery(array(
				'format' => 'php',
				'action' => 'purge',
				'titles' => $titles
			),
			true
		);
		
		##FIXME: Make sure this works
	}
	
	public function rollback() {}
	
	public function recentchanges( $namespace = 0, $tag = false, $start = false, $end = false, $user = false, $excludeuser = false, $dir = 'older', $minor = null, $bot = null, $anon = null, $redirect = null, $patrolled = null, $prop = array( 'user', 'comment', 'flags', 'timestamp', 'title', 'ids', 'sizes', 'tags' ) ) {

		if( is_array( $namespace ) ) {
			$namespace = implode( '|', $namespace );
		}
		
		$rcArray = array(
			'list' => 'recentchanges',
			'code' => 'rc',
			'rcnamespace' => $namespace,
			'rcdir' => $dir,
			'rcprop' => implode( '|', $prop ),
			
		);
		
		if( $tag ) $rcArray['rctag'] = $tag;
		if( $start ) $rcArray['rcstart'] = $start;
		if( $end ) $rcArray['rcend'] = $end;
		if( $user ) $rcArray['rcuser'] = $user;
		if( $excludeuser ) $rcArray['rcexcludeuser'] = $excludeuser;
		
		$rcshow = array();
		
		if( !is_null( $minor ) ) { 
			if( $minor ) {
				$rcshow[] = 'minor';
			}
			else {
				$rcshow[] = '!minor';
			}
		}
		
		if( !is_null( $bot ) ) { 
			if( $bot ) {
				$rcshow[] = 'bot';
			}
			else {
				$rcshow[] = '!bot';
			}
		}
		
		if( !is_null( $anon ) ) { 
			if( $minor ) {
				$rcshow[] = 'anon';
			}
			else {
				$rcshow[] = '!anon';
			}
		}
		
		if( !is_null( $redirect ) ) { 
			if( $redirect ) {
				$rcshow[] = 'redirect';
			}
			else {
				$rcshow[] = '!redirect';
			}
		}
		
		if( !is_null( $patrolled ) ) { 
			if( $minor ) {
				$rcshow[] = 'patrolled';
			}
			else {
				$rcshow[] = '!patrolled';
			}
		}
		
		if( count( $rcshow ) ) $rcArray['rcshow'] = implode( '|', $rcshow );
		
		$rcArray['limit'] = $this->apiQueryLimit;
		
		return $this->listHandler( $rcArray );
		
	}
	
	public function search() {}
	
	public function logs( $type = false, $user = false, $title = false, $start = false, $end = false, $dir = 'older', $tag = false, $prop = array( 'ids', 'title', 'type', 'user', 'timestamp', 'comment', 'details' ) ) {
		
		$leArray = array(
			'list' => 'logevents',
			'code' => 'le',
			'ledir' => $dir,
			'leprop' => implode( '|', $prop ),
		);
		
		if( is_array( $type ) ) $leArray['letype'] = implode( '|', $type );
		if( $start ) $leArray['lestart'] = $start;
		if( $end ) $leArray['leend'] = $end;
		if( $user ) $leArray['leuser'] = $user;
		if( $title ) $leArray['letitle'] = $title;
		if( $tag ) $leArray['letag'] = $tag;
		$leArray['limit'] = $this->apiQueryLimit;
		
		return $this->listHandler( $leArray );
	}
	
	public function allimages() {}
	
	public function allpages() {}
	
	public function alllinks() {}
	
	public function allusers() {}
	
	public function backlinks() {}
	
	public function listblocks() {}
	
	public function categorymembers() {}
	
	public function deletedrevs() {
	}
	
	public function embeddedin() {}
	
	public function logevents() {}
	
	public function tags() {}
	
	public function watchlist() {}
	
	public function watchlistraw() {}
	
	public function exturlusage() {}
	
	public function users() {}
	
	public function random() {}
	
	public function protectedtitles() {}
	
	public function siteinfo() {}
	
	public function allmessages() {}
	
	public function expandtemplates() {}
	
	public function parse() {}
	
	public function patrol() {}
	
	public function import() {}
	
	public function export() {}
	
	public function isLoggedIn() {}
	
	public function setUserAgent() {}
	
	public function diff( $rev1, $rev2 ) {
	
	}
	
	public function getTokens( $force = false, $rollback = false ) {
		if( $force ) return $this->tokens;
		
		if( $rollback ) {
			$tokens = $this->apiQuery( array(
				'action' => 'query',
				'prop' => "revisions",
				'titles' => 'Main Page',
				'rvtoken' => 'rollback'
			));
					
			foreach( $tokens['query']['pages'] as $x ) {
				foreach( $x as $y => $z ) {
					if( in_string( 'token', $y ) ) {
						$this->tokens[str_replace('token','',$y)] = $z;
					}
				}
			}
			
		}
		else {
			$tokens = $this->apiQuery( array(
				'action' => 'query',
				'prop' => "info",
				'titles' => 'Main Page',
				'intoken' => 'edit|delete|protect|move|block|unblock|email|import'
			));
					
			foreach( $tokens['query']['pages'] as $x ) {
				foreach( $x as $y => $z ) {
					if( in_string( 'token', $y ) ) {
						$this->tokens[str_replace('token','',$y)] = $z;
					}
				}
			}
			
			return $this->tokens;
		}
		
	}
	
	public function getExtensions() {
		return $this->extensions;
	}
	
	public function getExtversions() {
		return $this->extversions;
	}
	
	public function getNamespaces( $force = false ) {
		if( is_null( $this->namespaces ) || $force ) {
			$tArray = array(
				'meta' => 'siteinfo',
				'action' => 'query',
				'siprop' => 'namespaces'
			);
			$tRes = $this->apiQuery( $tArray );
			
			if( isset( $tRes['error'] ) ) {
				throw new APIError( array( 'error' => $tRes['error']['code'], 'text' => $tRes['error']['info'] ) );
				return false;
			}
			
			foreach($tRes['query']['namespaces'] as $namespace){
				$this->namespaces[$namespace['id']] = $namespace['*'];
			}
		}
		return $this->namespaces;
	}
	
	public function getUserRights() {
		return $this->userRights;
	}

}
