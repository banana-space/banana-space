<?php

if ( PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg' ) {
	die( "Can only be run from the command line" );
}
if ( count( $argv ) < 3 ) {
	print "Call with 2 arguments: the path to the load url and the file to output to";
	exit();
}
list( , $loadUrl, $outputFile ) = $argv;

define( 'MEDIAWIKI', true );
// FIXME: Why not use define()?
const NS_MAIN = 0;
// FIXME: This should be a string
// TODO: Can this be bumped?
define( 'MW_VERSION', 1.23 );
$wgSpecialPages = [];
$wgResourceModules = [];

include "Resources.php";

$query = [];
$blacklist = [];
foreach ( $wgResourceModules as $moduleName => $def ) {
	if ( !in_array( $moduleName, $blacklist ) ) {
		$query[] = $moduleName;
	}
}

$url = $loadUrl . '?only=styles&skin=vector&modules=' . implode( '|', $query );

/**
 * @param string $val
 * @param-taint $val none
 */
function out( $val ) {
	echo $val;
}

out( $url );
$css = file_get_contents( $url );
file_put_contents( $outputFile, $css );
