'use strict';

const Page = require( 'wdio-mediawiki/Page' ),
	Util = require( 'wdio-mediawiki/Util' );

class CitePage extends Page {
	getReference( num ) { return $$( '#mw-content-text .reference' )[ num - 1 ]; }
	getCiteMultiBacklink( num ) { return $( '.references li:nth-of-type(' + num + ') .mw-cite-up-arrow-backlink' ); }
	getCiteSingleBacklink( num ) { return $( '.references li:nth-of-type(' + num + ') .mw-cite-backlink a' ); }
	getCiteSubBacklink( num ) { return $( '.mw-cite-backlink sup:nth-of-type(' + num + ') a' ); }

	scriptsReady() {
		Util.waitForModuleState( 'ext.cite.ux-enhancements' );
	}

	getFragmentFromLink( linkElement ) {
		// the href includes the full url so slice the fragment from it
		const href = linkElement.getAttribute( 'href' );
		return href.slice( href.indexOf( '#' ) + 1 );
	}
}

module.exports = new CitePage();
