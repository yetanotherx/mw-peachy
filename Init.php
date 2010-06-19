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

define( 'PEACHY', true );
define( 'PEACHYVERSION', '0.1alpha' );
define( 'MINMW', '1.15' );

define( 'PECHO_NORMAL', 0 );
define( 'PECHO_NOTICE', 1 );
define( 'PECHO_WARN', 2 );
define( 'PECHO_ERORR', 2 );
define( 'PECHO_FATAL', 3 );

$version = explode( '.', phpversion() );

if( $version[0] < '5' ) die( "PHP 5 or higher is require_onced to use Peachy.\n" );

$IP = dirname(__FILE__) . '/';

require_once( $IP . 'Exceptions.php' );
require_once( $IP . 'GenFunctions.php' );
require_once( $IP . 'Diff/Diff.php' );
require_once( $IP . 'Wiki.php' );
require_once( $IP . 'Image.php' );
require_once( $IP . 'Hooks.php' );
require_once( $IP . 'Page.php' );
require_once( $IP . 'User.php' );
require_once( $IP . 'HTTP.php' );

$pgProxy = array();
$pgHTTPEcho = false;
$pgRunPage = null;
$pgVerbose = array(0,1,2,3,4);
$pgUA = 'Peachy MediaWiki Bot API Version ' . PEACHYVERSION;
$pgPechoTypes = array(
	'NORMAL',
	'NOTICE',
	'WARN',
	'ERROR',
	'FAT'
);

$pgHTTP = new HTTP;

$mwVersion = null;

class Peachy {
	static function newWiki( $config_name = null, $username = null, $password = null, $base_url = 'http://en.wikipedia.org/w/api.php' ) {
		global $IP;
		
		//throw new APIError( array( 'code' => "nopage", 'text' => "nopage exists" ) );
		if( !is_null( $config_name ) ) {
			$config_params = parse_ini_file( $IP . 'Configs/' . $config_name . '.cfg' );
		}
		else {
			$config_params = array(
				'username' => $username,
				'password' => $password,
				'baseurl' => $base_url
			);
		}
		
		$extensions = Peachy::wikiChecks( $base_url );
		
		Hooks::runHook( 'StartLogin', array( &$config_params, &$extensions ) );
		
		if( !isset( $config_params['username'] ) || 
			!isset( $config_params['password'] ) ||
			!isset( $config_params['baseurl'] ) ) {
			throw new LoginError( array( "MissingParam", "Either the username, password, or baseurl parameter was not set." ) );
		}
		
		$w = new Wiki( $config_params, $extensions, false, null );
		return $w;
	}
	
	static function wikiChecks( $base_url ) {
		global $pgHTTP, $mwVersion;
		
		$siteinfo = unserialize( $pgHTTP->get( 
			$base_url,
			 array( 'action' => 'query',
				'meta' => 'siteinfo',
				'format' => 'php',
				'siprop' => 'extensions|general',
		)));
		
		$version = preg_replace( '/[^0-9\.]/','',str_replace('MediaWiki ', '', $siteinfo['query']['general']['generator'] ));
		
		if( !version_compare( $version, MINMW ) ) {
			throw new DependencyError( "MediaWiki 1.12", "http://mediawiki.org" );
		}
		
		$mwVersion = $version;
		
		$extensions = array();
		
		foreach( $siteinfo['query']['extensions'] as $ext ) {
			if( isset( $ext['version'] ) ) {
				$extensions[$ext['name']] = $ext['version'];
			}
			else {
				$extensions[$ext['name']] = '';
			}
		}
		
		return $extensions;
	}
	
	static function loadPlugin( $plugin_name ) {
		global $IP;
		if( is_file( $IP . 'Plugins/' . $plugin_name . '.php' ) ) {
		
			Hooks::runHook( 'LoadPlugin', array( &$plugin_name ) );
			
			require_once( $IP . 'Plugins/' . $plugin_name . '.php' );
		}
	}
	
	static function loadAllPlugins() {
		global $IP;
		
		foreach( glob( $IP . 'Plugins/*.php' ) as $plugin ) {
			require_once( $plugin );
		}
	}
	
	
}
