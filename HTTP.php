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
 * HTTP object
 * Stores all cURL functions
 */

class HTTP {
	
	/**
	 * Curl object
	 * 
	 * @var cURL
	 * @access private
	 */
	private $curl_instance;
	
	/**
	 * Hash to use for cookies
	 * 
	 * @var string
	 * @access private
	 */
	private $cookie_hash;
	
	/**
	 * Construction method for the HTTP class
	 * 
	 * @access public
	 * @return void
	 */	
	function __construct() {
		global $pgUA;
		
		if( !function_exists( 'curl_init' ) ) {
			throw new DependencyError( "cURL", "http://us2.php.net/manual/en/curl.requirements.php" );
		}
		
		$this->curl_instance = curl_init();
		$this->cookie_hash = md5( time() . '-' . rand( 0, 999 ) );
		
		curl_setopt($this->curl_instance,CURLOPT_COOKIEJAR,'/tmp/peachy.cookies.'.$this->cookie_hash.'.dat');
		curl_setopt($this->curl_instance,CURLOPT_COOKIEFILE,'/tmp/peachy.cookies.'.$this->cookie_hash.'.dat');
		curl_setopt($this->curl_instance,CURLOPT_MAXCONNECTS,100);
		curl_setopt($this->curl_instance,CURLOPT_CLOSEPOLICY,CURLCLOSEPOLICY_LEAST_RECENTLY_USED);
		curl_setopt($this->curl_instance,CURLOPT_MAXREDIRS,10);
		curl_setopt($this->curl_instance,CURLOPT_HTTPHEADER, array('Expect:'));
		curl_setopt($this->curl_instance,CURLOPT_RETURNTRANSFER,1);
		curl_setopt($this->curl_instance,CURLOPT_TIMEOUT,30);
		curl_setopt($this->curl_instance,CURLOPT_CONNECTTIMEOUT,10);
		curl_setopt($this->curl_instance,CURLOPT_USERAGENT, $pgUA);

		##FIXME: Allow for logging in with a saved cookie, to save login time
	}
	
	/**
	 * Get an url with HTTP GET
	 * 
	 * @access public
	 * @param string $url URL to get
	 * @param array $data Array of data to pass. Gets transformed into the URL inside the function. Default null.
	 * @return bool|string Result
	 */
	function get( $url, $data = null ) {
		global $argv, $pgProxy, $pgHTTPEcho;
		
		if( count( $pgProxy ) ) {
			curl_setopt($this->curl_instance,CURLOPT_PROXY, $pgProxy['addr']);
			if( isset( $pgProxy['type'] ) ) {
				curl_setopt($this->curl_instance,CURLOPT_PROXYTYPE, $pgProxy['type']);
			}
			if( isset( $pgProxy['userpass'] ) ) {
				curl_setopt($this->curl_instance,CURLOPT_PROXYUSERPWD, $pgProxy['userpass']);
			}
			if( isset( $pgProxy['port'] ) ) {
				curl_setopt($this->curl_instance,CURLOPT_PROXYPORT, $pgProxy['port']);
			}
		}
		
		curl_setopt($this->curl_instance,CURLOPT_FOLLOWLOCATION,1);
		curl_setopt($this->curl_instance,CURLOPT_HTTPGET,1);
		
		/*if( !is_null( $this->use_cookie ) ) {
			curl_setopt($this->curl_instance,CURLOPT_COOKIE, $this->use_cookie);
		}*/
		
		if( !is_null( $data ) && is_array( $data ) ) {
			$url .= '?' . http_build_query( $data );
		}
		
		curl_setopt($this->curl_instance,CURLOPT_URL,$url);
		
		if( (!is_null( $argv ) && in_array( 'peachyecho', $argv )) || $pgHTTPEcho ) {
			pecho( "GET: $url\n", PECHO_NORMAL );
		}

		$data = curl_exec( $this->curl_instance );
		
		if( curl_errno( $this->curl_instance ) != 0 ) {
			throw new CURLError( curl_errno( $this->curl_instance ), curl_error( $this->curl_instance ) );
			return false;
		}
		
		return $data;
		
	}
	
	/**
	 * Returns the HTTP code of the last request
	 * 
	 * @access public
	 * @return int HTTP code
	 */
	function get_HTTP_code() {
		$ci = curl_getinfo( $this->curl_instance );
		return $ci['http_code'];
	}
	
	/**
	 * Sends data via HTTP POST
	 * 
	 * @access public
	 * @param string $url URL to send
	 * @param array $data Array of data to pass.
	 * @return bool|string Result
	 */
	function post( $url, $data ) {
		global $argv, $pgProxy, $pgHTTPEcho;
		
		if( count( $pgProxy ) ) {
			curl_setopt($this->curl_instance,CURLOPT_PROXY, $pgProxy['addr']);
			if( isset( $pgProxy['type'] ) ) {
				curl_setopt($this->curl_instance,CURLOPT_PROXYTYPE, $pgProxy['type']);
			}
			if( isset( $pgProxy['userpass'] ) ) {
				curl_setopt($this->curl_instance,CURLOPT_PROXYUSERPWD, $pgProxy['userpass']);
			}
			if( isset( $pgProxy['port'] ) ) {
				curl_setopt($this->curl_instance,CURLOPT_PROXYPORT, $pgProxy['port']);
			}
		}
		
		curl_setopt($this->curl_instance,CURLOPT_FOLLOWLOCATION,0);
		curl_setopt($this->curl_instance,CURLOPT_POST,1);
		curl_setopt($this->curl_instance,CURLOPT_POSTFIELDS, $data);
		
		/*if( !is_null( $this->use_cookie ) ) {
			curl_setopt($this->curl_instance,CURLOPT_COOKIE, $this->use_cookie);
		}*/
		
		curl_setopt($this->curl_instance,CURLOPT_URL,$url);
		
		if( (!is_null( $argv ) && in_array( 'peachyecho', $argv )) || $pgHTTPEcho ) {
			pecho( "POST: $url\n", PECHO_NORMAL );
		}
		
		$data = curl_exec( $this->curl_instance );
		
		if( curl_errno( $this->curl_instance ) != 0 ) {
			throw new CURLError( curl_errno( $this->curl_instance ), curl_error( $this->curl_instance ) );
			return false;
		}
		
		return $data;
	}
	
	/**
	 * Downloads an URL to the local disk
	 * 
	 * @access public
	 * @param string $url URL to get
	 * @param array $local Local filename to download to
	 * @return bool
	 */
	function download( $url, $local ) {
		global $argv, $pgProxy, $pgHTTPEcho;
		
		$out = fopen($local, 'wb'); 
		
		if( count( $pgProxy ) ) {
			curl_setopt($this->curl_instance,CURLOPT_PROXY, $pgProxy['addr']);
			if( isset( $pgProxy['type'] ) ) {
				curl_setopt($this->curl_instance,CURLOPT_PROXYTYPE, $pgProxy['type']);
			}
			if( isset( $pgProxy['userpass'] ) ) {
				curl_setopt($this->curl_instance,CURLOPT_PROXYUSERPWD, $pgProxy['userpass']);
			}
			if( isset( $pgProxy['port'] ) ) {
				curl_setopt($this->curl_instance,CURLOPT_PROXYPORT, $pgProxy['port']);
			}
		}
		

		//curl_setopt($this->curl_instance, CURLOPT_FILE, $out);
		curl_setopt($this->curl_instance, CURLOPT_URL, $url);
		curl_setopt($this->curl_instance, CURLOPT_HEADER, 0);
		
		if( (!is_null( $argv ) && in_array( 'peachyecho', $argv )) || $pgHTTPEcho ) {
			pecho( "DLOAD: $url\n", PECHO_NORMAL );
		}
		
		$ret = curl_exec( $this->curl_instance );
		
		if( curl_errno( $this->curl_instance ) != 0 ) {
			throw new CURLError( curl_errno( $this->curl_instance ), curl_error( $this->curl_instance ) );
			return false;
		}
		
		fwrite( $out, $ret );
		
		fclose($out);
		
		
	}
	
	/**
	 * Destructor, deleted cookies and closes cURL class
	 * 
	 * @access public
	 * @return void
	 */
	function __destruct () {
		curl_close($this->curl_instance);
		@unlink('/tmp/peachy.cookies.'.$this->cookie_hash.'.dat');
	}


}