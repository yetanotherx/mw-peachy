<?php

require_once( '/home/soxred93/Peachy/Init.php' );

$IP = '/home/soxred93/Peachy/';

$x = Peachy::newWiki( "compwhizii" );

$files = array();

getFiles();

function getFiles( $dir = false ) {
	global $IP, $files;
	
	if( !$dir ) $dir = $IP;
	
	$y = glob( $dir . '*' );
	
	foreach( $y as $file ) {
		if( is_dir( $file ) ) {
			getFiles( $file . '/' );
		}
		else {
			$files[] = $file;
		}
	}
}

$hooks = array();

foreach( $files as $file ) {
	$content = file_get_contents( $file );
	
	preg_match_all( '/Hooks::runHook\( \'(.*?)\'(, array\((.*?)\))/', $content, $res );
	
	if( count( $res[1] ) ) {
		foreach( $res[1] as $val => $ret ) {
			$hooks[$ret] = array(
				'args' => trim( $res[3][$val] ),
				'file' => array_pop( explode( '/', $file ) )
			);
		}
	}
	
	unset( $content );
}


ksort( $hooks );

print_r($hooks);

foreach( $hooks as $hook => $args ) {
	$page = $x->initPage( "Manual/Hooks/$hook" );
	
	if( $page->exists() ) continue;
	
	$output = '{{PeachyHook|name='.$hook.'|version='/*PEACHYVERSION*/.'0.1alpha'.'|args='.$args['args'].'|source='.$args['file'].'|summary=}}

== Details ==


';

	$output .= str_replace( '&$', '$', '* ' . implode( ":\n* ", explode( ', ', $args['args'] ) ) . ':');

	echo $output . "\n\n\n\n\n";
	
	$page->edit( $output );
}

$newpage = $x->initPage( "Manual/Hooks/Table" );

$new = '{| class="wikitable" width="100%" style="text-align: center;"
! Hook name !! Filename !! Version !! Description
|- ';

foreach( $hooks as $hook => $args ) {
$new .= '
| [[Manual/Hooks/'.$hook.'|'.$hook.']] || '. $args['file'].' || '/*PEACHYVERSION*/.'0.1alpha'.' || 
|-';
}

$new .= '
|}';

$newpage->edit( $new );

