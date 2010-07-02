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
 * Script object
 */

/**
 * Script class, contains methods that make writing scripts easier
 */
class Script {
	protected $wiki;
	protected $args;
	protected $list;
	
	function __construct( $argv, $argfunctions = array() ) {
		$this->parseArgs( $argv, $argfunctions );
		
		global $IP;
		$IP = dirname(__FILE__) . '/';
		require_once( $IP . 'Init.php' );

		if( isset( $this->args['config'] ) ) {
			$this->wiki = Peachy::newWiki( $this->args['config'] );
		}
		else {
			if( isset( $this->args['username'] ) ) {
				if( isset( $this->args['password'] ) ) {
					if( isset( $this->args['baseurl'] ) ) {
						$this->wiki = Peachy::newWiki( null, $this->args['username'], $this->args['password'], $this->args['baseurl'] );
					}
					else {
						$this->wiki = Peachy::newWiki( null, $this->args['username'], $this->args['password'] );
					}
				}
				else {
					if( isset( $this->args['baseurl'] ) ) {
						$this->wiki = Peachy::newWiki( null, $this->args['username'], null, $this->args['baseurl'] );
					}
					else {
						$this->wiki = Peachy::newWiki( null, $this->args['username'] );
					}
				}
			}
			else {
				if( isset( $this->args['baseurl'] ) ) {
					$this->wiki = Peachy::newWiki( null, null, null, $this->args['baseurl'] );
				}
				else {
					$this->wiki = Peachy::newWiki();
				}
			}
		}
		
		$this->makeList();
		
		if( count( $argfunctions ) ) {
			$this->runArgs( $argfunctions );
		}
	}
	
	protected function parseArgs( $args ) {
		foreach( $args as $arg ) {
			$tmp = explode( ':', $arg, 2 );
			if( $arg[0] == "-" ) $this->args[ substr( $tmp[0], 1 ) ] = $tmp[1];
		}
	}
	
	protected function runArgs( $argfunctions ) {
		foreach( $argfunctions as $arg => $callback ) {
			if( is_callable( $callback ) ) {
				call_user_func_array( $callback, $this->args[$arg] );
			}
		}
	}
	
	protected function makeList() {
		if( isset( $this->args['xml'] ) ) {
			Peachy::loadPlugin( 'xml' );
			
			$this->list = XML::load( file_get_contents( $this->args['xml'] ) );
		}
		
	}
}







