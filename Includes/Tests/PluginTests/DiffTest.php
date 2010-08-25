<?php

require_once dirname(dirname(dirname(dirname(__FILE__)))).'/Plugins/lime.php';
 
$t = new lime_test();

$str1 = '
you
are
cool
';

$str2 = '
you
aren\'t
cool
';

$str3 = '
you
are
sorta
cool
';

$t->is( count( explode( "\n", trim( getTextDiff('unified', $str1, $str2) ) ) ), 6, 'Unified diff is 6 lines long' );
$t->is( count( explode( "\n", trim( getTextDiff('inline', $str1, $str2) ) ) ), 3, 'Inline diff is 3 lines long' );
$t->is( count( explode( "\n", trim( getTextDiff('context', $str1, $str2 ) ) ) ), 12, 'Context diff is 12 lines long' );
$t->is( count( explode( "\n", trim( getTextDiff('threeway', $str1, $str2, $str3 ) ) ) ), 9, 'Threeway diff is 9 lines long' );

$t->is( count( explode( "\n", trim( getTextDiff('unified', '', $str2) ) ) ), 5, 'Unified diff is 5 lines long' );
$t->is( count( explode( "\n", trim( getTextDiff('unified', '', $str3) ) ) ), 6, 'Unified diff is 6 lines long' );

$t->is( count( explode( "\n", trim( getTextDiff('unified', $str2, '', $str2) ) ) ), 5, 'Unified diff is 5 lines long' );
$t->is( count( explode( "\n", trim( getTextDiff('unified', $str3, '', $str3) ) ) ), 6, 'Unified diff is 6 lines long' );
