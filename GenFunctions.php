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

function iin_array( $needle, $haystack ) {
	return in_array( strtoupper( $needle ), array_map( 'strtoupper', $haystack ) );
}

function in_string( $needle, $haystack ) {
	return strpos( $haystack, $needle ) !== false; 
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
function checkExclusion( $text = '', $username = null, $optout = null ) {
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

/*

0 = normal
1 = notice
2 = warning
2 = error
3 = fatal error

*/
function outputText( $text, $cat = 0 ) {
	global $pgVerbose;
	
	Hooks::runHook( 'OutputText', array( &$text, &$cat ) );
	
	if( in_array( $cat, $pgVerbose ) ) echo $text;
}

function pecho( $text, $cat = 0 ) {
	outputText( $text, $cat );
}