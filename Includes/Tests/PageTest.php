<?php
require_once dirname(dirname(dirname(__FILE__))).'/Plugins/lime.php';

class PageTest extends Page {
	function getParam($param) { return $this->$param; }
}

$t = new lime_test();

$site = Peachy::newWiki( 'Tests/enwikitest' );

$p1 = $site->initPage( 'Foobar' );
$p2 = initPage( 'Foobar' );
$p3 = new Page( $site, 'Foobar' );
$p4 = new PageTest( $site, 'foobar' );

$t->info('1 - Initialization');

$t->is( serialize($p1), serialize($p2), 'Wiki::initPage() and initPage() return the same value' );
$t->is( serialize($p1), serialize($p3), 'Wiki::initPage() and new Page( $site ) return the same value' );
$t->is( serialize($p4->getParam('wiki')), serialize($site), 'initPage()->wiki and $site return the same value' );

unset( $p2, $p3, $p4 );

$site2 = Peachy::newWiki( 'Tests/compwhiziitest' );

$t->ok( !$p1->redirectFollowed(), 'Page::redirectFollowed() returns false' );

$p1 = $site2->initPage( 'TestRedirect' );

$t->ok( $p1->redirectFollowed(), 'Page::redirectFollowed() returns true' );