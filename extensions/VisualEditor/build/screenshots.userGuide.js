'use strict';

const createScreenshotEnvironment = require( './screenshots.js' ).createScreenshotEnvironment,
	test = require( 'selenium-webdriver/testing' ),
	runScreenshotTest = createScreenshotEnvironment( test );

function runTests( lang ) {

	test.describe( 'Screenshots: ' + lang, function () {
		this.lang = lang;
		test.it( 'Toolbar & action tools', function () {
			runScreenshotTest( 'VisualEditor_toolbar', lang,
				require( './screenshots-client/userGuide.js' ).toolbar,
				0
			);
			runScreenshotTest( 'VisualEditor_toolbar_actions', lang,
				require( './screenshots-client/userGuide.js' ).toolbarActions,
				0
			);
		} );
		test.it( 'Citoid inspector', function () {
			runScreenshotTest( 'VisualEditor_Citoid_Inspector', lang,
				require( './screenshots-client/userGuide.js' ).citoidInspector
			);
			runScreenshotTest( 'VisualEditor_Citoid_Inspector_Manual', lang,
				require( './screenshots-client/userGuide.js' ).citoidInspectorManual
			);
			runScreenshotTest( 'VisualEditor_Citoid_Inspector_Reuse', lang,
				require( './screenshots-client/userGuide.js' ).citoidInspectorReuse
			);
		} );
		test.it( 'Tool groups (headings/text style/indentation/insert/page settings)', function () {
			runScreenshotTest( 'VisualEditor_Toolbar_Headings', lang,
				require( './screenshots-client/userGuide.js' ).toolbarHeadings
			);
			runScreenshotTest( 'VisualEditor_Toolbar_Formatting', lang,
				require( './screenshots-client/userGuide.js' ).toolbarFormatting
			);
			runScreenshotTest( 'VisualEditor_Toolbar_Lists_and_indentation', lang,
				require( './screenshots-client/userGuide.js' ).toolbarLists
			);
			runScreenshotTest( 'VisualEditor_Insert_Menu', lang,
				require( './screenshots-client/userGuide.js' ).toolbarInsert
			);
			runScreenshotTest( 'VisualEditor_Media_Insert_Menu', lang,
				require( './screenshots-client/userGuide.js' ).toolbarMedia
			);
			runScreenshotTest( 'VisualEditor_Template_Insert_Menu', lang,
				require( './screenshots-client/userGuide.js' ).toolbarTemplate
			);
			runScreenshotTest( 'VisualEditor_insert_table', lang,
				require( './screenshots-client/userGuide.js' ).toolbarTable
			);
			runScreenshotTest( 'VisualEditor_Formula_Insert_Menu', lang,
				require( './screenshots-client/userGuide.js' ).toolbarFormula
			);
			runScreenshotTest( 'VisualEditor_References_List_Insert_Menu', lang,
				require( './screenshots-client/userGuide.js' ).toolbarReferences
			);
			runScreenshotTest( 'VisualEditor_More_Settings', lang,
				require( './screenshots-client/userGuide.js' ).toolbarSettings
			);
			runScreenshotTest( 'VisualEditor_page_settings_item', lang,
				require( './screenshots-client/userGuide.js' ).toolbarPageSettings
			);
			runScreenshotTest( 'VisualEditor_category_item', lang,
				require( './screenshots-client/userGuide.js' ).toolbarCategory
			);
		} );
		test.it( 'Save dialog', function () {
			runScreenshotTest( 'VisualEditor_save_dialog', lang,
				require( './screenshots-client/userGuide.js' ).save
			);
		} );
		test.it( 'Special character inserter', function () {
			runScreenshotTest( 'VisualEditor_Toolbar_SpecialCharacters', lang,
				require( './screenshots-client/userGuide.js' ).specialCharacters
			);
		} );
		test.it( 'Math dialog', function () {
			runScreenshotTest( 'VisualEditor_formula', lang,
				require( './screenshots-client/userGuide.js' ).formula
			);
		} );
		test.it( 'Reference list dialog', function () {
			runScreenshotTest( 'VisualEditor_references_list', lang,
				require( './screenshots-client/userGuide.js' ).referenceList
			);
		} );
		test.it( 'Cite button', function () {
			runScreenshotTest( 'VisualEditor_citoid_Cite_button', lang,
				require( './screenshots-client/userGuide.js' ).toolbarCite,
				0
			);
		} );
		test.it( 'Link inspector', function () {
			runScreenshotTest( 'VisualEditor-link_tool-search_results', lang,
				require( './screenshots-client/userGuide.js' ).linkSearchResults
			);
		} );
	} );
}

for ( let i = 0, l = langs.length; i < l; i++ ) {
	runTests( langs[ i ] );
}
