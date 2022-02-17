<?php

/**
 * This is a stupid hack, basically Maintenance::shouldExecute() will bail
 * for safety if something other than include/require is in the stack trace.
 * This allows to parse the "execute" the maint script to make sure it's loadable
 * without actually running the script.
 *
 * @param string $path
 */
function wfCirrusUnitTestScriptsRunablePreload( $path ) {
	include $path;
	exit( 0 );
}

wfCirrusUnitTestScriptsRunablePreload( $argv[1] );
