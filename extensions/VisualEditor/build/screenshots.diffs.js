'use strict';

const createScreenshotEnvironment = require( './screenshots.js' ).createScreenshotEnvironment,
	test = require( 'selenium-webdriver/testing' ),
	runScreenshotTest = createScreenshotEnvironment( test );

function runTests( lang ) {

	test.describe( 'Screenshots: ' + lang, function () {
		this.lang = lang;
		test.it( 'Simple diff', function () {
			runScreenshotTest( 'VisualEditor_diff_simple', lang,
				require( './screenshots-client/diffs.js' ).simple
			);
			runScreenshotTest( 'VisualEditor_diff_move_and_change', lang,
				require( './screenshots-client/diffs.js' ).moveAndChange
			);
			runScreenshotTest( 'VisualEditor_diff_link_change', lang,
				require( './screenshots-client/diffs.js' ).linkChange
			);
			runScreenshotTest( 'VisualEditor_diff_list_change', lang,
				require( './screenshots-client/diffs.js' ).listChange
			);
		} );
	} );
}

for ( let i = 0, l = langs.length; i < l; i++ ) {
	runTests( langs[ i ] );
}
