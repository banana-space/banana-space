// TODO: Incomplete
// Page showing the article with some actions.  This is the page that everyone
// is used to reading on wikipedia.  My mom would recognize this page.

'use strict';

const TitlePage = require( './title_page' );

class ArticlePage extends TitlePage {

	get articleTitle() {
		return this.title_element().getText();
	}

	title_element() {
		return browser.$( 'h1#firstHeading' );
	}

	/**
	 * Performs a search using the search button top-right
	 */
	click_search_top_right() {
		browser.$( '#simpleSearch #searchButton' ).click();
	}

	has_search_suggestions() {
		return this.get_search_suggestions().length > 0;
	}

	get_search_suggestion_at( nth ) {
		nth--;
		const suggestions = this.get_search_suggestions();
		return suggestions.length > nth ? suggestions[ nth ] : null;
	}

	get_search_suggestions() {
		const selector = '.suggestions .suggestions-results a.mw-searchSuggest-link';
		browser.waitUntil(
			() => browser.$( selector ).isExisting(),
			{ timeout: 10000 }
		);
		return this.collect_element_attribute( 'title', selector );
	}

	set search_query_top_right( search ) {
		browser.$( '#simpleSearch #searchInput' ).setValue( search );
	}

	get search_query_top_right() {
		return browser.getValue( '#simpleSearch #searchInput' );
	}
}

module.exports = new ArticlePage();
