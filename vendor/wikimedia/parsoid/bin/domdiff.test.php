<?php

// PORT-FIXME: Incomplete. Doesn't support CLI flags of the JS version

require_once __DIR__ . '/../vendor/autoload.php';

use Wikimedia\Parsoid\Html2Wt\DOMDiff;
use Wikimedia\Parsoid\Mocks\MockEnv;
use Wikimedia\Parsoid\Utils\ContentUtils;

$html1 = file_get_contents( $argv[1] );
$html2 = file_get_contents( $argv[2] );

$mockEnv = new MockEnv( [] );
$body1 = ContentUtils::ppToDOM( $mockEnv, $html1, [ "markNew" => true ] );
$body2 = ContentUtils::ppToDOM( $mockEnv, $html2, [ "markNew" => true ] );

$domDiff = new DOMDiff( $mockEnv );
$domDiff->diff( $body1, $body2 );

$opts = [
	'env' => $mockEnv,
	'keepTmp' => true,
	'storeDiffMark' => true,
	'tunnelFosteredContent' => true,
	'quiet' => true
];
ContentUtils::dumpDOM( $body2, 'DIFF-marked DOM',  $opts );
