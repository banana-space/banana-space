'use strict';

const Page = require( './page' );

class SearchResultsPage extends Page {

	/**
	 * Open the Search results searching for search
	 *
	 * @param {string} search
	 * @chainable
	 * @return {SearchResultsPage}
	 */
	search( search ) {
		this.url = `/w/index.php?title=Special:Search&search=${encodeURIComponent( search )}`;
		return this;
	}

	has_search_results() {
		return browser.$( '.searchresults ul.mw-search-results' ).isExisting();
	}

	get_warnings() {
		return this.collect_element_texts( '.searchresults div.warningbox p' );
	}

	has_warnings() {
		return this.get_warnings().length > 0;
	}

	get_errors() {
		return this.collect_element_texts( '.searchresults div.errorbox p' );
	}

	has_errors() {
		return this.get_errors().length > 0;
	}

	has_create_page_link() {
		return browser.$( '.searchresults p.mw-search-createlink a.new' ).isExisting();
	}

	is_on_srp() {
		return browser.$( 'form#search div#mw-search-top-table' ).isExisting() ||
			browser.$( 'form#powersearch div#mw-search-top-table' ).isExisting();
	}

	set search_query( search ) {
		browser.$( 'div#searchText input[name="search"]' ).setValue( search );
	}

	get search_query() {
		return browser.$( 'div#searchText input[name="search"]' ).getValue();
	}

	get_result_element_at( nth ) {
		const resultLink = this.results_block().$( `a[data-serp-pos="${nth - 1}"]` );
		if ( !resultLink.isExisting() ) {
			return null;
		}
		return resultLink.$( '..' );
	}

	get_result_image_link_at( nth ) {
		const resElem = this.get_result_element_at( nth );
		if ( resElem === null ) {
			return null;
		}
		// Image links are inside a table
		// move to the tr parent to switch the td holding the images
		// <tbody>
		//  <tr>
		//    <td>[THUMB IMAGE LINK BLOCK]</td>
		//    <td>[RESULT ELEMENT BLOCK] position returned by get_result_element_at</td>
		//  </tr>
		// </tbody>
		const tr = resElem.$( '..' );
		if ( tr.getTagName() !== 'tr' ) {
			return null;
		}
		const imageTag = tr.$( 'td a.image img' );
		if ( imageTag.isExisting() ) {
			return imageTag.getAttribute( 'src' );
		}
		return null;
	}

	has_search_data_in_results( data ) {
		return this.results_block().$( `div.mw-search-result-data*=${data}` ).isExisting();
	}

	get_search_alt_title_at( nth ) {
		const resultBlock = this.get_result_element_at( nth );
		if ( resultBlock === null ) {
			return null;
		}
		const elt = resultBlock.$( 'span.searchalttitle' );
		if ( elt.isExisting() ) {
			return elt.getText();
		}
		return null;
	}

	get_result_at( nth ) {
		return this.results_block().$( `a[data-serp-pos="${nth - 1}"]` ).getAttribute( 'title' );
	}

	in_search_results( title ) {
		const elt = this.results_block().$( `a[title="${title}"]` );
		return elt.isExisting();
	}

	results_block() {
		const elt = browser.$( 'div.searchresults' );
		if ( !elt.isExisting() ) {
			throw new Error( 'Cannot locate search results block, are you on the SRP?' );
		}
		return elt;
	}

	click_search() {
		const forms = [ 'form#powersearch', 'form#search' ];
		for ( const form of forms ) {
			const elt = browser.$( form );
			if ( elt.isExisting() ) {
				elt.$( 'button[type="submit"]' ).click();
				return;
			}
		}
		throw new Error( 'Cannot click the search button, are you on the Search page?' );
	}

	/**
	 * @param {string} filter
	 */
	click_filter( filter ) {
		const linkSel = `a=${filter}`;
		browser.$( 'div.search-types' ).$( linkSel ).click();
	}

	/**
	 * @param {Array.<string>} namespaceLabels
	 * @param {boolean} first true to select first, false to select all
	 */
	select_namespaces( namespaceLabels, first ) {
		const elt = browser.$( 'form#powersearch fieldset#mw-searchoptions' );
		if ( !elt.isExisting() ) {
			throw new Error( "Cannot find the namespace filters, did you click on 'Advanced' first?" );
		}
		for ( const nsLabel of namespaceLabels ) {
			const labelSel = `label=${nsLabel}`;
			const label = elt.$( labelSel );
			if ( label.isExisting() ) {
				label.click();
				if ( first ) {
					return;
				}
			} else if ( !first ) {
				throw new Error( `Count not find namespace labeled as ${nsLabel}` );
			}
		}
		if ( first ) {
			throw new Error( `Count not find any namespace link labeled as ${namespaceLabels.join()}` );
		}
	}
}

module.exports = new SearchResultsPage();
