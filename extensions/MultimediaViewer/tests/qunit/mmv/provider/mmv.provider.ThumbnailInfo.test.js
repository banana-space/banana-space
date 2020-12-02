/*
 * This file is part of the MediaWiki extension MultimediaViewer.
 *
 * MultimediaViewer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * MultimediaViewer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with MultimediaViewer.  If not, see <http://www.gnu.org/licenses/>.
 */

( function () {
	QUnit.module( 'mmv.provider.ThumbnailInfo', QUnit.newMwEnvironment() );

	QUnit.test( 'ThumbnailInfo constructor sanity check', function ( assert ) {
		var api = { get: function () {} },
			thumbnailInfoProvider = new mw.mmv.provider.ThumbnailInfo( api );

		assert.ok( thumbnailInfoProvider );
	} );

	QUnit.test( 'ThumbnailInfo get test', function ( assert ) {
		var apiCallCount = 0,
			api = { get: function () {
				apiCallCount++;
				return $.Deferred().resolve( {
					query: {
						pages: {
							'-1': {
								ns: 6,
								title: 'File:Stuff.jpg',
								missing: '',
								imagerepository: 'shared',
								imageinfo: [
									{
										thumburl: 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/19/Stuff.jpg/51px-Stuff.jpg',
										thumbwidth: 95,
										thumbheight: 200,
										url: 'https://upload.wikimedia.org/wikipedia/commons/1/19/Stuff.jpg',
										descriptionurl: 'https://commons.wikimedia.org/wiki/File:Stuff.jpg'
									}
								]
							}
						}
					}
				} );
			} },
			file = new mw.Title( 'File:Stuff.jpg' ),
			thumbnailInfoProvider = new mw.mmv.provider.ThumbnailInfo( api );

		return thumbnailInfoProvider.get( file, 100 ).then( function ( thumbnail ) {
			assert.strictEqual( thumbnail.url,
				'https://upload.wikimedia.org/wikipedia/commons/thumb/1/19/Stuff.jpg/51px-Stuff.jpg',
				'URL is set correctly' );
			assert.strictEqual( thumbnail.width, 95, 'actual width is set correctly' );
			assert.strictEqual( thumbnail.height, 200, 'actual height is set correctly' );
		} ).then( function () {
			assert.strictEqual( apiCallCount, 1 );
			// call the data provider a second time to check caching
			return thumbnailInfoProvider.get( file, 100 );
		} ).then( function () {
			assert.strictEqual( apiCallCount, 1 );
			// call a third time with different size to check caching
			return thumbnailInfoProvider.get( file, 110 );
		} ).then( function () {
			assert.strictEqual( apiCallCount, 2 );
			// call it again, with a height specified, to check caching
			return thumbnailInfoProvider.get( file, 110, 100 );
		} ).then( function () {
			assert.strictEqual( apiCallCount, 3 );
		} );
	} );

	QUnit.test( 'ThumbnailInfo fail test', function ( assert ) {
		var api = { get: function () {
				return $.Deferred().resolve( {} );
			} },
			file = new mw.Title( 'File:Stuff.jpg' ),
			done = assert.async(),
			thumbnailInfoProvider = new mw.mmv.provider.ThumbnailInfo( api );

		thumbnailInfoProvider.get( file, 100 ).fail( function () {
			assert.ok( true, 'promise rejected when no data is returned' );
			done();
		} );
	} );

	QUnit.test( 'ThumbnailInfo fail test 2', function ( assert ) {
		var api = { get: function () {
				return $.Deferred().resolve( {
					query: {
						pages: {
							'-1': {
								title: 'File:Stuff.jpg'
							}
						}
					}
				} );
			} },
			file = new mw.Title( 'File:Stuff.jpg' ),
			done = assert.async(),
			thumbnailInfoProvider = new mw.mmv.provider.ThumbnailInfo( api );

		thumbnailInfoProvider.get( file, 100 ).fail( function () {
			assert.ok( true, 'promise rejected when imageinfo is missing' );
			done();
		} );
	} );

	QUnit.test( 'ThumbnailInfo missing page test', function ( assert ) {
		var api = { get: function () {
				return $.Deferred().resolve( {
					query: {
						pages: {
							'-1': {
								title: 'File:Stuff.jpg',
								missing: '',
								imagerepository: ''
							}
						}
					}
				} );
			} },
			file = new mw.Title( 'File:Stuff.jpg' ),
			done = assert.async(),
			thumbnailInfoProvider = new mw.mmv.provider.ThumbnailInfo( api );

		thumbnailInfoProvider.get( file ).fail( function ( errorMessage ) {
			assert.strictEqual( errorMessage, 'file does not exist: File:Stuff.jpg',
				'error message is set correctly for missing file' );
			done();
		} );
	} );

	QUnit.test( 'ThumbnailInfo fail test 3', function ( assert ) {
		var api = { get: function () {
				return $.Deferred().resolve( {
					query: {
						pages: {
							'-1': {
								title: 'File:Stuff.jpg',
								imageinfo: [
									{}
								]
							}
						}
					}
				} );
			} },
			file = new mw.Title( 'File:Stuff.jpg' ),
			done = assert.async(),
			thumbnailInfoProvider = new mw.mmv.provider.ThumbnailInfo( api );

		thumbnailInfoProvider.get( file, 100 ).fail( function () {
			assert.ok( true, 'promise rejected when thumbnail info is missing' );
			done();
		} );
	} );
}() );
