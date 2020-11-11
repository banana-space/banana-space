<?php

require_once __DIR__ . '/../vendor/autoload.php';

$testSuiteJSON = file_get_contents( __DIR__ . '/../tests/JSPHP-suite.json' );
$testSuite = json_decode( $testSuiteJSON, true );
$testSuiteOutput = [];

// @codingStandardsIgnoreStart
function new_OOUI( $class, $config = [] ) {
	// @codingStandardsIgnoreEnd
	$class = "OOUI\\" . $class;
	return new $class( $config );
}
// @codingStandardsIgnoreStart
function unstub( &$value ) {
	// @codingStandardsIgnoreEnd
	if ( is_string( $value ) && substr( $value, 0, 13 ) === '_placeholder_' ) {
		$value = json_decode( substr( $value, 13 ), true );
		if ( isset( $value['config'] ) && is_array( $value['config'] ) ) {
			array_walk_recursive( $value['config'], 'unstub' );
		}
		$value = new_OOUI( $value['class'], $value['config'] );
	}
}
// Keep synchronized with tests/index.php
$themes = [ 'ApexTheme', 'WikimediaUITheme' ];
foreach ( $themes as $theme ) {
	OOUI\Theme::setSingleton( new_OOUI( $theme ) );
	foreach ( $testSuite as $className => $tests ) {
		foreach ( $tests['tests'] as $test ) {
			// Unstub placeholders
			$config = $test['config'];
			array_walk_recursive( $config, 'unstub' );
			$config['infusable'] = true;
			$instance = new_OOUI( $test['class'], $config );
			$output = "$instance";
			// HACK: OO.ui.infuse() expects to find this element somewhere on the page
			if ( $instance instanceof OOUI\LabelWidget && isset( $config['input'] ) ) {
				$output .= $config['input'];
			}
			$testSuiteOutput[$theme][$className][] = $output;
		}
	}
}

$testSuiteOutputJSON = json_encode( $testSuiteOutput, JSON_PRETTY_PRINT );

echo "var testSuiteConfigs = $testSuiteJSON;\n\n";
echo "var testSuitePHPOutput = $testSuiteOutputJSON;\n\n";
echo file_get_contents( __DIR__ . '/../tests/JSPHP.test.karma.js' );
