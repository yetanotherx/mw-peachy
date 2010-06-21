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