<?php

require( 'Init.php' );

$x = Peachy::newWiki("sample"); //Loads the Configs/sample.cfg file

Peachy::loadAllPlugins();//Optional, required if using CheckUser, OpenSearch, SiteMatrix, etc plugins

$sites = SiteMatrix::load( $x ); //Generates sitematrix, logic in Plugins/sitematrix.php

$y = new Page( $x, "Main Page" );
echo $y->get_text();
