<?php
require_once dirname(dirname(dirname(dirname(__FILE__)))).'/Plugins/lime.php';
 
$t = new lime_test();

$t->pass('This always passes');