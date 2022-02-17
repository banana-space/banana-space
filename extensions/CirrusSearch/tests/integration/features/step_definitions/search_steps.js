'use strict';

const { Given, When, Then } = require( 'cucumber' ),
	SearchResultsPage = require( '../support/pages/search_results_page' ),
	ArticlePage = require( '../support/pages/article_page' ),
	TitlePage = require( '../support/pages/title_page' ),
	expect = require( 'chai' ).expect;

When( /^I go search for (.+)$/, function ( title ) {
	return this.visit( SearchResultsPage.search( title ) );
} );

Then( /^there are no search results/, function () {
	expect( SearchResultsPage.has_search_results(), 'there are no search results' ).to.equal( false );
} );

When( /^I search for (.+)$/, function ( search ) {
	// If on the SRP already use the main search
	if ( SearchResultsPage.is_on_srp() ) {
		SearchResultsPage.search_query = search;
		SearchResultsPage.click_search();
	} else {
		ArticlePage.search_query_top_right = search;
		ArticlePage.click_search_top_right();
	}
} );

Then( /^there is (no|a) link to create a new page from the search result$/, function ( no_or_a ) {
	const msg = `there is ${no_or_a} link to create a new page from the search result`;
	expect( SearchResultsPage.has_create_page_link(), msg ).to.equal( no_or_a !== 'no' );
} );

Then( /^there is no warning$/, function () {
	const msg = 'there is no warning';
	expect( SearchResultsPage.has_warnings(), msg ).to.equal( false );
} );

Then( /^there are no errors reported$/, function () {
	const msg = 'there are no errors reported';
	expect( SearchResultsPage.has_errors(), msg ).to.equal( false );
} );

Then( /^(.+) is the first search result( and has an image link)?$/, function ( result, imagelink ) {
	const msg = `${result} is the first search result`;
	if ( result === 'none' ) {
		expect( SearchResultsPage.has_search_results(), msg ).to.equal( false );
	} else {
		expect( SearchResultsPage.is_on_srp(), msg ).to.equal( true );
		expect( SearchResultsPage.has_search_results(), msg ).to.equal( true );
		expect( SearchResultsPage.get_result_at( 1 ), msg ).to.equal( result );
		if ( imagelink ) {
			expect( SearchResultsPage.get_result_image_link_at( 1 ), msg ).to.not.equal( null );
		}
	}
} );

Then( /^(.+) is( not)? in the search results$/, function ( result, not ) {
	const msg = `${result} is${not === undefined ? '' : not} in the search results`;
	expect( SearchResultsPage.is_on_srp(), msg ).to.equal( true );
	if ( not === undefined ) {
		expect( SearchResultsPage.has_search_results(), msg ).to.equal( true );
	}
	expect( SearchResultsPage.in_search_results( result ), msg ).to.equal( not === undefined );
} );

Given( /^I am at the search results page$/, function () {
	this.visit( new TitlePage( 'Special:Search' ) );
} );

When( /^I click the (.+) link$/, function ( filter ) {
	SearchResultsPage.click_filter( filter );
} );

When( /^I click the (.+) labels?$/, function ( filter ) {
	const and_labels = filter.split( /, /, 10 );
	for ( const labels of and_labels ) {
		const or_labels = labels.split( / or /, 10 );
		SearchResultsPage.select_namespaces( or_labels, true );
	}
} );

Then( /^the title still exists$/, function () {
	const msg = 'the title still exists';
	expect( ArticlePage.title_element().isExisting(), msg ).to.equal( true );
} );

Then( /^there is not alttitle on the first search result$/, function () {
	const msg = 'there is not alttitle on the first search result';
	expect( SearchResultsPage.get_search_alt_title_at( 1, msg ) ).to.equal( null );
} );

Then( /^there are search results with \((.+)\) in the data$/, function ( what ) {
	const msg = `there are search results with ${what} in the data`;
	expect( SearchResultsPage.is_on_srp() ).to.equal( true );
	expect( SearchResultsPage.has_search_results(), msg ).to.equal( true );
	expect( SearchResultsPage.has_search_data_in_results( what ), msg ).to.equal( true );
} );

Then( /^I type (.+) into the search box$/, function ( search ) {
	ArticlePage.search_query_top_right = search;
} );

Then( /^suggestions should appear$/, function () {
	expect( ArticlePage.has_search_suggestions() ).to.equal( true );
} );

Then( /^(.+) is the first suggestion$/, function ( page ) {
	expect( ArticlePage.get_search_suggestion_at( 1 ) ).to.equal( page );
} );

Then( /^I click the search button$/, function () {
	ArticlePage.click_search_top_right();
} );
