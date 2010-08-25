<?php

require_once dirname(dirname(dirname(__FILE__))).'/Plugins/lime.php';

$t = new lime_test();

$site = Peachy::newWiki( null, null, null, 'http://en.wikipedia.org/w/api.php' );


$t->info( '1 - iin_array() testing' );

$t->is( iin_array( 'no', array( 'No', 'non', 'dhhd' ) ), true, '"no" in array( "No" )' );
$t->is( iin_array( 'noN', array( 'No', 'no', 'dhhd' ) ), false, '"non" not in array' );
$t->is( iin_array( array(1), array( 'No', 'no', 'dhhd', array(1) ) ), true, 'needle is an array' );

$t->info( '2 - in_string() testing' );

$t->ok( in_string( 'foo', '123foo456' ), 'foo in 123foo456' );
$t->ok( in_string( 'foo', 'foo456' ), 'foo in foo456' );
$t->ok( !in_string( 'Foo', 'foo456' ), 'Foo not in foo456' );
$t->ok( in_string( 'Foo', 'foo456', true ), 'Foo in foo456, with case insensitivity' );


$t->info( '3 - in_array_recursive() testing' );

$t->is( in_array_recursive( 'no', array( 'No', 'non', 'dhhd', array( 'no' ) ) ), true );
$t->is( in_array_recursive( 'noN', array( 'No', 'no', 'dhhd', array( 'Non' ) ), true ), true, 'Insensitive search' );
