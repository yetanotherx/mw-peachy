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

class Image {

	private $wiki;
	private $name; //including "Image"
	private $mime;
	private $pageid;
	private $exists = true;
	private $url;
	private $commons = false;
	private $hash;
	private $bitdepth;
	private $metadata;
	private $usage;
	private $history = array();
	
	
	function __construct( $wikiClass, $filename = null, $pageid = null, $prop = array( 'timestamp', 'user', 'comment', 'url', 'size', 'dimensions', 'sha1', 'mime', 'metadata', 'archivename', 'bitdepth' ) ) {
		$this->wiki = $wikiClass;
		
		$imageInfoArray = array(
			'action' => 'query',
			'prop' => 'imageinfo',
			'iilimit' => '1',
			'iiprop' => implode('|',$prop)
		);
		
		if( is_null( $filename ) && is_null( $pageid ) ) {
			throw new ImageError( 'Either $filename or $pageid must be set when initializing Image' );
		}
		elseif( !is_null( $pageid ) && !is_null( $filename ) ) {
			throw new ImageError( '$filename and $pageid cannot be used in conjunction.' );
		}
		else {
			if( is_null( $pageid ) ) {
				##FIXME: This is incredibly hacky, and doesn't work for non-english-wikis
				if( !preg_match( '/^(File|Image)/i', $filename ) ) $filename = "Image:" . $filename;
				$imageInfoArray['titles'] = $filename;
			}
			else {
				$imageInfoArray['pageids'] = $pageid;
			}
		}
		
		$ii = $this->wiki->apiQuery( $imageInfoArray );
		
		foreach( $ii['query']['pages'] as $x ) {
			$this->pageid = $x['pageid'];
			$this->name = $x['title'];
			
			if( isset( $x['missing'] ) ) $this->exists = false;
			
			if( $x['imagerepository'] == "shared" ) $this->commons = true;
			
			if( isset( $x['imageinfo'] ) ) {
				$this->mime = $x['imageinfo'][0]['mime'];
				$this->url = $x['imageinfo'][0]['url'];
				$this->hash = $x['imageinfo'][0]['sha1'];
				$this->metadata = $x['imageinfo'][0]['metadata'];
				$this->bitdepth = $x['imageinfo'][0]['bitdepth'];
			}
		}
	}
	
	public function getHistory() {
	
	}
	
	/**
	 * Returns all pages where the image is used. If function was already called earlier in the script, it will return the local cache unless $force is set to true. 
	 * 
	 * @access public
	 * @param bool $force Whether or not to regenerate list, even if there is a local cache. Default false, set to true to regenerate list.
	 * @param string|array $namespace Namespaces to look in. If set as a string, must be set in the syntax "0|1|2|...". If an array, simply the namespace IDs to look in. Default null.
	 * @param string $redirects How to filter for redirects. Options are "all", "redirects", or "nonredirects". Default "all".
	 * @param bool $followRedir If linking page is a redirect, find all pages that link to that redirect as well. Default false.
	 * @return array
	 */
	 
	##FIXME: Make this work for images on a shared repository
	public function getUsage( $force = false, $namespace = null, $redirects = "all", $followRedir = false ) {
		
		if( !$this->exists ) {
			$this->usage = array();
		}
		elseif( $force || !is_array( $this->usage ) ) {
			$iuArray = array(
				'list' => 'imageusage',
				'code' => 'iu',
				'lhtitle' => 'title',
				'iutitle' => $this->name,
				'iufilterredir' => $redirects,
			);
			
			if( !is_null( $namespace ) ) {
			
				if( is_array( $namespace ) ) {
					$namespace = implode( '|', $namespace );
				}
				$iuArray['iunamespace'] = $namespace;
			}
			
			if( $followRedir ) $iuArray['iuredirect'] = 'yes';
			
			$this->usage = $this->wiki->listHandler( $iuArray );
		}
		
		return $this->usage;
		
	}
	
	/**
	 * Returns an array of all files with identical sha1 hashes
	 * @return array Duplicate files
	 */
	public function getDuplicates() {
		
		if( !$this->exists ) {
			return array();
		}
		
		$dArray = array(
			'action' => 'query',
			'prop' => 'duplicatefiles',
			'dflimit' => ($this->wiki->get_api_limit() + 1),
			'titles' => $this->name
		);
		
		$dupes = array();
		
		$continue = null;
		
		while( 1 ) {
			if( !is_null( $continue ) ) $tArray['dfcontinue'] = $continue;
			
			$dRes = $this->wiki->apiQuery( $dArray );
			
			foreach( $dRes['query']['pages'] as $x ) {
				if( isset( $x['duplicatefiles'] ) ) {
					foreach( $x['duplicatefiles'] as $y ) {
						$dupes[] = $y['name'];
					}
				}
			}
			
			if( isset( $dRes['query-continue'] ) ) {
				foreach( $dRes['query-continue'] as $z ) {
					$continue = $z['dfcontinue'];
				}
			}
			else {
				break;
			}
			
			
		}
		
		return $dupes;
		
	}
	
	/**
	 * Upload an image to the wiki
	 * 
	 * @access public
	 * @param mixed $file Location of the file to upload
	 * @param string $text Text on the image file page (default: '')
	 * @param string $comment Comment for inthe upload in logs (default: '')
	 * @param bool $watch Should the upload be added to the watchlist (default: false)
	 * @param bool $ignorewarnings Ignore warnings about the upload (default: true)
	 * @return void
	 */
	public function upload( $file, $text = '', $comment = '', $watch = false, $ignorewarnings = true ) {
		global $mwVersion;
		
		$tokens = $this->getTokens();
		
		if( version_compare( $mwVersion, '1.16' ) >= 0 ) {
			$uploadArray = array(
				'action' => 'upload',
				'filename' => $this->name,
				'comment' => $comment,
				'text' => $text,
				'token' => $tokens['edit'],
				'watch' => intval( $watch ),
				'ignorewarnings' => intval( $ignorewarnings ),
				'file' => "@$file"
			);
			$this->apiQuery( $uploadArray, true );
		} else {
			##FIXME: test the non-api upload
			
			$pgHTTP->post(
				str_replace( 'api.php', 'index.php', $this->base_url ),
				array(
					'wpUploadFile' => '@'.$file,
		            'wpSourceType' => 'file',
		            'wpDestFile' => $this->name,
		            'wpUploadDescription' => $desc,
		            'wpLicense' => '',
		            'wpWatchthis' => '0',
		            'wpIgnoreWarning' => '1',
		            'wpUpload' => 'Upload file',
				)
			);
		}
		
		##FIXME: Add error checking
		
		$this->__construct( $this->wiki, $this->name );
	}
	
	public function history() {}
	
	/**
	 * Downloads an image to the local disk
	 * @param string $name Filename to store image as. Default false.
	 * @param int $width Width of image to download. Cannot be used together with $height. Default false.
	 * @param int $height Height of image to download. Cannot be used together with $width. Default false.
	 * @return void
	 */

	public function download( $name = false, $width = false, $height = false ) {
		global $pgHTTP, $IP;
		
		if( $this->commons ) {
			throw new ImageError( "Attempted to download a file on a shared respository instead of a local one" );
		}
		
		if( !$this->exists ) {
			throw new ImageError( "Attempted to download a non-existant file." );
		}
		
		if( $width ^ $height ) {
			$iiParams = array(
				'action' => 'query',
				'prop' => 'imageinfo',
				'iiprop' => 'url',
				'titles' => $this->name
			);
			
			if( $width ) {
				$iiParams['iiurlwidth'] = $width;
			}
			else {
				$iiParams['iiurlheight'] = $height;
			}
			
			$iiRes = $this->wiki->apiQuery( $iiParams );
			
			if( !isset( $iiRes['query']['pages'] ) ) {
				throw new APIError( "Unknown API error", print_r($iiRes,true) );
			}
			
			foreach( $iiRes['query']['pages'] as $x ) {
				$url = $x['imageinfo'][0]['url'];
				break;
			}
		}
		elseif( $width && $height ) {
			throw new ImageError( "Both width and height were specifying when downloading an image." );
		}
		else {
			$url = $this->url;
		}
		
		$localname = str_replace(' ','_',$this->name);
		if( $name ) $localname = $name;
		
		$pgHTTP->download( $url, $IP . 'Images/' . $name );
	}
	
	/**
	 * Returns the normalized image name
	 * @param bool $namespace Whether or not to include the File: part of the name. Default true.
	 * @return string
	 */
	 
	public function getName( $namespace = true ) {
		if( $namespace ) {
			return $this->name;
		}
		else {
			$tmp = explode( ':', $this->name, 2 );
			return $tmp[1];
		}
	}
	
	/**
	 * Returns the MIME type of the image
	 * @return string
	 */
	 
	public function getMime() {
		return $this->mime;
	}
	
	/**
	 * Whether or not the image exists
	 * @return bool
	 */
	 
	public function getExists() {
		return $this->exists;
	}
	
	/**
	 * Returns the direct URL of the image
	 * @return string
	 */
	 
	public function getUrl() {
		return $this->url;
	}
	
	/**
	 * Whether or not the image is on a shared repository. A true result means that it is stored locally.
	 * @return bool
	 */
	 
	public function getLocal() {
		if( $this->commons ) {
			return false;
		}
		return true;
		
	}
	
	/**
	 * Returns the SHA1 hash of the image
	 * @return string
	 */
	 
	public function getHash() {
		return $this->hash;
	}
	
	/**
	 * Returns the bitdepth of the image
	 * @return string
	 */
	 
	public function getBitdepth() {
		return $this->bitdepth;
	}
	
	/**
	 * Returns the metadata of the image
	 * @return string
	 */
	 
	public function getMetadata() {
		return $this->metadata;
	}
	

}