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
 * Main Peachy file
 * Defines constants, initializes global variables
 * Stores Peachy class
 */

/**
 * The version that Peachy is running 
 */
define( 'PEACHYVERSION', '0.1alpha' );

/**
 * Minimum MediaWiki version that is required for Peachy 
 */
define( 'MINMW', '1.15' );

/**
 * PECHO constants, used for {@link outputText}()
 */
define( 'PECHO_NORMAL', 0 );

/**
 * PECHO constants, used for {@link outputText}()
 */
define( 'PECHO_NOTICE', 1 );

/**
 * PECHO constants, used for {@link outputText}()
 */
define( 'PECHO_WARN', 2 );

/**
 * PECHO constants, used for {@link outputText}()
 */
define( 'PECHO_ERROR', 3 );

/**
 * PECHO constants, used for {@link outputText}()
 */
define( 'PECHO_FATAL', 4 );

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
$pgVerbose = array(0,1,2,3,4);
$pgUA = 'Peachy MediaWiki Bot API Version ' . PEACHYVERSION;
$pgPechoTypes = array(
	'NORMAL',
	'NOTICE',
	'WARN',
	'ERROR',
	'FATAL'
);

$pgHTTP = new HTTP;

$mwVersion = null;

/**
 * Base Peachy class, used to generate all other classes
 */
class Peachy {

	/**
	 * Initializes Peachy, logs in with a either configuration file or a given username and password
	 * 
	 * @static
	 * @access public
	 * @param string $config_name Name of the config file stored in the Configs directory, minus the .cfg extension. Default null
	 * @param string $username Username to log in if no config file specified. Default null
	 * @param string $password Password to log in with if no config file specified. Default null
	 * @param string $base_url URL to api.php if no config file specified. Defaults to English Wikipedia's API.
	 * @return Wiki Instance of the Wiki class, where most functions are stored
	 */
	public static function newWiki( $config_name = null, $username = null, $password = null, $base_url = 'http://en.wikipedia.org/w/api.php' ) {
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
		
		$extensions = Peachy::wikiChecks( $config_params['baseurl'] );
		
		Hooks::runHook( 'StartLogin', array( &$config_params, &$extensions ) );
		
		if( !isset( $config_params['username'] ) || 
			!isset( $config_params['password'] ) ||
			!isset( $config_params['baseurl'] ) ) {
			throw new LoginError( array( "MissingParam", "Either the username, password, or baseurl parameter was not set." ) );
		}
		
		$w = new Wiki( $config_params, $extensions, false, null );
		return $w;
	}
	
	/**
	 * Performs various checks and settings
	 * Checks if MW version is at least {@link MINMW}
	 * 
	 * @static
	 * @access public
	 * @param string $base_url URL to api.php
	 * @return array Installed extensions
	 */
	public static function wikiChecks( $base_url ) {
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
			throw new DependencyError( "MediaWiki " . MINMW, "http://mediawiki.org" );
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
	
	/**
	 * Loads a specific plugin into memory
	 * 
	 * @static
	 * @access public
	 * @param string $plugin_name Name of plugin to load from Plugins directory, minus .php ending
	 * @return void
	 */
	public static function loadPlugin( $plugin_name ) {
		global $IP;
		if( is_file( $IP . 'Plugins/' . $plugin_name . '.php' ) ) {
		
			Hooks::runHook( 'LoadPlugin', array( &$plugin_name ) );
			
			require_once( $IP . 'Plugins/' . $plugin_name . '.php' );
		}
	}
	
	/**
	 * Loads all available plugins
	 * 
	 * @static
	 * @access public
	 * @return void
	 */
	public static function loadAllPlugins() {
		global $IP;
		
		foreach( glob( $IP . 'Plugins/*.php' ) as $plugin ) {
			require_once( $plugin );
		}
	}
	
	
}
