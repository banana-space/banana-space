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
	QUnit.module( 'mmv.model', QUnit.newMwEnvironment() );

	QUnit.test( 'Thumbnail constructor sanity check', function ( assert ) {
		var width = 23,
			height = 42,
			url = 'http://example.com/foo.jpg',
			thumbnail = new mw.mmv.model.Thumbnail( url, width, height );

		assert.strictEqual( thumbnail.url, url, 'Url is set correctly' );
		assert.strictEqual( thumbnail.width, width, 'Width is set correctly' );
		assert.strictEqual( thumbnail.height, height, 'Height is set correctly' );

		try {
			thumbnail = new mw.mmv.model.Thumbnail( url, width );
		} catch ( e ) {
			assert.ok( e, 'Exception is thrown when parameters are missing' );
		}
	} );

	QUnit.test( 'ThumbnailWidth constructor sanity check', function ( assert ) {
		var cssWidth = 23,
			cssHeight = 29,
			screenWidth = 42,
			realWidth = 123,
			thumbnailWidth = new mw.mmv.model.ThumbnailWidth(
				cssWidth, cssHeight, screenWidth, realWidth );

		assert.strictEqual( thumbnailWidth.cssWidth, cssWidth, 'Width is set correctly' );
		assert.strictEqual( thumbnailWidth.cssHeight, cssHeight, 'Height is set correctly' );
		assert.strictEqual( thumbnailWidth.screen, screenWidth, 'Screen width is set correctly' );
		assert.strictEqual( thumbnailWidth.real, realWidth, 'Real width is set correctly' );

		try {
			thumbnailWidth = new mw.mmv.model.ThumbnailWidth( cssWidth, screenWidth );
		} catch ( e ) {
			assert.ok( e, 'Exception is thrown when parameters are missing' );
		}
	} );

}() );
