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

error_reporting( E_ALL | E_STRICT );
# TODO: Create a better argument parser that accepts, e.g.
# -wiki=libertapedia -file=enwiki-20100622-all-titles-in-ns0
if ( !isset ( $argv[1] ) ) {
    die ('Filename not specified.\n');
}
$fileName = $argv[1];
require_once( 'Init.php' );
$wiki = Peachy::newWiki( "libertapedia" );
Peachy::loadPlugin( 'rped' );
$rped = RPED::load( $wiki );
$handle = @fopen( $fileName, "r" );
$lineNumber = 0;
$line = "";
$daemonize = true;

if ( isset( $daemonize ) && $daemonize ) {

    $pid = pcntl_fork(); // fork
    if ( $pid < 0 ) {
        exit;
    }
    else if ( $pid ) { // parent
        exit;
    }
    // child
    $sid = posix_setsid();
    if ( $sid < 0 ) {
            exit;
    }
}

$maxCount = 1000;
$count = 0;
$rpedArray = array();
if ( $handle ) {
    while ( !feof( $handle ) ) {
        $buffer = fgets( $handle, 4096 );
        $buffer = str_replace( "\n", "", $buffer );
        
        $count++;
        if ( $count > $maxCount ) {
            $rped->insertArray( $rpedArray, 10000 );
            $count = 0;
            unset ( $rpedArray );
            $rpedArray = array();
        }
        $rpedArray[] = $buffer;
    }
    $rped->insertArray( $rpedArray, 10000 );
    fclose( $handle );
} else {
    echo "No handle!\n";
}