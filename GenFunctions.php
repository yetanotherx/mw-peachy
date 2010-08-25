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
function iin_array( $needle, $haystack, $strict = false ) {
	if( is_string( $needle ) ) 
		return in_array( strtoupper( $needle ), array_map( 'strtoupper', $haystack ), $strict );
	
	return in_array( $needle, $haystack, $strict );
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
 * Recursive in_array function
 * 
 * @param string $needle What to search for
 * @param string $haystack What to search in
 * @param bool Whether or not to do a case-insensitive search
 * @return bool True if $needle is found in $haystack
 * @link http://us3.php.net/in_array
 */
function in_array_recursive( $needle, $haystack, $insensitive = false ) {
	$fnc = 'in_array';
	if( $insensitive ) $fnc = 'iin_array';
	
	if( $fnc( $needle, $haystack ) ) return true;
	foreach( $haystack as $key => $val ) {
		if( is_array( $val ) ) {
			return in_array_recursive( $needle, $val );
		}
	}
	return false;
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
	
	$page = new Page( $wiki, $title, $pageid, $followRedir, $normalize );
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
	
	$user = new User( $wiki, $username );
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
	
	$image = new Image( $wiki, $filename );
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

if( !function_exists('iconv') ) {
	# iconv support is not in the default configuration and so may not be present.
	# Assume will only ever use utf-8 and iso-8859-1.
	# This will *not* work in all circumstances.
	function iconv( $from, $to, $string ) {
		if ( substr( $to, -8 ) == '//IGNORE' ) $to = substr( $to, 0, strlen( $to ) - 8 );
		if(strcasecmp( $from, $to ) == 0) return $string;
		if(strcasecmp( $from, 'utf-8' ) == 0) return utf8_decode( $string );
		if(strcasecmp( $to, 'utf-8' ) == 0) return utf8_encode( $string );
		return $string;
	}
}

if ( !function_exists( 'istainted' ) ) {
	function istainted( $var ) {
		return 0;
	}
	function taint( $var, $level = 0 ) {}
	function untaint( $var, $level = 0 ) {}
	define( 'TC_HTML', 1 );
	define( 'TC_SHELL', 1 );
	define( 'TC_MYSQL', 1 );
	define( 'TC_PCRE', 1 );
	define( 'TC_SELF', 1 );
}


/**
 * Called when a non-existant class is initiated, loads a plugin if it exists.
 * 
 * @param string $class_name Plugin name to load
 * @return void
 */
function __autoload( $class_name ) {
	global $pgIP, $pgAutoloader;
	
	if( is_file( $pgIP . 'Plugins/' . strtolower( $class_name ) . '.php' ) ) {
		Hooks::runHook( 'LoadPlugin', array( &$class_name ) );
				
		require_once( $pgIP . 'Plugins/' . strtolower( $class_name ) . '.php' );
	}
	
	if( isset( $pgAutoloader[$class_name] ) ) {
		require_once( $pgIP . $pgAutoloader[$class_name] );
	}
	
}

/**
 * Recursive glob() function.
 * 
 * @access public
 * @param string $pattern. (default: '*')
 * @param int $flags. (default: 0)
 * @param string $path. (default: '')
 * @return void
 */
function rglob($pattern='*', $flags = 0, $path='') {
    $paths=glob($path.'*', GLOB_MARK|GLOB_ONLYDIR|GLOB_NOSORT);
    $files=glob($path.$pattern, $flags);
    foreach ($paths as $path) { $files=array_merge($files,rglob($pattern, $flags, $path)); }
    return $files;
}

/**
 * Echo function with color capabilities.
 * 
 * Syntax:
 *
 * <i>[Text to colorize|NAME] where NAME is the name of a defined style.</i> For example:
 * 
 * <i>This text is standard terminal color. [This text will be yellow.|COMMENT] [This text will be white on red.|ERROR]</i>
 * 
 * Defined styles:
 * <ul>
 * <li>ERROR: White on red, bold</li>
 * <li>INFO: Green text, bold</li>
 * <li>PARAMETER: Cyan text</li>
 * <li>COMMENT: Yellow text</li>
 * <li>GREEN_BAR: White on green, bold</li>
 * <li>RED_BAR: White on red, bold</li>
 * <li>INFO_BAR: Cyan text, bold</li>
 * </ul>
 *
 * You can define your own styles by using this syntax:
 *
 *   <code>lime_colorizer::style('STYLE_NAME', array('bg' => 'red', 'fg' => 'white'));</code>
 *
 * (Available colors: black, red, green, yellow, blue, magenta, cyan, white)
 * 
 * You can also set options for how the text is formatted (not available on all systems):
 *
 *   <code>lime_colorizer::style('STYLE_NAME', array('bg' => 'red', 'fg' => 'white', 'bold' => true ));</code> (sets bold text)
 *
 * Available options: bold, underscore, blink, reverse, conceal
 *  
 * 
 * @access public
 * @param mixed $text
 * @return void
 */
function cecho( $text ) {
	global $pgColorizer;
	
	if( !isset( $pgColorizer ) ) $pgColorizer = new lime_colorizer( true );
	
	echo preg_replace('/\[(.+?)\|(\w+)\]/se', '$pgColorizer->colorize("$1", "$2")', $text);
	
}


/**
 * Generates a diff between two strings
 * 
 * @param string $method Which style of diff to generate: unified, inline (HTML), context, threeway
 * @param string $diff1 Old text
 * @param string $diff2 New text
 * @param string $diff3 New text #2 (if in three-way mode)
 * @return string Generated diff
 * @link http://pear.php.net/package/Text_Diff/redirected
 */
function getTextDiff($method, $diff1, $diff2, $diff3 = null) {
	switch ($method) {
		case 'unified':
			$diff = new Text_Diff('auto', array(explode("\n",$diff1), explode("\n",$diff2)));

			$renderer = new Text_Diff_Renderer_unified();
			
			$diff = $renderer->render($diff);
			break;
		case 'inline':
			$diff = new Text_Diff('auto', array(explode("\n",$diff1), explode("\n",$diff2)));

			$renderer = new Text_Diff_Renderer_inline();
			
			$diff = $renderer->render($diff);
			break;
		case 'context':
			$diff = new Text_Diff('auto', array(explode("\n",$diff1), explode("\n",$diff2)));

			$renderer = new Text_Diff_Renderer_context();
			
			$diff = $renderer->render($diff);
			break;
		case 'threeway':
			$diff = new Text_Diff3( explode("\n",$diff1), explode("\n",$diff2), explode("\n",$diff3) );
			$diff = implode( "\n", $diff->mergedOutput() );
			$rendered = null;
	}
	unset($renderer);
	return $diff;
}
