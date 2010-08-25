<?php

require_once dirname(dirname(dirname(__FILE__))).'/Plugins/lime.php';

class UserTest extends User {
	function getParam($param) { return $this->$param; }
}

$t = new lime_test();

$site = Peachy::newWiki( null, null, null, 'http://en.wikipedia.org/w/api.php' );

$u1 = $site->initUser( 'Jimbo Wales' );
$u2 = initUser( 'Jimbo Wales' );
$u3 = new User( $site, 'Jimbo Wales' );
$u4 = new UserTest( $site, 'jimbo_Wales' );

$t->info('1 - Initialization');

$t->is( serialize($u1), serialize($u2), 'Wiki::initUser() and initUser() return the same value' );
$t->is( serialize($u1), serialize($u3), 'Wiki::initUser() and new User( $site ) return the same value' );
$t->is( serialize($u4->getParam('wiki')), serialize($site), 'initUser()->wiki and $site return the same value' );

$u1 = $u4;
unset( $u2, $u3, $u4 );

$t->info('2 - Construction');

$u2 = new UserTest( $site, '127.0.0.1' );

$t->is( $u1->getParam('username'), 'Jimbo Wales', '__construct() normalizes username' );
$t->is( $u2->getParam('ip'), true, '__construct() detects IPs' );

$u2 = new UserTest( $site, 'ThisUserDoesNotExist12345Peachy' ); //hopefully no one ever makes that
$t->is( $u2->exists(), false, '__construct() detects non-existent users' );

$u2 = new UserTest( $site, '<!@#$:%^&>' );
$t->is( $u2->exists(), false, '__construct() detects invalid usernames' );


$u2 = new UserTest( $site, 'Grawp' );
$t->info('3 - get*(), is*() functions');

$t->is( $u1->is_blocked(), false, 'is_blocked() correctly detects blocked editors #1' );
$t->is( $u2->is_blocked(), true, 'is_blocked() correctly detects blocked editors #2' );

$contribs = $u2->get_contribs( false );
$first = array_shift($contribs);
$last = array_pop($contribs);

$t->cmp_ok( $first['timestamp'], '<', $last['timestamp'], 'get_contribs(false) gets oldest first' );

$u2 = new UserTest( $site, 'Example' );
$t->cmp_ok( $u2->get_editcount(), '>=', 1, 'get_editcount() returns correct values' );
$t->cmp_ok( count($u2->get_contribs()), '>=', 1, 'get_contribs() returns correct values' );

$u2 = new UserTest( $site, 'Emachman (usurped)' );
$t->is( $u2->has_email(), false, 'has_email() works correctly #1' );
$u2 = new UserTest( $site, 'X!' );
$t->is( $u2->has_email(), true, 'has_email() works correctly #1' );

$t->cmp_ok( strtotime($u2->get_registration()), '<', strtotime('01-01-2007'), 'get_registration() works correctly' );

$t->is( serialize($u2->getPageClass()), serialize(initPage('User:X!')), 'getPageClass() works correctly' );






