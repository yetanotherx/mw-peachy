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

class Page {

	private $wiki;
	private $title;
	private $pageid;
	private $exists = true;
	private $redirectFollowed = false;
	private $title_wo_namespace;
	private $namespace_id;
	private $content;
	private $templates = array();
	private $protection = array();
	private $categories = array();
	private $images = array();
	private $links = array();
	private $lastedit;
	private $length;
	private $hits;
	private $langlinks = array();
	private $extlinks = array();
	private $starttimestamp;
	
	
	/**
	 * Construction method for the Page class
	 * 
	 * @access public
	 * @param Wiki $wikiClass The Wiki class object
	 * @param mixed $title Title of the page (default: null)
	 * @param mixed $pageid ID of the page (default: null)
	 * @param bool $followRedir Should it follow a redirect when retrieving the page (default: true)
	 * @param bool $normalize Should the class automatically normalize the title (default: true)
	 * @return void
	 */
	function __construct( &$wikiClass, $title = null, $pageid = null, $followRedir = true, $normalize = true ) {
		$this->wiki =& $wikiClass;
		
		if( is_null( $title ) && is_null( $pageid ) ) {
			throw new NoTitle();
		}
		
		if( $normalize ) {
			$title = str_replace( '_', ' ', $title );
			$title = str_replace( '%20', ' ', $title );
			if( $title[0] == ":" ){
				$title = substr($title, 1);
			}
			$chunks = explode( ':', $title, 2 );
			if(count($chunks) != 1){
				$namespace = strtolower( trim( $chunks[0] ) );
				$namespaces = $this->wiki->getNamespaces();
				if( $namespace == $namespaces[-2] || $namespace == "media" ){
					// Media or local variant, translate to File:
					$title = $namespaces[6] . ":" . $chunks[1];
				}
				elseif( $namespace == $namespaces[-1] || $namespace == "special" ){
					//Special or local variant, error
					throw new BadTitle( "Special pages are not currently supported by the API." );
				}
			}
		}
		
		$pageInfoArray = array();
		
		if( !is_null( $pageid ) ) {
			$pageInfoArray['pageids'] = $pageid;
		}
		else {
			$pageInfoArray['titles'] = $title;
		}
		
		if( $followRedir ) $pageInfoArray['redirects'] = '';
		
		$info = $this->get_metadata( $pageInfoArray );
		
		if( isset( $info['invalid'] ) ) throw new BadTitle( $title );
		
		$this->title = $info['title'];
		$this->namespace_id = $info['ns'];
		
		if( $this->namespace_id != 0 ) {
			$this->title_wo_namespace = explode( ':', $this->title, 2 );
			$this->title_wo_namespace = $this->title_wo_namespace[1];
		}
		else {
			$this->title_wo_namespace = $this->title;
		}

	}
	
	/**
	 * Returns page history. Can be specified to return ontent aswell
	 * 
	 * @access public
	 * @param int $count Revisions to return (default: 1)
	 * @param string $dir Direction to return revisions (default: "older")
	 * @param bool $content Should content of that revision be returned aswell (default: false)
	 * @param int $revid Revision ID to start from (default: null)
	 * @param bool $rollback_token Should a rollback token be returned (default: false)
	 * @return array Revision data
	 */
	public function history( $count = 1, $dir = "older", $content = false, $revid = null, $rollback_token = false ) {

		if( !$this->exists ) return array();
		
		$historyArray = array(
			'action' => 'query',
			'prop' => 'revisions',
			'titles' => $this->title, 
			'rvlimit' => $count,
			'rvprop' => 'timestamp|ids|user|comment',
			'rvdir' => $dir,
			
		);
		
		if( $content ) $historyArray['rvprop'] .= "|content";
		
		if( !is_null( $revid ) ) {
			$historyArray['rvstartid'] = $revid;
		}
		
		if( $rollback_token ) $historyArray['rvtoken'] = 'rollback';
		
		$historyResult = $this->wiki->apiQuery($historyArray);
		
		return $historyResult['query']['pages'][$this->pageid]['revisions'];

	}
	
	/**
	 * Retrieves text from a page, or a cached copy unless $force is true
	 * 
	 * @access public
	 * @param bool $force Grab text from the API, don't use the cached copy (default: false)
	 * @return string Page content
	 */
	public function get_text( $force = false ) {
		
		##FIXME: Allow getting sections
		
		if( !$force && !empty($this->content) ) {
			return $this->content;
		}
		
		if( !$this->exists ) return null;
		
		$this->content = $this->history( 1, "older", true );
		$this->content = $this->content[0]['*'];
		
		return $this->content;
	}
	
	/**
	 * Returns the pageid of the page.
	 * 
	 * @return int Pageid
	 */
	public function get_id() {
		return $this->pageid;
	}
	
	/**
	 * Returns if the page exists
	 * 
	 * @access public
	 * @return bool Exists
	 */
	public function exists() {
		return $this->exists;
	}
	
	/**
	 * Returns links on the page.
	 * 
	 * @access public
	 * @link http://www.mediawiki.org/wiki/API:Query_-_Properties#links_.2F_pl
	 * @param bool $force Force use of API, won't use cached copy (default: false)
	 * @return bool|array False on error, array of link titles
	 */
	public function get_links( $force = false ) {

		if( !$force && count( $this->links ) > 0 ) {
			return $this->links;
		}

		if( !$this->exists ) return array();
		
		$endArray = array();
		
		$continue = null;
		
		while( 1 ) {
			$tArray = array(
				'action' => 'query',
				'prop' => 'links',
				'pllimit' => $this->wiki->get_api_limit(),
				'titles' => $this->title,
			);
			
			if( !is_null( $continue ) ) $tArray['plcontinue'] = $continue;
			
				
			$tRes = $this->wiki->apiQuery( $tArray );
				
			if( isset( $tRes['error'] ) ) {
				throw new APIError( array( 'error' => $tRes['error']['code'], 'text' => $tRes['error']['info'] ) );
				return false;
			}
			
			foreach( $tRes['query']['pages'][$this->pageid]['links'] as $x ) {
				$endArray[] = $x['title'];
			}
			
			if( empty( $tRes['query-continue']['links']['plcontinue'] ) ) {
				$this->links = $endArray;
				return $endArray;
			}
			else {
				$continue = $tRes['query-continue']['links']['plcontinue'];
			}
			
			
		}
		
	}
	
	/**
	 * Returns templates on the page
	 * 
	 * @access public
	 * @link http://www.mediawiki.org/wiki/API:Query_-_Properties#templates_.2F_tl
	 * @param bool $force Force use of API, won't use cached copy (default: false)
	 * @return bool|array False on error, array of template titles
	 */
	public function get_templates( $force = false ) {

		if( !$force && count( $this->templates ) > 0 ) {
			return $this->templates;
		}

		if( !$this->exists ) return array();
		
		$endArray = array();
		
		$continue = null;
		
		while( 1 ) {
			$tArray = array(
				'action' => 'query',
				'prop' => 'templates',
				'tllimit' => $this->wiki->get_api_limit(),
				'titles' => $this->title,
			);
			
			if( !is_null( $continue ) ) $tArray['tlcontinue'] = $continue;
			
				
			$tRes = $this->wiki->apiQuery( $tArray );
			 
			if( isset( $tRes['error'] ) ) {
				throw new APIError( array( 'error' => $tRes['error']['code'], 'text' => $tRes['error']['info'] ) );
				return false;
			}
			
			foreach( $tRes['query']['pages'][$this->pageid]['templates'] as $x ) {
				$endArray[] = $x['title'];
			}
			
			if( empty( $tRes['query-continue']['templates']['tlcontinue'] ) ) {
				$this->templates = $endArray;
				return $endArray;
			}
			else {
				$continue = $tRes['query-continue']['templates']['tlcontinue'];
			}
			
			
		}
	}
	
	/**
	 * Returns categories of page
	 * 
	 * @access public
	 * @link http://www.mediawiki.org/wiki/API:Query_-_Properties#categories_.2F_cl
	 * @param bool $force Force use of API, won't use cached copy (default: false)
	 * @return bool|array False on error, returns array of categories
	 */
	public function get_categories( $force = false ) {

		if( !$force && count( $this->categories ) > 0 ) {
			return $this->categories;
		}

		if( !$this->exists ) return array();
		
		$endArray = array();
		
		$continue = null;
		
		while( 1 ) {
			$tArray = array(
				'action' => 'query',
				'prop' => 'categories',
				'cllimit' => $this->wiki->get_api_limit(),
				'titles' => $this->title,
			);
			
			if( !is_null( $continue ) ) $tArray['clcontinue'] = $continue;
			
				
			$tRes = $this->wiki->apiQuery( $tArray );
			
			if( isset( $tRes['error'] ) ) {
				throw new APIError( array( 'error' => $tRes['error']['code'], 'text' => $tRes['error']['info'] ) );
				return false;
			}
			
			foreach( $tRes['query']['pages'][$this->pageid]['categories'] as $x ) {
				$endArray[] = $x['title'];
			}
			
			if( empty( $tRes['query-continue']['categories']['clcontinue'] ) ) {
				$this->categories = $endArray;
				return $endArray;
			}
			else {
				$continue = $tRes['query-continue']['categories']['clcontinue'];
			}
			
			
		}
	}
	
	/**
	 * Returns images used in the page
	 * 
	 * @access public
	 * @link http://www.mediawiki.org/wiki/API:Query_-_Properties#images_.2F_im
	 * @param bool $force Force use of API, won't use cached copy (default: false)
	 * @return bool|array False on error, returns array of image titles
	 */
	public function get_images( $force = false ) {
		global $pgHTTP;
		
		if( !$force && count( $this->images ) > 0 ) {
			return $this->images;
		}

		if( !$this->exists ) return array();
		
		$endArray = array();
		
		$continue = null;
		
		while( 1 ) {
			$tArray = array(
				'action' => 'query',
				'prop' => 'images',
				'imlimit' => $this->wiki->get_api_limit(),
				'titles' => $this->title,
			);
			
			if( !is_null( $continue ) ) $tArray['imcontinue'] = $continue;
			
				
			$tRes = $this->wiki->apiQuery( $tArray );
			  
			if( isset( $tRes['error'] ) ) {
				throw new APIError( array( 'error' => $tRes['error']['code'], 'text' => $tRes['error']['info'] ) );
				return false;
			}
			
			foreach( $tRes['query']['pages'][$this->pageid]['images'] as $x ) {
				$endArray[] = $x['title'];
			}
			
			if( empty( $tRes['query-continue']['images']['imcontinue'] ) ) {
				$this->images = $endArray;
				return $endArray;
			}
			else {
				$continue = $tRes['query-continue']['images']['imcontinue'];
			}
			
			
		}
	}
	
	/**
	 * Returns external links used in the page
	 * 
	 * @access public
	 * @link http://www.mediawiki.org/wiki/API:Query_-_Properties#extlinks_.2F_el
	 * @param bool $force Force use of API, won't use cached copy (default: false)
	 * @return bool|array False on error, returns array of URLs
	 */
	public function get_extlinks( $force = false ) {

		if( !$force && count( $this->extlinks ) > 0 ) {
			return $this->extlinks;
		}

		if( !$this->exists ) return array();
		
		$endArray = array();
		
		$continue = null;
		
		while( 1 ) {
			$tArray = array(
				'action' => 'query',
				'prop' => 'extlinks',
				'ellimit' => $this->wiki->get_api_limit(),
				'titles' => $this->title,
			);
			
			if( !is_null( $continue ) ) $tArray['elcontinue'] = $continue;
			
				
			$tRes = $this->wiki->apiQuery( $tArray );
			

			if( isset( $tRes['error'] ) ) {
				throw new APIError( array( 'error' => $tRes['error']['code'], 'text' => $tRes['error']['info'] ) );
				return false;
			}
			
			foreach( $tRes['query']['pages'][$this->pageid]['extlinks'] as $x ) {
				$endArray[] = $x['*'];
			}
			
			if( empty( $tRes['query-continue']['extlinks']['elcontinue'] ) ) {
				$this->extlinks = $endArray;
				return $endArray;
			}
			else {
				$continue = $tRes['query-continue']['extlinks']['elcontinue'];
			}
			
			
		}
	}
	
	/**
	 * Returns language links on the page
	 * 
	 * @access public
	 * @link http://www.mediawiki.org/wiki/API:Query_-_Properties#langlinks_.2F_ll
	 * @param bool $force Force use of API, won't use cached copy (default: false)
	 * @return bool|array False on error, returns array of links in the form of lang:title
	 */
	public function get_langlinks( $force = false ) {
		if( !$force && count( $this->langlinks ) > 0 ) {
			return $this->langlinks;
		}

		if( !$this->exists ) return array();
		
		$endArray = array();
		
		$continue = null;
		
		while( 1 ) {
			$tArray = array(
				'action' => 'query',
				'prop' => 'langlinks',
				'lllimit' => $this->wiki->get_api_limit(),
				'titles' => $this->title,
			);
			
			if( !is_null( $continue ) ) $tArray['llcontinue'] = $continue;
			
				
			$tRes = $this->wiki->apiQuery( $tArray );
			
			if( isset( $tRes['error'] ) ) {
				throw new APIError( array( 'error' => $tRes['error']['code'], 'text' => $tRes['error']['info'] ) );
				return false;
			}
			
			foreach( $tRes['query']['pages'][$this->pageid]['langlinks'] as $x ) {
				$endArray[] = $x['lang'] . ':' . $x['*'];
			}
			
			if( empty( $tRes['query-continue']['langlinks']['llcontinue'] ) ) {
				$this->extlinks = $endArray;
				return $endArray;
			}
			else {
				$continue = $tRes['query-continue']['langlinks']['llcontinue'];
			}
			
			
		}
	}
	
	/**
	 * Returns the protection level of the page
	 * 
	 * @access public
	 * @link http://www.mediawiki.org/wiki/API:Query_-_Properties#info_.2F_in
	 * @param bool $force Force use of API, won't use cached copy (default: false)
	 * @return bool|array False on error, returns array with protection levels
	 */
	public function get_protection( $force = false ) {

		if( !$force && count( $this->protection ) > 0 ) {
			return $this->protection;
		}

		if( !$this->exists ) return array();
		
		$tArray = array(
			'action' => 'query',
			'prop' => 'info',
			'inprop' => 'protection',
			'titles' => $this->title,
		);
			
		$tRes = $this->wiki->apiQuery( $tArray );
			
		if( isset( $tRes['error'] ) ) {
			throw new APIError( array( 'error' => $tRes['error']['code'], 'text' => $tRes['error']['info'] ) );
			return false;
		}
		
		$this->protection = $tRes['query']['pages'][$this->pageid]['protection'];
		
		return $this->protection;

	}
	
	/**
	 * Edits the page
	 * 
	 * @access public
	 * @link http://www.mediawiki.org/wiki/API:Edit_-_Create%26Edit_pages
	 * @param string $text Text of the page that will be saved
	 * @param string $summary Summary of the edit (default: "")
	 * @param bool $minor Minor edit (default: false)
	 * @param bool $bot Mark as bot edit (default: true)
	 * @param bool $force Override nobots check (default: false)
	 * @param string $pend Set to 'pre' or 'ap' to prepend or append, respectively (default: null)
	 * @param bool $create Set to 'never' or 'only' to never create a new page or only create a new page, respectively (default: false) 
	 */
	public function edit( 
		$text, 
		$summary = "", 
		$minor = false, 
		$bot = true, 
		$force = false,
		$pend = false, 
		$create = false
	)  {
	
		global $pgRunPage;
		
		$tokens = $this->wiki->getTokens();
		
		if( $tokens['edit'] == '+\\' ) {
			throw new EditError( "LoggedOut", "User has logged out" );
		}
		elseif( $tokens['edit'] == '' ) {
			throw new EditError( "PermissionDenied", "User is not allowed to edit {$this->title}" );
		}
		
		if( mb_strlen( $summary, '8bit' ) > 255 ) {
			throw new EditError( "LongSummary", "Summary is over 255 bytes, the maximum allowed" );
		}
		
		pecho( "Making edit to {$this->title}...\n\n", 0 );
		
		$editarray = array(
			'title' => $this->title,
			'action' => 'edit',
			'token' => $tokens['edit'],
			'starttimestamp' => '',
			'basetimestamp' => $this->lastedit,
			'md5' => md5($text),
			'text' => $text,
			'assert' => 'user',
		);
		
		if( $pend == "pre" ) {
			$editarray['prependtext'] = $text;
		}
		elseif( $pend == "ap" ) {
			$editarray['appendtext'] = $text;
		}
		
		if( $create == "never" ) {
			$editarray['nocreate'] = 'yes';
		}
		elseif( $create == "only" ) {
			$editarray['createonly'] = 'yes';
		}
		
		if( $this->wiki->get_maxlag() ) {
			$editarray['maxlag'] = $this->wiki->get_maxlag();
		}
		
		if( !empty( $summary ) ) $editarray['summary'] = $summary;
		
		if( $minor ) {
			$editarray['minor'] = 'yes';
		}
		else {
			$editarray['notminor'] = 'yes';
		}
		
		if( $bot ) {
			$editarray['bot'] = 'yes';
		}
		
		if( !$force ) {
			//Perform nobots checks, login checks, /Run checks
			if( $this->nobots( $text ) ) {
				throw new EditError("Nobots", "The page has a nobots template");
			}
			$die = true;
			Hooks::runHook( 'SoftEditBlock', array( &$editarray, &$die ) );
			if( 1 == 2 && $die ) {
				##FIXME: Die() isn't the right thing to do here, throw an error?
				die();
			}
		}
		
		Hooks::runHook( 'StartEdit', array( &$editarray ) );
		
		$result = $this->wiki->apiQuery( $editarray, true );
		
		if( isset( $result['error'] ) ) {
			throw new EditError( $result['error']['code'], $result['error']['info'] );
		}
		elseif( isset( $result['edit'] ) ) {
			if( $result['edit']['result'] == "Success" ) {
				$this->__construct( $this->wiki, $this->pageid );
				return $result['edit']['newrevid'];
			}
			else {
				throw new EditError( "UnknownEditError", print_r($result['edit'],true));
			}
		}
		else {
			throw new EditError( "UnknownEditError", print_r($result['edit'],true));
		}
	
	}
	
	/**
	 * Detects the presence of a nobots template or one that denies editing by ours
	 * 
	 * @access public
	 * @param string $text Text of the page to check (default: '')
	 * @return bool True on match of an appropriate nobots template
	 */
	public function nobots( $text = '' ) {
		if( !$this->wiki->get_nobots() ) return false;
		
		if( $text == '' ) {
			if( $this->content == '' ) {
				$this->get_text();
			}
			
			$text = $this->content;
		}
		
		return preg_match( '/\{\{(nobots|bots\|allow=none|bots\|deny=all|bots\|optout=all|bots\|deny=.*?'.preg_quote($this->wiki->get_username(),'/').'.*?)\}\}/iS', $text );
	}
	
	public function undo() {}
	
	/**
	 * Returns a boolean depending on whether the page can have subpages or not.
	 * 
	 * @return bool True if subpages allowed
	 */		
	public function allowSubpages() {
		$allows = $this->wiki->getAllowSubpages();
		return $allows[$this->namespace_id];
	}
	
	/**
	 * Returns a boolean depending on whether the page is a discussion (talk) page or not.
	 * 
	 * @return bool True if discussion page, false if not
	 */	
	public function isDiscussion() {
		if($this->namespace_id >= 0 && $this->namespace_id%2 == 1){
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * Returns the title of the discussion (talk) page associated with a page, if it exists.
	 * 
	 * @return string Title of discussion page
	 */	
	public function get_discussion() {
		if($this->namespace_id < 0 || $this->namespace_id === "") {
			// No discussion page exists
			// Guessing we want to error
			throw new BadEntryError("get_discussion","tried to find the discussion page of a page which could never have one");
			return false;
		} else {
			$namespaces = $this->wiki->getNamespaces();
			if($this->isDiscussion()){
				return $namespaces[($this->namespace_id - 1)] . ":" . $this->title_wo_namespace;
			} else {
				return $namespaces[($this->namespace_id + 1)] . ":" . $this->title_wo_namespace;
			}
		}
	}
	
	/**
	 * Moves a page to a new location.
	 * 
	 * @param string $newTitle The new title to which to move the page.
	 * @param string $reason A descriptive reason for the move.
	 * @param bool $movetalk Whether or not to move any associated talk (discussion) page.
	 * @param bool $movesubpages Whether or not to move any subpages.
	 * @param bool $noredirect Whether or not to suppress the leaving of a redirect to the new title at the old title.
	 * @return bool True on success
	 */	
	public function move( $newTitle, $reason = '', $movetalk = true, $movesubpages = true, $noredirect = false ) {
		$tokens = $this->wiki->getTokens();
		
		if( $tokens['move'] == '+\\' ) {
			throw new EditError( "LoggedOut", "User has logged out" );
		}
		elseif( $tokens['move'] == '' ) {
			throw new EditError( "PermissionDenied", "User is not allowed to move {$this->title}" );
		}
		
		if( mb_strlen( $reason, '8bit' ) > 255 ) {
			throw new EditError( "LongReason", "Reason is over 255 bytes, the maximum allowed" );
		}
		
		pecho( "Moving {$this->title} to $newTitle...\n\n", 0 );
		
		$editarray = array(
			'from' => $this->title,
			'to' => $newTitle,
			'action' => 'move',
			'token' => $tokens['edit'],
		);
		
		if( !empty( $reason ) ) $editArray['reason'] = $reason;
	
		if( $movetalk ) $editArray['movetalk'] = 'yes';
		if( $movesubpages ) $editArray['movesubpages'] = 'yes';
		if( $noredirect ) $editArray['noredirect'] = 'yes';
		
		if( $this->wiki->get_maxlag() ) {
			$editarray['maxlag'] = $this->wiki->get_maxlag();
		}
		
		Hooks::runHook( 'StartMove', array( &$editarray ) );
		
		$result = $this->wiki->apiQuery( $editarray, true );
		
		if( isset( $result['error'] ) ) {
			throw new MoveError( $result['error']['code'], $result['error']['info'] );
		}
		elseif( isset( $result['move'] ) ) {
			if( isset( $result['move']['to'] ) ) {
				$this->__construct( $this->wiki, $this->pageid );
				return true;
			}
			else {
				throw new MoveError( "UnknownMoveError", print_r($result['move'],true));
			}
		}
		else {
			throw new MoveError( "UnknownMoveError", print_r($result['move'],true));
		}
	}
	
	public function protect() {}
	
	/**
	 * Deletes the page.
	 * 
	 * @param string $reason A reason for the deletion. Defaults to null (blank).
	 * @return bool True on success
	 */	
	public function delete( $reason = null ) {
	
		if( !in_array( 'delete', $this->wiki->getUserRights() ) ) {
			throw new PermissionsError( "User is not allowed to delete pages" );
			return false;
		}
		
		$tokens = $this->wiki->getTokens();
		
		$editarray = array(
			'action' => 'delete',
			'title' => $this->title,
			'token' => $tokens['delete'],
			'reason' => $reason
		);
		
		Hooks::runHook( 'StartDelete', array( &$editarray ) );
		
		$result = $this->wiki->apiQuery( $editarray, true);
		
		if( isset( $result['error'] ) ) {
			throw new DeleteError( $result['error']['code'], $result['error']['info'] );
		}
		elseif( isset( $result['delete'] ) ) {
			if( isset( $result['delete']['title'] ) ) {
				$this->__construct( $this->wiki, $this->title );
				return true;
			}
			else {
				throw new DeleteError( "UnknownDeleteError", print_r($result['delete'],true));
			}
		}
		else {
			throw new DeleteError( "UnknownDeleteError", print_r($result['delete'],true));
		}
		
	}
	
	public function unprotect() {}
	
	public function undelete( $reason = null, $timestamps = null ) {
		if( !in_array( 'undelete', $this->wiki->getUserRights() ) ) {
			throw new PermissionsError( "User is not allowed to undelete pages" );
			return false;
		}
		
		$tokens = $this->wiki->getTokens();
		
		$undelArray = array(
			'action' => 'undelete',
			'title' => $this->title,
			'token' => $tokens['edit'], //Using the edit token, it's the exact same, and we don't have to do another API call
			'reason' => $reason
		);
		
		if( !is_null( $timestamps ) ) {
			$undelArray['timestamps'] = $timestamps;
			if( is_array( $timestamps ) ) {
				$undelArray['timestamps'] = implode('|',$timestamps);
			}
		}
		
		Hooks::runHook( 'StartUndelete', array( &$undelArray ) );
		
		$result = $this->wiki->apiQuery( $undelArray, true);
		
		if( isset( $result['error'] ) ) {
			throw new UndeleteError( $result['error']['code'], $result['error']['info'] );
		}
		elseif( isset( $result['undelete'] ) ) {
			if( isset( $result['undelete']['title'] ) ) {
				$this->__construct( $this->wiki, $this->title );
				return true;
			}
			else {
				throw new UndeleteError( "UnknownUndeleteError", print_r($result['undelete'],true));
			}
		}
		else {
			throw new UndeleteError( "UnknownUndeleteError", print_r($result['undelete'],true));
		}
	}
	
	public function deletedrevs( $content = false, $user = null, $excludeuser = null, $start = null, $end = null, $dir = 'older', $prop = array( 'revid', 'user', 'parsedcomment', 'minor', 'len', 'content', 'token' ) ) {
		if( !in_array( 'deletedhistory', $this->wiki->getUserRights() ) ) {
			throw new PermissionsError( "User is not allowed to view deleted revisions" );
			return false;
		}
		
		if( $content ) $prop[] = 'content';
		
		$drArray = array(
			'code' => 'dr',
			'list' => 'deletedrevs',
			'titles' => $this->title,
			'drprop' => implode( '|', $prop ),
			'drdir' => $dir
		);
		
		if( !is_null( $user ) ) $drArray['druser'] = $user;
		if( !is_null( $excludeuser ) ) $drArray['drexcludeuser'] = $excludeuser;
		if( !is_null( $start ) ) $drArray['drstart'] = $start;
		if( !is_null( $end ) ) $drArray['drend'] = $end;
		
		return $this->wiki->listHandler( $drArray );
	}
	
	public function prefixindex() {}
	
	/**
	 * Returns all of titles on which the page is transcluded ("embedded in").
	 * 
	 * @param string $namespace A pipe '|' separated list of namespace numbers to check. Default null (all). 
	 * @param int $limit A hard limit on the number of transclusions to fetch. Default null (all). 
	 * @return array Titles of pages that transclude this page
	 */
	public function get_transclusions( $namespace = null, $limit = null ) {
		
		pecho( "Getting transclusions of {$this->title}...\n\n", 0 );

		$trArray = array(
			'code' => 'ei',
			'eititle' => $this->title,
			'action' => 'query',
			'list' => 'embeddedin',
			'limit' => $limit,
			'assert' => 'user',
			'lhtitle' => 'title'
		);
		
		if(!is_null($namespace)){
			$trArray['einamespace'] = $namespace;
		}
		
		$result = $this->wiki->listHandler($trArray);
		return $result;
    }

	/**
	 * Adds the page to the logged in user's watchlist
	 * 
	 * @return bool True on success
	 */		
	public function watch() {
		
		Hooks::runHook( 'StartWatch' );
		
		$result = $this->wiki->apiQuery( array(
			'action' => 'watch',
			'title' => $this->title,
		), true );
		
		if( isset( $result['error'] ) ) {
			throw new APIError( $result['error'] );
		}
		elseif( isset( $result['watch'] ) ) {
			if( isset( $result['watch']['watched'] ) ) {
				return true;
			}
			else {
				throw new APIError( "UnknownWatchError", print_r($result['watch'],true));
			}
		}
		else {
			throw new APIError( "UnknownWatchError", print_r($result['watch'],true));
		}
		
	}
	
	/**
	 * Removes the page to the logged in user's watchlist
	 * 
	 * @return bool True on sucecess
	 */	
	public function unwatch() {
		Hooks::runHook( 'StartUnwatch' );
		
		$result = $this->wiki->apiQuery( array(
			'action' => 'watch',
			'title' => $this->title,
			'unwatch' => 'yes'
		), true );
		
		if( isset( $result['error'] ) ) {
			throw new APIError( $result['error'] );
		}
		elseif( isset( $result['watch'] ) ) {
			if( isset( $result['watch']['unwatched'] ) ) {
				return true;
			}
			else {
				throw new APIError( "UnknownUnwatchError", print_r($result['watch'],true));
			}
		}
		else {
			throw new APIError( "UnknownUnwatchError", print_r($result['watch'],true));
		}
		
	}
	
	/**
	 * Returns the page title
	 * 
	 * @param bool $namespace Set to true to return the title with namespace, false to return it without the namespace. Default true. 
	 * @return string Page title
	 */
	public function get_title( $namespace = true ) {
		if( !$namespace ) {
			return $this->title_wo_namespace;
		}
		return $this->title;
	}
	
	/**
	 * Returns whether or not a redirect was followed to get to the real page title
	 * 
	 * @return bool
	 */
	public function redirectFollowed() {
		return $this->redirectFollowed;
	}
	
	/**
	 * Gets ID or name of the namespace
	 * 
	 * @param bool $id Set to true to get namespace ID, set to false to get namespace name. Default true
	 * @return int|string
	 */
	public function get_namespace( $id = true ) {
		if( $id ) {
			return $this->namespace_id;
		}
		else {
			$namespaces = $this->wiki->getNamespaces();
			
			return $namespaces[$this->namespace_id];
		}
	}
	
	/**
	 * Returns number of hits the page has received
	 * 
	 * @return int
	 */
	public function get_lastedit( $force = false ) {
		if( $force ) $this->get_metadata();
		
		return $this->lastedit;
	}
	
	/**
	 * Returns length of the page
	 * 
	 * @return int
	 */
	public function get_length( $force = false ) {
		if( $force ) $this->get_metadata();
		
		return $this->length;
	}
	
	/**
	 * Returns number of hits the page has received
	 * 
	 * @return int
	 */
	public function get_hits( $force = false ) {
		if( $force ) $this->get_metadata();
		
		return $this->hits;
	}
	
	/**   
	 * Regenerates lastedit, length, and hits
	 * 
	 * @param array $pageInfoArray2 Array of values to merge with defaults (default: null)
	 * @return array Information gathered
	 * @access private
	 */
	private function get_metadata( $pageInfoArray2 = null ) {
		$pageInfoArray = array(
			'action' => 'query',
			'prop' => "info",
			'intoken' => 'edit|delete|protect|move|block|unblock|email|import'
		);
		
		if( $pageInfoArray2 != null ) {
			$pageInfoArray = array_merge($pageInfoArray, $pageInfoArray2);
		} else {
			$pageInfoArray['titles'] = $this->title;
		}
		
		$pageInfoRes = $this->wiki->apiQuery($pageInfoArray);
		
		foreach( $pageInfoRes['query']['pages'] as $key => $info ) {
			$this->pageid = $key;
			if( $this->pageid > 0 ) {
				$this->exists = true;
			}
			else {
				$this->pageid = 0;
			}
			
			if( isset( $info['missing'] ) ) $this->exists = false;

			$this->lastedit = $info['touched'];
			$this->hits = $info['counter'];
			$this->length = $info['length'];
			$this->starttimestamp = $info['starttimestamp'];
			
			return $info;
		}
	}

}