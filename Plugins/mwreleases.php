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

class SiteMatrix {
	
	/**
	 * Loads list of all SiteMatrix wikis
	 * 
	 * @static
	 * @access public
	 * @param Wiki &$wikiClass The Wiki class object
	 * @return array List of all wikis
	 */
	public static function load( &$wikiClass ) {

		if( !array_key_exists( 'MWReleases', $wikiClass->get_extensions() ) ) {
			throw new DependencyError( "MWReleases", "http://www.mediawiki.org/wiki/Extension:MWReleases" );
		}
		
		$SMres = $wikiClass->apiQuery(array(
				'action' => 'mwreleases',
			)
		);
		
		return $SMres['mwreleases'];
		
	}

}
