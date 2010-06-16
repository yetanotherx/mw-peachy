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

class OpenSearch {
	
	public static function load( $wikiClass, $text, $limit = 100, $namespaces = array( 0 ) ) {
		global $pgHTTP;
		
		if( !array_key_exists( 'OpenSearchXml', $wikiClass->getExtensions() ) ) {
			throw new DependancyError( "OpenSearchXml", "http://www.mediawiki.org/wiki/Extension:OpenSearchXML" );
		}
		
		$OSres = $pgHTTP->get(
			$wikiClass->get_base_url(),
			array(
				'search' => $text,
				'action' => 'opensearch',
				'limit' => $limit,
				'namespace' => implode( '|', $namespaces )
			)
		);
		
		$OS = explode(',', $OSres);
		
		##FIXME:::: Only shift if page does not exist. 
		##FIXME: Shift this whole mess to json_decode
		array_shift( $OS );
		
		foreach( $OS as $key => $val ) {
			$OS[$key] = str_replace( array('[',']','"'),'', $val );
		}
		
		return $OS;
		
	}

}