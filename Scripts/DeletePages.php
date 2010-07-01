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

This script deletes a bunch of pages using a list of page titles contained
in a file. The filename is argument #1.
*/

# TODO: Create a better argument parser
# that lets you specify the wiki and
# deletion reason
if ( !isset ( $argv[1] ) ) {
    die ('Filename not specified.\n');
}
$fileName = $argv[1];
$reason = 'Deleting a batch of pages';
require_once( 'Init.php' );
$wiki = Peachy::newWiki( "libertapedia" );
$handle = @fopen( $fileName, "r" );

if ( $handle ) {
    while ( !feof( $handle ) ) {
        $buffer = fgets( $handle, 4096 );
        $buffer = str_replace( "\n", "", $buffer );
	if ( $buffer != '' ) {
	    #echo 'Deleting ' . $buffer . "\n";
	    $page = $wiki->initPage( $buffer );
	    $page->delete ( $reason );
	}
    }
} else {
    echo "File I/O problem (maybe the file doesn't exist?), script aborted\n";
}
