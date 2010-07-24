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
 * Stores general functions that do not belong in a class
 */

/**
 * Case insensitive in_array function
 * 
 * @param mixed $needle What to search for
 * @param array $haystack Array to search in
 * @return bool True if $needle is found in $haystack, case insensitive
 * @link http://us3.php.net/in_array
 */
function iin_array( $needle, $haystack ) {
	return in_array( strtoupper( $needle ), array_map( 'strtoupper', $haystack ) );
}

/**
 * Returns whether or not a string is found in another
 * Shortcut for strpos()
 * 
 * @param string $needle What to search for
 * @param string $haystack What to search in
 * @param bool Whether or not to do a case-insensitive search
 * @return bool True if $needle is found in $haystack
 * @link http://us3.php.net/strpos
 */
function in_string( $needle, $haystack, $insensitive = false ) {
	$fnc = 'strpos';
	if( $insensitive ) $fnc = 'stripos';
	
	return $fnc( $haystack, $needle ) !== false; 
}

/**
 * Detects the presence of a nobots template or one that denies editing by ours
 * 
 * @access public
 * @param Wiki &$wiki Wiki class
 * @param string $text Text of the page to check (default: '')
 * @param string $username Username to search for in the template (default: null)
 * @param string $optout Text to search for in the optout= parameter. (default: null)
 * @return bool True on match of an appropriate nobots template
 */
function checkExclusion( &$wiki, $text = '', $username = null, $optout = null ) {
	if( !$wiki->get_nobots() ) return false;
	
	if( in_string( "{{nobots}}", $text ) ) return true;
	if( in_string( "{{bots}}", $text ) ) return false;
	
	if( preg_match( '/\{\{bots\s*\|\s*allow\s*=\s*(.*?)\s*\}\}/i', $text, $allow ) ) {
		if( $allow[1] == "all" ) return false;
		if( $allow[1] == "none" ) return true;
		$allow = explode(',', $allow[1]);
		if( !is_null($username) && in_array( $username, $allow ) ) {
			return false;
		}
		return true;
	}
	
	if( preg_match( '/\{\{bots\s*\|\s*deny\s*=\s*(.*?)\s*\}\}/i', $text, $deny ) ) {
		if( $deny[1] == "all" ) return true;
		if( $deny[1] == "none" ) return false;
		$deny = explode(',', $deny[1]);
		if( !is_null($username) && in_array( $username, $deny ) ) {
			return true;
		}
		return false;
	}
	
	if( !is_null( $optout ) && preg_match( '/\{\{bots\s*\|\s*optout\s*=\s*(.*?)\s*\}\}/i', $text, $allow ) ) {
		if( $allow[1] == "all" ) return true;
		$allow = explode(',', $allow[1]);
		if( in_array( $optout, $allow ) ) {
			return true;
		}
		return false;
	}
}

/**
 * Outputs text if the given category is in the allowed types
 * 
 * @param string $text Text to display
 * @param int $cat Category of text, such as PECHO_WARN, PECHO_NORMAL
 * @return void
 */
function outputText( $text, $cat = 0 ) {
	global $pgVerbose;
	
	Hooks::runHook( 'OutputText', array( &$text, &$cat ) );
	
	if( in_array( $cat, $pgVerbose ) ) echo $text;
}

/**
 * Shortcut for {@link outputText}
 * 
 * @param string $text Text to display
 * @param int $cat Category of text, such as PECHO_WARN, PECHO_NORMAL
 * @link outputText
 * @return void
 */
function pecho( $text, $cat = 0 ) {
	outputText( $text, $cat );
}

/**
 * Gets the first defined Wiki object
 * 
 * @return Wiki|bool
 */
function &getSiteObject() {

	foreach( $GLOBALS as $var ) {
		if( is_object( $var ) ) {
			if( get_class( $var ) == "Wiki" ) {
				return $var;
			}
		}
	}
	
	return false;
}

/**
 * Returns an instance of the Page class as specified by $title or $pageid
 * 
 * @param mixed $title Title of the page (default: null)
 * @param mixed $pageid ID of the page (default: null)
 * @param bool $followRedir Should it follow a redirect when retrieving the page (default: true)
 * @param bool $normalize Should the class automatically normalize the title (default: true)
 * @return Page
 */
function &initPage( $title = null, $pageid = null, $followRedir = true, $normalize = true ) {
	$wiki = getSiteObject();
	if( !$wiki ) return false;
	
	$page = new Page( &$wiki, $title, $pageid, $followRedir, $normalize );
	return $page;
}

/**
 * Returns an instance of the User class as specified by $username
 * 
 * @param mixed $username Username
 * @return User
 */
function &initUser( $username ) {
	$wiki = getSiteObject();
	if( !$wiki ) return false;
	
	$user = new User( &$wiki, $username );
	return $user;
}

/**
 * Returns an instance of the Image class as specified by $filename or $pageid
 * 
 * @param string $filename Filename
 * @return Image
 */
function &initImage( $filename = null ) {
	
	$wiki = getSiteObject();
	if( !$wiki ) return false;
	
	$image = new Image( &$wiki, $filename );
	return $image;
}

if ( !function_exists( 'mb_strlen' ) ) {
	
	/**
	 * Fallback implementation of mb_strlen. 
	 *
	 * @link http://svn.wikimedia.org/svnroot/mediawiki/trunk/phase3/includes/GlobalFunctions.php
	 * @param string $str String to get
	 * @return int
	 */
	function mb_strlen( $str ) {
		$counts = count_chars( $str );
		$total = 0;
		
		// Count ASCII bytes
		for( $i = 0; $i < 0x80; $i++ ) {
			$total += $counts[$i];
		}
		
		// Count multibyte sequence heads
		for( $i = 0xc0; $i < 0xff; $i++ ) {
			$total += $counts[$i];
		}
		
		return $total;
	}
} 

if ( !function_exists( 'mb_substr' ) ) {
	/**
	 * Fallback implementation for mb_substr. This is VERY slow, from 5x to 100x slower. Use only if necessary.
	 * @link http://svn.wikimedia.org/svnroot/mediawiki/trunk/phase3/includes/GlobalFunctions.php
	 */
	function mb_substr( $str, $start, $count = 'end' ) {
		if( $start != 0 ) {
			$split = mb_substr_split_unicode( $str, intval( $start ) );
			$str = substr( $str, $split );
		}
		
		if( $count !== 'end' ) {
			$split = mb_substr_split_unicode( $str, intval( $count ) );
			$str = substr( $str, 0, $split );
		}
		
		return $str;
	}
	
	function mb_substr_split_unicode( $str, $splitPos ) {
		if( $splitPos == 0 ) {
			return 0;
		}
		
		$byteLen = strlen( $str );
		
		if( $splitPos > 0 ) {
			if( $splitPos > 256 ) {
				// Optimize large string offsets by skipping ahead N bytes.
				// This will cut out most of our slow time on Latin-based text,
				// and 1/2 to 1/3 on East European and Asian scripts.
				$bytePos = $splitPos;
				while ($bytePos < $byteLen && $str{$bytePos} >= "\x80" && $str{$bytePos} < "\xc0")
					++$bytePos;
				$charPos = mb_strlen( substr( $str, 0, $bytePos ) );
			} else {
				$charPos = 0;
				$bytePos = 0;
			}
			
			while( $charPos++ < $splitPos ) {
				++$bytePos;
				// Move past any tail bytes
				while ($bytePos < $byteLen && $str{$bytePos} >= "\x80" && $str{$bytePos} < "\xc0")
					++$bytePos;
			}
		} else {
			$splitPosX = $splitPos + 1;
			$charPos = 0; // relative to end of string; we don't care about the actual char position here
			$bytePos = $byteLen;
			while( $bytePos > 0 && $charPos-- >= $splitPosX ) {
				--$bytePos;
				// Move past any tail bytes
				while ($bytePos > 0 && $str{$bytePos} >= "\x80" && $str{$bytePos} < "\xc0")
					--$bytePos;
			}
		}
		
		return $bytePos;
	}
}

/**
 * Called when a non-existant class is initiated, loads a plugin if it exists.
 * 
 * @param string $class_name Plugin name to load
 * @return void
 */
function __autoload( $class_name ) {
	global $IP, $pgAutoloader;
	
	if( $class_name == "ImageModify" ) {
		require_once( $IP . 'Plugins/image.php' );
	}
	
	if( is_file( $IP . 'Plugins/' . strtolower( $class_name ) . '.php' ) ) {
		Hooks::runHook( 'LoadPlugin', array( &$class_name ) );
				
		require_once( $IP . 'Plugins/' . strtolower( $class_name ) . '.php' );
	}
	
	if( isset( $pgAutoloader[$class_name] ) ) {
		require_once( $IP . $pgAutoloader[$class_name] );
	}
	
}

