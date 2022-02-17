/**
 * The Page object contains shortcuts and properties
 */

'use strict';

class Page {
	constructor() {
		// tag selector shortcut.
		// analogous to Ruby's link(:create_link, text: "Create") etc.
		// assuming first param is a selector, second is text.
		[ 'h1',
			'table',
			'td',
			'a',
			'ul',
			'li',
			'button',
			'textarea',
			'div',
			'span',
			'p',
			'input[type=text]',
			'input[type=submit]'
		].forEach( ( el ) => {
			let alias = el;
			switch ( el ) {
				case 'a':
					alias = 'link';
					break;
				case 'input[type=text]':
					alias = 'text_field';
					break;
				case 'textarea':
					alias = 'text_area';
					break;
				case 'p':
					alias = 'paragraph';
					break;
				case 'ul':
					alias = 'unordered_list';
					break;
				case 'td':
					alias = 'cell';
					break;
			}
			// the text option here doesn't work on child selectors
			// when more that one element is returned.
			// so "table.many-tables td=text" doesn't work!
			this[ el ] = this[ alias ] = ( selector, text ) => {
				const s = selector || '';
				const t = ( text ) ? '=' + text : '';
				const sel = el + s + t;
				return browser.$$( sel );
			};
		} );
	}

	collect_element_texts( selector ) {
		const elements = browser.$$( selector );
		const texts = [];
		for ( const text of elements ) {
			texts.push( text.getText() );
		}
		return texts;
	}

	collect_element_attribute( attr, selector ) {
		const elements = browser.$$( selector );
		const texts = [];
		for ( const elt of elements ) {
			texts.push( elt.getAttribute( attr ) );
		}
		return texts;
	}

	get url() {
		return this._url;
	}

	set url( url ) {
		this._url = url;
		browser.url( url );
	}

	login( world, wiki = false ) {
		const config = wiki ?
			world.config.wikis[ wiki ] :
			world.config.wikis[ world.config.wikis.default ];
		world.visit( '/wiki/Special:UserLogin' );
		browser.$( '#wpName1' ).setValue( config.username );
		browser.$( '#wpPassword1' ).setValue( config.password );
		browser.$( '#wpLoginAttempt' ).click();
		// skip password reset, not always present?
		const skip = browser.$( '#mw-input-skipReset' );
		if ( skip.isExisting() ) {
			skip.click();
		}
	}
}
module.exports = Page;
