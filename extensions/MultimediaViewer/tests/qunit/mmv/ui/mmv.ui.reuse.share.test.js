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

( function ( mw, $ ) {
	function makeShare() {
		return new mw.mmv.ui.reuse.Share( $( '#qunit-fixture' ) );
	}

	QUnit.module( 'mmv.ui.reuse.share', QUnit.newMwEnvironment() );

	QUnit.test( 'Sanity test, object creation and UI construction', function ( assert ) {
		var share = makeShare();

		assert.ok( share, 'Share UI element is created.' );
		assert.strictEqual( share.$pane.length, 1, 'Pane div created.' );
		assert.ok( share.pageInput, 'Text field created.' );
		assert.ok( share.$pageLink, 'Link created.' );
	} );

	QUnit.test( 'set()/empty():', function ( assert ) {
		var share = makeShare(),
			image = { // fake mw.mmv.model.Image
				title: new mw.Title( 'File:Foobar.jpg' ),
				url: 'https://upload.wikimedia.org/wikipedia/commons/3/3a/Foobar.jpg',
				descriptionUrl: '//commons.wikimedia.org/wiki/File:Foobar.jpg'
			};

		assert.notStrictEqual( !share.pageInput.getValue(), '', 'pageInput is empty.' );

		share.select = function () {
			assert.ok( true, 'Text has been selected after data is set.' );
		};

		share.set( image );

		assert.notStrictEqual( share.pageInput.getValue(), '', 'pageInput is not empty.' );

		share.empty();

		assert.notStrictEqual( !share.pageInput.getValue(), '', 'pageInput is empty.' );
	} );

	QUnit.test( 'attach()/unattach():', function ( assert ) {
		var share = makeShare(),
			image = {
				title: new mw.Title( 'File:Foobar.jpg' ),
				url: 'https://upload.wikimedia.org/wikipedia/commons/3/3a/Foobar.jpg',
				descriptionUrl: '//commons.wikimedia.org/wiki/File:Foobar.jpg'
			};

		share.set( image );

		share.selectAllOnEvent = function () {
			assert.ok( false, 'selectAllOnEvent should not have been called.' );
		};

		// Triggering action events before attaching should do nothing
		// use of focus() would run into jQuery bug #14740 and similar issues
		share.pageInput.$element.find( 'input' ).triggerHandler( 'focus' );

		share.selectAllOnEvent = function () {
			assert.ok( true, 'selectAllOnEvent was called.' );
		};

		share.attach();

		// Action events should be handled now
		share.pageInput.$element.find( 'input' ).triggerHandler( 'focus' );

		// Test the unattach part
		share.selectAllOnEvent = function () {
			assert.ok( false, 'selectAllOnEvent should not have been called.' );
		};

		share.unattach();

		// Triggering action events now that we are unattached should do nothing
		share.pageInput.$element.find( 'input' ).triggerHandler( 'focus' );
	} );

}( mediaWiki, jQuery ) );
