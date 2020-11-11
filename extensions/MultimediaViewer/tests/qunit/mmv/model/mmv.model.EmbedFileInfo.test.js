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
	QUnit.module( 'mmv.model.EmbedFileInfo', QUnit.newMwEnvironment() );

	QUnit.test( 'EmbedFileInfo constructor sanity check', function ( assert ) {
		var imageInfo = {},
			repoInfo = {},
			caption = 'Foo',
			alt = 'Bar',
			embedFileInfo = new mw.mmv.model.EmbedFileInfo( imageInfo, repoInfo, caption, alt );

		assert.strictEqual( embedFileInfo.imageInfo, imageInfo, 'ImageInfo is set correctly' );
		assert.strictEqual( embedFileInfo.repoInfo, repoInfo, 'ImageInfo is set correctly' );
		assert.strictEqual( embedFileInfo.caption, caption, 'Caption is set correctly' );
		assert.strictEqual( embedFileInfo.alt, alt, 'Alt text is set correctly' );

		try {
			embedFileInfo = new mw.mmv.model.EmbedFileInfo( {} );
		} catch ( e ) {
			assert.ok( e, 'Exception is thrown when parameters are missing' );
		}
	} );

}( mediaWiki ) );
