'use strict';

const TitlePage = require( './title_page' );

class SpecialUndelete extends TitlePage {
	constructor() {
		// Haxing fuzzy into the url like this feels hacky.
		super( 'Special:Undelete?fuzzy=1' );
	}

	set search_input( search ) {
		browser.$( '#prefix' ).setValue( search );
	}

	get search_input() {
		return browser.$( '#prefix' ).getValue();
	}

	click_search_button() {
		browser.$( '#searchUndelete' ).click();
	}

	// nth is 1-indexed, not 0 like might be expected
	get_result_at( nth ) {
		return browser.$( `.undeleteResult:nth-child(${nth}) a` ).getText();
	}
}

module.exports = new SpecialUndelete();
