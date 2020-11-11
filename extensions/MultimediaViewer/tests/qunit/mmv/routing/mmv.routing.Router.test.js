/*
 * This file is part of the MediaWiki extension MediaViewer.
 *
 * MediaViewer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * MediaViewer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with MediaViewer.  If not, see <http://www.gnu.org/licenses/>.
 */

( function ( mw ) {
	QUnit.module( 'mmv.routing.Router', QUnit.newMwEnvironment() );

	QUnit.test( 'Constructor sanity checks', function ( assert ) {
		var router;

		router = new mw.mmv.routing.Router();
		assert.ok( router, 'Router created successfully' );
	} );

	QUnit.test( 'isMediaViewerHash()', function ( assert ) {
		var router = new mw.mmv.routing.Router();

		assert.ok( router.isMediaViewerHash( 'mediaviewer/foo' ), 'Legacy hash' );
		assert.ok( router.isMediaViewerHash( '#mediaviewer/foo' ), 'Legacy hash with #' );
		assert.ok( router.isMediaViewerHash( 'mediaviewer' ), 'Bare legacy hash' );
		assert.ok( router.isMediaViewerHash( '#mediaviewer' ), 'Bare legacy hash with #' );
		assert.ok( router.isMediaViewerHash( '/media/foo' ), 'Normal hash' );
		assert.ok( router.isMediaViewerHash( '#/media/foo' ), 'Normal hash with #' );
		assert.ok( router.isMediaViewerHash( '/media' ), 'Bare hash' );
		assert.ok( router.isMediaViewerHash( '#/media' ), 'Bare hash with #' );
		assert.ok( !router.isMediaViewerHash( 'foo/media' ), 'Foreign hash' );
		assert.ok( !router.isMediaViewerHash( '' ), 'Empty hash' );
	} );

	QUnit.test( 'createHash()/parseHash()', function ( assert ) {
		var route, parsedRoute, hash, title,
			router = new mw.mmv.routing.Router();

		route = new mw.mmv.routing.MainFileRoute();
		hash = router.createHash( route );
		parsedRoute = router.parseHash( hash );
		assert.deepEqual( parsedRoute, route, 'Bare hash' );

		title = new mw.Title( 'File:Foo.png' );
		route = new mw.mmv.routing.ThumbnailRoute( title );
		hash = router.createHash( route );
		parsedRoute = router.parseHash( hash );
		assert.strictEqual( parsedRoute.fileTitle.getPrefixedDb(),
			title.getPrefixedDb(), 'Normal hash' );
		assert.ok( hash.match( /File:Foo.png/ ), 'Simple filenames remain readable' );

		title = new mw.Title( 'File:Foo.png' );
		route = new mw.mmv.routing.ThumbnailRoute( title );
		hash = router.createHash( route );
		assert.notEqual( hash[ 0 ], '#', 'Leading # is not included in the returned hash' );
		parsedRoute = router.parseHash( '#' + hash );
		assert.strictEqual( parsedRoute.fileTitle.getPrefixedDb(),
			title.getPrefixedDb(), 'Leading # is accepted when parsing a hash' );

		title = new mw.Title( 'File:Foo.png' );
		route = new mw.mmv.routing.ThumbnailRoute( title );
		hash = router.createHash( route );
		parsedRoute = router.parseHash( hash );
		assert.strictEqual( parsedRoute.fileTitle.getPrefixedDb(),
			title.getPrefixedDb(), 'Normal hash' );
		assert.ok( hash.match( /File:Foo.png/ ), 'Simple filenames remain readable' );

		title = new mw.Title( 'File:Foo/bar.png' );
		route = new mw.mmv.routing.ThumbnailRoute( title );
		hash = router.createHash( route );
		parsedRoute = router.parseHash( hash );
		assert.strictEqual( parsedRoute.fileTitle.getPrefixedDb(),
			title.getPrefixedDb(), 'Filename with /' );
		assert.ok( !hash.match( 'Foo/bar' ), '/ is encoded' );

		title = new mw.Title( 'File:Foo bar.png' );
		route = new mw.mmv.routing.ThumbnailRoute( title );
		hash = router.createHash( route );
		parsedRoute = router.parseHash( hash );
		assert.strictEqual( parsedRoute.fileTitle.getPrefixedDb(),
			title.getPrefixedDb(), 'Filename with space' );
		assert.ok( !hash.match( 'Foo bar' ), 'space is replaced...' );
		assert.ok( hash.match( 'Foo_bar' ), '...with underscore' );

		title = new mw.Title( 'File:看門狗 (遊戲).jpg' );
		route = new mw.mmv.routing.ThumbnailRoute( title );
		hash = router.createHash( route );
		parsedRoute = router.parseHash( hash );
		assert.strictEqual( parsedRoute.fileTitle.getPrefixedDb(),
			title.getPrefixedDb(), 'Unicode filename' );

		title = new mw.Title( 'File:%!"$&\'()*,-./:;=?@\\^_`~+.jpg' );
		if ( title ) {
			route = new mw.mmv.routing.ThumbnailRoute( title );
			hash = router.createHash( route );
			parsedRoute = router.parseHash( hash );
			assert.strictEqual( parsedRoute.fileTitle.getPrefixedDb(),
				title.getPrefixedDb(), 'Special characters' );
		} else {
			// mw.Title depends on $wgLegalTitleChars - do not fail test if it is non-standard
			assert.ok( true, 'Skipped' );
		}
	} );

	QUnit.test( 'createHash() error handling', function ( assert ) {
		var router = new mw.mmv.routing.Router();

		assert.ok( mw.mmv.testHelpers.getException( function () { return new mw.mmv.routing.ThumbnailRoute(); } ),
			'Exception thrown then ThumbnailRoute has no title' );
		assert.ok( mw.mmv.testHelpers.getException( function () {
			router.createHash( this.sandbox.createStubInstance( mw.mmv.routing.Route ) );
		} ), 'Exception thrown for unknown Route subclass' );
		assert.ok( mw.mmv.testHelpers.getException( function () {
			router.createHash( {} );
		} ), 'Exception thrown for non-Route class' );
	} );

	QUnit.test( 'parseHash() with invalid hashes', function ( assert ) {
		var router = new mw.mmv.routing.Router();

		assert.ok( !router.parseHash( 'foo' ), 'Non-MMV hash is rejected.' );
		assert.ok( !router.parseHash( '#foo' ), 'Non-MMV hash is rejected (with #).' );
		assert.ok( !router.parseHash( '/media/foo/bar' ), 'Invalid MMV hash is rejected.' );
		assert.ok( !router.parseHash( '#/media/foo/bar' ), 'Invalid MMV hash is rejected (with #).' );
	} );

	QUnit.test( 'parseHash() backwards compatibility', function ( assert ) {
		var route,
			router = new mw.mmv.routing.Router();

		route = router.parseHash( '#mediaviewer/File:Foo bar.png' );
		assert.strictEqual( route.fileTitle.getPrefixedDb(), 'File:Foo_bar.png',
			'Old urls (with space) are handled' );

		route = router.parseHash( '#mediaviewer/File:Mexican \'Alien\' Piñata.jpg' );
		assert.strictEqual( route.fileTitle.getPrefixedDb(), 'File:Mexican_\'Alien\'_Piñata.jpg',
			'Old urls (without percent-encoding) are handled' );
	} );

	QUnit.test( 'createHashedUrl()', function ( assert ) {
		var url,
			route = new mw.mmv.routing.MainFileRoute(),
			router = new mw.mmv.routing.Router();

		url = router.createHashedUrl( route, 'http://example.com/' );
		assert.strictEqual( url, 'http://example.com/#/media', 'Url generation works' );

		url = router.createHashedUrl( route, 'http://example.com/#foo' );
		assert.strictEqual( url, 'http://example.com/#/media', 'Urls with fragments are handled' );
	} );

	QUnit.test( 'parseLocation()', function ( assert ) {
		var location, route,
			router = new mw.mmv.routing.Router();

		location = { href: 'http://example.com/foo#mediaviewer/File:Foo.png' };
		route = router.parseLocation( location );
		assert.strictEqual( route.fileTitle.getPrefixedDb(), 'File:Foo.png', 'Reading location works' );

		location = { href: 'http://example.com/foo#/media/File:Foo.png' };
		route = router.parseLocation( location );
		assert.strictEqual( route.fileTitle.getPrefixedDb(), 'File:Foo.png', 'Reading location works' );

		location = { href: 'http://example.com/foo' };
		route = router.parseLocation( location );
		assert.ok( !route, 'Reading location without fragment part works' );
	} );

	QUnit.test( 'parseLocation() with real location', function ( assert ) {
		var route, title, hash,
			router = new mw.mmv.routing.Router();

		// mw.Title does not accept % in page names
		this.sandbox.stub( mw, 'Title', function ( name ) {
			return {
				name: name,
				getMain: function () { return name.replace( /^File:/, '' ); }
			};
		} );
		title = new mw.Title( 'File:%40.png' );
		hash = router.createHash( new mw.mmv.routing.ThumbnailRoute( title ) );

		window.location.hash = hash;
		route = router.parseLocation( window.location );
		assert.strictEqual( route.fileTitle.getMain(), '%40.png',
			'Reading location set via location.hash works' );

		if ( window.history ) {
			window.history.pushState( null, null, '#' + hash );
			route = router.parseLocation( window.location );
			assert.strictEqual( route.fileTitle.getMain(), '%40.png',
				'Reading location set via pushState() works' );
		} else {
			assert.ok( true, 'Skipped pushState() test, not supported on this browser' );
		}

		// reset location, might interfere with other tests
		window.location.hash = '#';
	} );

	QUnit.test( 'tokenizeHash()', function ( assert ) {
		var router = new mw.mmv.routing.Router();

		router.legacyPrefix = 'legacy';
		router.applicationPrefix = 'prefix';

		assert.deepEqual( router.tokenizeHash( '#foo/bar' ), [], 'No known prefix' );

		assert.deepEqual( router.tokenizeHash( '#prefix' ), [ 'prefix' ], 'Current prefix, with #' );
		assert.deepEqual( router.tokenizeHash( 'prefix' ), [ 'prefix' ], 'Current prefix, without #' );
		assert.deepEqual( router.tokenizeHash( '#prefix/bar' ), [ 'prefix', 'bar' ], 'Current prefix, with # and element' );
		assert.deepEqual( router.tokenizeHash( 'prefix/bar' ), [ 'prefix', 'bar' ], 'Current prefix, with element without #' );
		assert.deepEqual( router.tokenizeHash( '#prefix/bar/baz' ), [ 'prefix', 'bar', 'baz' ], 'Current prefix, with # and 2 elements' );
		assert.deepEqual( router.tokenizeHash( 'prefix/bar/baz' ), [ 'prefix', 'bar', 'baz' ], 'Current prefix, with 2 elements without #' );

		assert.deepEqual( router.tokenizeHash( '#legacy' ), [ 'legacy' ], 'Legacy prefix, with #' );
		assert.deepEqual( router.tokenizeHash( 'legacy' ), [ 'legacy' ], 'Legacy prefix, without #' );
		assert.deepEqual( router.tokenizeHash( '#legacy/bar' ), [ 'legacy', 'bar' ], 'Legacy prefix, with # and element' );
		assert.deepEqual( router.tokenizeHash( 'legacy/bar' ), [ 'legacy', 'bar' ], 'Legacy prefix, with element without #' );
		assert.deepEqual( router.tokenizeHash( '#legacy/bar/baz' ), [ 'legacy', 'bar', 'baz' ], 'Legacy prefix, with # and 2 elements' );
		assert.deepEqual( router.tokenizeHash( 'legacy/bar/baz' ), [ 'legacy', 'bar', 'baz' ], 'Legacy prefix, with 2 elements without #' );

	} );
}( mediaWiki ) );
