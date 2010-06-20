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
	
	/**
	 * URL to the API for the wiki.
	 * 
	 * @var string
	 * @access protected
	 */
	protected $base_url;
	
	/**
	 * Username for the user editing the wiki.
	 * 
	 * @var string
	 * @access protected
	 */
	protected $username;
	
	/**
	 * Edit of editing for the wiki in EPM.
	 * 
	 * @var int
	 * @access protected
	 */
	protected $edit_rate;
	
	/**
	 * Maximum db lag that the bot will accept. False to disable.
	 * 
	 * (default value: false)
	 * 
	 * @var bool|int
	 * @access protected
	 */
	protected $maxlag = false;
	
	/**
	 * Limit of results that can be returned by the API at one time.
	 * 
	 * (default value: 499)
	 * 
	 * @var int
	 * @access protected
	 */
	protected $apiQueryLimit = 499;
	
	/**
	 * Does the user have a bot flag.
	 * 
	 * (default value: false)
	 * 
	 * @var bool
	 * @access protected
	 */
	protected $isFlagged = false;
	
	/**
	 * Array of extenstions on the Wiki in the form of name => version.
	 * 
	 * @var array
	 * @access private
	 */
	private $extensions;
	
	/**
	 * Array of tokens for editing.
	 * 
	 * (default value: array())
	 * 
	 * @var array
	 * @access protected
	 */
	protected $tokens = array();
	
	/**
	 * Array of rights assigned to the user.
	 * 
	 * (default value: array())
	 * 
	 * @var array
	 * @access protected
	 */
	protected $userRights = array();
	
	/**
	 * Array of namespaces by ID.
	 * 
	 * (default value: null)
	 * 
	 * @var array
	 * @access private
	 */
	private $namespaces = null;
	
	/**
	 * array of namespaces that have subpages allowed, by namespace id.
	 * 
	 * (default value: null)
	 * 
	 * @var array
	 * @access private
	 */
	private $allowSubpages = null;
	
	/**
	 * Should the wiki follow nobots rules?
	 * 
	 * (default value: true)
	 * 
	 * @var bool
	 * @access private
	 */
	private $nobots = true;
	
	/**
	 * Text to search for in the optout= field of the {{nobots}} template
	 * 
	 * (default value: null)
	 * 
	 * @var null
	 * @access private
	 */
	private $optout = null;
	
	/**
	 * Whether or not to not edit if the user has new messages
	 * 
	 * (default value: false)
	 * 
	 * @var bool
	 * @access private
	 */
	private $stoponnewmessages = false;
	
	/**
	 * Configuration (sans password)
	 * 
	 * @var array
	 * @access private
	 */
	private $configuration;
	
	/**
	 * Contruct function for the wiki. Handles login and related functions.
	 * 
	 * @access public
	 * @see Peachy::newWiki()
	 * @param array $configuration Array with configuration data. At least needs username, password, and base_url.
	 * @param array $extensions Array of names of extensions installed on the wiki and their versions (default: array())
	 * @param bool $recursed Is the function recursing itself? Used internally, don't use (default: false)
	 * @param mixed $token Token if the wiki needs a token. Used internally, don't use (default: null)
	 * @return void
	 */
	function __construct( $configuration, $extensions = array(), $recursed = false, $token = null ) {
		global $pgHTTP, $pgProxy, $pgHTTPEcho, $pgRunPage, $pgVerbose, $pgUA;
		
		$this->base_url = $configuration['baseurl'];
		$this->username = $configuration['username'];
		$this->extensions = $extensions;
		
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
		
		if( isset( $configuration['optout'] ) ) {
			$this->optout = $configuration['optout'];
		}
		
		if( isset( $configuration['stoponnewmessages'] ) ) {
			$this->stoponnewmessages = 'true';
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
						case 'FATAL':
							$pgVerbose[] = 4;
							break;
					}
				}
			}
			
			unset( $tmp );
		}
		
		if( isset($configuration['nobots']) && $configuration['nobots'] == 'false' ) {
			$this->nobots = false;
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
		
		Hooks::runHook( 'PreLogin', array( &$lgarray ) );
		
		$loginRes = $this->apiQuery($lgarray,true);
		
		Hooks::runHook( 'PostLogin', array( &$loginRes ) );
		
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
					
					$recres = $this->__construct( $configuration, $this->extensions, true );
					return $recres;
					break;
				case 'Blocked':
					throw new LoginError( array( 'Blocked', 'User specified has been blocked' ) );
					return false;
					break;
				case 'NeedToken':
					if( $recursed ) throw new LoginError( array( 'NeedToken', 'Token was not specified' ) );
					
					$token = $loginRes['login']['token'];

					$recres = $this->__construct( $configuration, $this->extensions, true, $token );
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
					
					$this->configuration = $configuration;
					unset($this->configuration['password']);
					

			}
		}
		
	}
	
	/**
	 * Logs the user out of the wiki.
	 *
	 * @access public
	 * @return void
	 */
	public function logout() {
		$this->apiQuery( array( 'action' => 'logout' ), true );
	}
	
	/**
	 * Queries the API.
	 * 
	 * @access public
	 * @param array $arrayParams Parameters given to query with (default: array())
	 * @param bool $post Should it be a POST reqeust? (default: false)
	 * @return array Returns an array with the API result
	 */
	public function apiQuery( $arrayParams = array(), $post = false ) {
		global $pgHTTP;
		
		$arrayParams['format'] = 'php';
		$assert = false;
		
		if( $post && $this->isFlagged && in_array( 'assert', array_values( $arrayParams ) ) && $post['assert'] == 'user' ) {
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
	
	/**
	 * Simplifies the running of API queries, especially with continues and other parameters.
	 * 
	 * @access public
	 * @link http://compwhizii.net/peachy/wiki/Manual/Wiki::listHandler
	 * @param array $tArray Parameters given to query with (default: array()). In addition to those recognised by the API, ['code'] is the first two characters of all the parameters in a list=XXX API call. For example: with allpages, the parameters start with 'ap'. With recentchanges, the parameters start with 'rc' (required), ['limit'] to impose a hard limit on the number of results returned (optional) and ['lhtitle'] to simplify a multidimendional result into a unidimensional result. lhtitle is the key of the sub-array to return. (optional)
	 * @return array Returns an array with the API result
	 */
	public function listHandler( $tArray = array() ) {
		
		$code = $tArray['code'];
		unset( $tArray['code'] );
		
		if( isset($tArray['limit']) ) {
			$limit = $tArray['limit'];
			unset( $tArray['limit'] );
		} else {
			$limit = null;
		}
		
		$tArray['action'] = 'query';
		$tArray[$code . 'limit'] = ($this->apiQueryLimit + 1);
		
		$retrieved = 0;
		
		if( isset($limit) && !is_null($limit) ){
			if(!is_numeric($limit)){
				throw new BadEntryError("listHandler","limit should be a number or null");
			} else {
				$limit = intval($limit);
				if($limit < 0 || (floor($limit) != $limit)){
					throw new BadEntryError("listHandler","limit should an integer greater than 0");
				}

				if($limit < $tArray[$code . 'limit']){
					$tArray[$code . 'limit'] = $limit;
				}
			}
		} 
		if( isset($tArray[$code . 'namespace']) && !is_null($tArray[$code . 'namespace']) ){
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
	
	/**
	 * Returns the base URL for the wiki.
	 * 
	 * @access public
	 * @see Wiki::$base_url
	 * @return string base_url for the wiki
	 */
	public function get_base_url() {
		return $this->base_url;
	}
	
	/**
	 * Returns the api query limit for the wiki.
	 * 
	 * @access public
	 * @see Wiki::$apiQueryLimit
	 * @return int apiQueryLimit fot the wiki
	 */
	public function get_api_limit() {
		return $this->apiQueryLimit;
	}
	
	/**
	 * Returns if maxlag is on or what it is set to for the wiki.
	 * 
	 * @access public
	 * @see Wiki:$maxlag
	 * @return bool|int Max lag for the wiki
	 */
	public function get_maxlag() {
		return $this->maxlag;
	}
	
	/**
	 * Returns the edit rate in EPM for the wiki.
	 * 
	 * @access public
	 * @see Wiki::$edit_rate
	 * @return int Edit rate in EPM for the wiki
	 */
	public function get_edit_rate() {
		return $this->edit_rate;
	}
	
	/**
	 * Returns the username.
	 * 
	 * @access public
	 * @see Wiki::$username
	 * @return string Username
	 */
	public function get_username() {
		return $this->username;
	}
	
	/**
	 * Returns if the Wiki should follow nobots rules.
	 * 
	 * @access public
	 * @see Wiki::$nobots
	 * @return bool True for following nobots
	 */
	public function get_nobots() {
		return $this->nobots;
	}
	
	/**
	 * Returns if the script should not edit if the user has new messages
	 * 
	 * @access public
	 * @see Wiki::$stoponnewmessages
	 * @return bool True for stopping on new messages
	 */
	public function get_stoponnewmessages() {
		return $this->stoponnewmessages;
	}
	
	/**
	 * Returns the text to search for in the optout= field of the {{nobots}} template
	 * 
	 * @access public
	 * @see Wiki::$optout
	 * @return null|string String to search for
	 */
	public function get_optout() {
		return $this->optout;
	}
	
	/**
	 * Returns the configuration of the wiki
	 * 
	 * @access public
	 * @see Wiki::$configuration
	 * @return array Configuration array
	 */
	public function get_configuration() {
		return $this->configuration;
	}
	
	public function purge( $titles ) {
		global $pgHTTP;
		
		Hooks::runHook( 'StartPurge', array( &$titles ) );
		
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
		
		Hooks::runHook( 'PreQueryRecentchanges', array( &$rcArray ) );
		return $this->listHandler( $rcArray );
		
	}
	
	public function search() {}
	
	/**
	 * Retrieves log entries from the wiki.
	 * 
	 * @access public
	 * @link http://www.mediawiki.org/wiki/API:Query_-_Lists#logevents_.2F_le
	 * @param bool|array $type Type of log to retrieve from the wiki (default: false)
	 * @param bool|string $user Restrict the log to a certain user (default: false)
	 * @param bool|string $title Restrict the log to a certain page (default: false)
	 * @param bool|string $start Timestamp for the start of the log (default: false)
	 * @param bool|string $end Timestamp for the end of the log (default: false)
	 * @param string $dir Direction for retieving log entries (default: 'older')
	 * @param bool $tag Restrict the log to entries with a certain tag (default: false)
	 * @param array $prop Information to retieve from the log (default: array( 'ids', 'title', 'type', 'user', 'timestamp', 'comment', 'details' ))
	 * @return array Log entries
	 */
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
		
		Hooks::runHook( 'PreQueryLog', array( &$leArray ) );
		return $this->listHandler( $leArray );
	}
	
	/**
	 * Enumerate all images sequentially
	 * 
	 * @access public
	 * @link http://www.mediawiki.org/wiki/API:Query_-_Lists#allimages_.2F_le
	 * @param string $prefix Search for all image titles that begin with this value. (default: null)
	 * @param string $sha1 SHA1 hash of image (default: null)
	 * @param string $base36 SHA1 hash of image in base 36 (default: null)
	 * @param string $from The image title to start enumerating from. (default: null)
	 * @param string $minsize Limit to images with at least this many bytes (default: null)
	 * @param string $maxsize Limit to images with at most this many bytes (default: null)
	 * @param string $dir Direction in which to list (default: 'ascending')
	 * @param array $prop Information to retieve (default: array( 'timestamp', 'user', 'comment', 'url', 'size', 'dimensions', 'sha1', 'mime', 'metadata', 'archivename', 'bitdepth' ))
	 * @return array List of images
	 */
	public function allimages( $prefix = null, $sha1 = null, $base36 = null, $from = null, $minsize = null, $maxsize = null, $dir = 'ascending', $prop = array( 'timestamp', 'user', 'comment', 'url', 'size', 'dimensions', 'sha1', 'mime', 'metadata', 'archivename', 'bitdepth' ) ) {
		$leArray = array(
			'list' => 'allimages',
			'code' => 'ai',
			'aidir' => $dir,
			'aiprop' => implode( '|', $prop ),
		);
		
		if( !is_null( $from ) ) $leArray['aifrom'] = $from;
		if( !is_null( $prefix ) ) $leArray['aiprefix'] = $prefix;
		if( !is_null( $minsize ) ) $leArray['aiminsize'] = $minsize;
		if( !is_null( $maxsize ) ) $leArray['aimaxsize'] = $maxsize;
		if( !is_null( $sha1 ) ) $leArray['aisha1'] = $sha1;
		if( !is_null( $base36 ) ) $leArray['aisha1base36'] = $base36;
		
		Hooks::runHook( 'PreQueryAllimages', array( &$leArray ) );
		return $this->listHandler( $leArray );
	
	}
	
	/**
	 * Enumerate all pages sequentially
	 * 
	 * @access public
	 * @link http://www.mediawiki.org/wiki/API:Query_-_Lists#allpages_.2F_le
	 * @param array $namespace The namespace to enumerate. (default: array( 0 ))
	 * @param string $prefix Search for all page titles that begin with this value. (default: null)
	 * @param string $from The page title to start enumerating from. (default: null)
	 * @param string $redirects Which pages to list: all, redirects, or nonredirects (default: all)
	 * @param string $minsize Limit to pages with at least this many bytes (default: null)
	 * @param string $maxsize Limit to pages with at most this many bytes (default: null)
	 * @param array $protectiontypes Limit to protected pages. Examples: array( 'edit' ), array( 'move' ), array( 'edit', 'move' ). (default: array())
	 * @param array $protectionlevels Limit to protected pages. Examples: array( 'autoconfirmed' ), array( 'sysop' ), array( 'autoconfirmed', 'sysop' ). (default: array())
	 * @param string $dir Direction in which to list (default: 'ascending')
	 * @param string $interwiki Filter based on whether a page has langlinks (either withlanglinks, withoutlanglinks, or all (default))
	 * @return array List of pages
	 */
	public function allpages( $namespace = array( 0 ), $prefix = null, $from = null, $redirects = 'all', $minsize = null, $maxsize = null, $protectiontypes = array(), $protectionlevels = array(), $dir = 'ascending', $interwiki = 'all' ) {
		$leArray = array(
			'list' => 'allpages',
			'code' => 'ap',
			'apdir' => $dir,
			'apnamespace' => $namespace,
			'apfilterredir' => $redirects,
			'apfilterlanglinks' => $interwiki,
		);
		
		if( count( $protectiontypes ) && count( $protectionlevels ) ) {
			throw new BadEntryError( "AllPages", '$protectionlevels and $protectiontypes cannot be used in conjunction' );
		}
		elseif( count( $protectiontypes ) ) {
			$leArray['apprtype'] = implode( '|', $protectiontypes );
		}
		elseif( count( $protectionlevels ) ) {
			$leArray['apprlevel'] = implode( '|', $protectionlevels );
		}
		
		if( !is_null( $from ) ) $leArray['apfrom'] = $from;//
		if( !is_null( $prefix ) ) $leArray['apprefix'] = $prefix; //
		if( !is_null( $minsize ) ) $leArray['apminsize'] = $minsize; //
		if( !is_null( $maxsize ) ) $leArray['apmaxsize'] = $maxsize; // 
		
		Hooks::runHook( 'PreQueryAllpages', array( &$leArray ) );
		return $this->listHandler( $leArray );
	}
	
	/**
	 * Enumerate all internal links that point to a given namespace
	 * 
	 * @access public
	 * @link http://www.mediawiki.org/wiki/API:Query_-_Lists#alllinks_.2F_le
	 * @param array $namespace The namespace to enumerate. (default: array( 0 ))
	 * @param string $prefix Search for all page titles that begin with this value. (default: null)
	 * @param string $from The page title to start enumerating from. (default: null)
	 * @param string $continue When more results are available, use this to continue. (default: null)
	 * @param bool $unique Set to true in order to only show unique links (default: true)
	 * @param array $prop What pieces of information to include: ids and/or title. (default: array( 'ids', 'title' ))
	 * @return array List of links
	 */
	public function alllinks( $namespace = array( 0 ), $prefix = null, $from = null, $continue = null, $unique = true, $prop = array( 'ids', 'title' ) ) {
		$leArray = array(
			'list' => 'alllinks',
			'code' => 'al',
			'alnamespace' => $namespaces,
			'alprop' => implode( '|', $prop ),
		);
		
		if( !is_null( $from ) ) $leArray['alfrom'] = $from;
		if( !is_null( $prefix ) ) $leArray['alprefix'] = $prefix;
		if( !is_null( $continue ) ) $leArray['alcontinue'] = $continue;
		if( $unique ) $leArray['alunique'] = 'yes';
		$leArray['limit'] = $this->apiQueryLimit;
		
		Hooks::runHook( 'PreQueryAlllinks', array( &$leArray ) );
		return $this->listHandler( $leArray );
	}
	
	/**
	 * Enumerate all registered users
	 * 
	 * @access public
	 * @link http://www.mediawiki.org/wiki/API:Query_-_Lists#alllinks_.2F_le
	 * @param string $prefix Search for all usernames that begin with this value. (default: null)
	 * @param array $groups Limit users to a given group name (default: array())
	 * @param string $from The username to start enumerating from. (default: null)
	 * @param bool $editsonly Set to true in order to only show users with edits (default: false)
	 * @param array $prop What pieces of information to include (default: array( 'blockinfo', 'groups', 'editcount', 'registration' ))
	 * @return array List of users
	 */
	public function allusers( $prefix = null, $groups = array(), $from = null, $editsonly = false, $prop = array( 'blockinfo', 'groups', 'editcount', 'registration' ) ) {
		$leArray = array(
			'list' => 'allusers',
			'code' => 'au',
			'auprop' => implode( '|', $prop ),
		);
		
		if( !is_null( $from ) ) $leArray['aufrom'] = $from;
		if( !is_null( $prefix ) ) $leArray['auprefix'] = $prefix;
		if( !count( $groups ) ) $leArray['augroup'] = implode( '|', $groups );
		if( $editsonly ) $leArray['auwitheditsonly'] = 'yes';
		
		Hooks::runHook( 'PreQueryAllusers', array( &$leArray ) );
		return $this->listHandler( $leArray );
	}
	
	public function backlinks() {}
	
	public function listblocks() {}
	
	/**
	 * Retrieves the titles of member pages of the given category
	 * 
	 * @access public
	 * @param mixed $category Category to retieve
	 * @param bool $subcat Should subcategories be checked (default: false)
	 * @param mixed $namespace Restrict results to the given namespace (default: null)
	 * @return array Array of titles
	 */
	public function categorymembers( $category, $subcat = false, $namespace = null) {
		$cmArray = array(
			'list' => 'categorymembers',
			'code' => 'cm',
			'cmtitle' => $category,
			'cmprop' => 'title',
		);
		
		$strip_categories = false;
		
		if( $namespace !== null ) {
			if( is_array( $namspace ) ) {
				if( $subcat && !in_array( 14, $namespace ) ) {
					$namespace[] = 14;
					$strip_categories = true;
				}
				
				$namespace = implode( '|', $namespace );
			}
			else if( $subcat && $namespace !== '14' ) {
				$namespace .= '|14';
				$strip_categories = true;
			}
			
			$cmArray['cmnamespace'] = $namespace;
		}
		
		Hooks::runHook( 'PreQueryCategorymembers', array( &$cmArray ) );
		$top_category = $this->listHandler( $cmArray );
		$final_titles = array();
		
		foreach( array_values($top_category) AS $category ) {
			if( $category['ns'] == 14 && $subcat ) {
				$final_titles = array_merge( $final_titles, $this->categorymembers( $category['title'], $subcat, $namespace ));
				
				if( $strip_categories ) continue;
			}
			
			$final_titles[] = $category['title'];
		}
		
		return $final_titles;
	}
	
	public function deletedrevs() {
	}
	
	/**
	 * Returns array of pages that embed (transclude) the page given
	 * 
	 * @access public
	 * @param mixed $title
	 * @param mixed $namespace. (default: null)
	 * @return void
	 */
	public function embeddedin( $title, $namespace = null, $limit = null ) {
		$eiArray = array(
			'list' => 'embeddedin',
			'code' => 'ei',
			'eititle' => $title,
			'lhtitle' => 'title',
			'limit' => $limit
		);
		
		if(!is_null($namespace)){
			$eiArray['einamespace'] = $namespace;
		}
		
		Hooks::runHook( 'PreQueryEmbeddedin', array( &$eiArray ) );
		return $this->listHandler( $eiArray );
	}
	
	public function logevents() {}
	
	public function tags() {}
	
	public function watchlist() {}
	
	public function watchlistraw() {}
	
	/** 
	 * Returns details of usage of an external URL on the wiki.
	 *
	 * @access public
	 * @param string $url The url to search for links to, without a protocol. * can be used as a wildcard.
	 * @param string $protocol The protocol to accompany the URL. Only certain values are allowed, depending on how $wgUrlProtocols is set on the wiki; by default the allowed values are 'http://', 'https://', 'ftp://', 'irc://', 'gopher://', 'telnet://', 'nntp://', 'worldwind://', 'mailto:' and 'news:'. Default 'http://'.
	 * @param string $prop Properties to return; it should be a pipe '|' separated list of values; the options are 'ids', 'title' and 'url'. Default null (all).
	 * @param string $namespace A pipe '|' separated list of namespace numbers to check. Default null (all).
	 * @param int $limit A hard limit on the number of transclusions to fetch. Default null (all).
	 */
	public function exturlusage($url, $protocol = "http", $prop = null, $namespace = null, $limit = null ) {
		if( isset($prop) && !is_null($prop) ){
			if(strlen($prop) == 0){
				$prop = null;
			} else {
				if(!preg_match('/^(ids|title|url)(\|(ids|title|url)){0,2}/',$prop)){
					throw new BadEntryError("exturlusage",'$prop should be a pipe \'|\' separated list of values; the options are \'ids\', \'title\' and \'url\'.');
				}
			}
		}
		
		$tArray = array(
			'list' => 'exturlusage',
			'code' => 'eu',
			'euquery' => $url,
			'euprotocol' => $protocol,
			'limit' => $limit,
			'euprop' => $prop
		);	
		if(!is_null($namespace)){
			$tArray['eunamespace'] = $namespace;
		}
		
		Hooks::runHook( 'PreQueryExturlusage', array( &$tArray ) );
		$result = $this->listHandler($tArray);
		return $result;
	}
	
	public function users() {}
	
	/**
	 * Returns the titles of some random pages.
	 * 
	 * @access public
	 * @param string $namespaces A pipe '|' separated list of namespaces to select from (default: "0" i.e. mainspace).
	 * @param int $limit The number of titles to return (default: 1).
	 * @param bool $onlyredirects Only include redirects (true) or only include non-redirects (default; false).
	 * @return array A series of random titles.
	 */
	public function random($namespaces = "0", $limit = 1, $onlyredirects = false) {
		$rnArray = array(
			'code' => 'rn',
			'list' => 'random',
			'rnnamespace' => $namespaces,
			'limit' => $limit,
			'rnredirect' => (is_null($onlyredirects) || !$onlyredirects) ? null : "true",
			'lhtitle' => 'title'
		);
		
		Hooks::runHook( 'PreQueryRandom', array( &$rnArray ) );
		return $this->listHandler($rnArray);
	}
	
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
	
	/**
	 * Returns a unified or HTML diff between two revisions
	 * 
	 * @access public
	 * @param int $rev1 The revision id of the old revision
	 * @param int $rev2 The revision id of the new revision
	 * @param string $method Format of diff. Either 'unified', or 'inline' (HTML diff)
	 * @return string Returned diff
	 */
	public function diff( $rev1, $rev2, $method = 'unified' ) {
		$r1array = array(
			'action' => 'query',
			'prop' => 'revisions',
			'revids' => $rev1,
			'rvprop' => 'content'
		);
		$r2array = array(
			'action' => 'query',
			'prop' => 'revisions',
			'revids' => $rev2,
			'rvprop' => 'content'
		);
		
		$r1 = $this->apiQuery( $r1array );
		$r2 = $this->apiQuery( $r2array );
		
		
		if( isset( $r1['query']['badrevids'] ) || isset( $r2['query']['badrevids'] ) ) {
			
		}
		elseif( !isset( $r1['query']['pages'] ) || !isset( $r2['query']['pages'] ) ) {
			
		}
		else {
			foreach( $r1['query']['pages'] as $r1pages ) {
				$r1text = $r1pages['revisions'][0]['*'];
			}
			foreach( $r2['query']['pages'] as $r2pages ) {
				$r2text = $r2pages['revisions'][0]['*'];
			}
			
			return getTextDiff($method, $r1text, $r2text);
		}
		
		
	}
	
	public function get_tokens( $force = false, $rollback = false ) {
		Hooks::runHook( 'GetTokens', array( &$this->tokens ) );
		
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
	
	/**
	 * Returns extensions.
	 * 
	 * @access public
	 * @see Wiki::$extensions
	 * @return array Extensions in format name => version
	 */
	public function get_extensions() {
		return $this->extensions;
	}
	
	/**
	 * Returns an array of the namespaces used on the current wiki.
	 * 
	 * @access public
	 * @param bool $force Whether or not to force an update of any cached values first.
	 * @return array The namespaces in use in the format index => local name. 
	 */
	public function get_namespaces( $force = false ) {
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
				$this->allowSubpages[$namespace['id']] = ((isset($namespace['subpages'])) ? true : false);
			}
		}
		return $this->namespaces;
	}

	/**
	 * Returns an array of subpage-allowing namespaces.
	 * 
	 * @access public
	 * @param bool $force Whether or not to force an update of any cached values first.
	 * @return array An array of namespaces that allow subpages.
	 */	
	public function get_allow_subpages( $force = false ) {
		if( is_null( $this->allowSubpages ) || $force ) {
			$this->get_namespaces( true );
		}
		return $this->allowSubpages;
	}
	
	/**
	 * Returns a boolean equal to whether or not the namespace with index given allows subpages.
	 * 
	 * @access public
	 * @param int $namespace The namespace that might allow subpages.
	 * @return bool Whether or not that namespace allows subpages.
	 */
	public function get_ns_allows_subpages( $namespace = 0 ) {
		$this->get_allow_subpages();
		
		return (bool) $this->allowSubpages[$namespace];
	}
	
	/**
	 * Returns user rights.
	 * 
	 * @access public
	 * @see Wiki::$userRights
	 * @return array Array of user rights
	 */					
	public function get_userrights() {
		return $this->userRights;
	}

}
