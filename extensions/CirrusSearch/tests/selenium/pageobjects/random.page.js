'use strict';
const Page = require( 'wdio-mediawiki/Page' );

class RandomPage extends Page {
	open() {
		super.openTitle( 'Special:RandomPage' );
	}
}
module.exports = new RandomPage();
