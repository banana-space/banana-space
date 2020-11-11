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

( function ( mw, $ ) {
	QUnit.module( 'mmv.ui.tipsyDialog', QUnit.newMwEnvironment( {
		setup: function () {
			// remove tipsy elements left behind by other tests so these tests don't find them by accident
			// tipsy puts its elements to the end of the body so clearing the fixture doesn't get rid of them
			$( '.mw-mmv-tipsy-dialog' ).remove();
		}
	} ) );

	QUnit.test( 'Open/close', function ( assert ) {
		var $qf = $( '#qunit-fixture' ),
			$anchor = $( '<div>' ).appendTo( $qf ),
			dialog = new mw.mmv.ui.TipsyDialog( $anchor );

		assert.ok( !$( '.mw-mmv-tipsy-dialog' ).length, 'dialog is not shown' );
		dialog.open();
		assert.ok( $( '.mw-mmv-tipsy-dialog' ).length, 'dialog is shown' );
		dialog.close();
		assert.ok( !$( '.mw-mmv-tipsy-dialog' ).length, 'dialog is not shown' );
	} );

	QUnit.test( 'setContent', function ( assert ) {
		var $qf = $( '#qunit-fixture' ),
			$anchor = $( '<div>' ).appendTo( $qf ),
			titleText = 'This is a title',
			bodyText = 'This is the <b class="typsyDialogTest-123">body</b>',
			dialog = new mw.mmv.ui.TipsyDialog( $anchor );

		dialog.setContent( titleText, bodyText );
		dialog.open();
		assert.ok( $( '.mw-mmv-tipsy-dialog' ).text().match( titleText ), 'Title is included' );
		assert.ok( $( '.mw-mmv-tipsy-dialog' ).html().match( bodyText ), 'Body is included' );
		assert.ok( $( '.mw-mmv-tipsy-dialog' ).find( '.typsyDialogTest-123' ).length, 'Body is HTML' );
	} );

	QUnit.test( 'Close on click', function ( assert ) {
		var $qf = $( '#qunit-fixture' ),
			$anchor = $( '<div>' ).appendTo( $qf ),
			dialog = new mw.mmv.ui.TipsyDialog( $anchor );

		dialog.open();
		assert.ok( $( '.mw-mmv-tipsy-dialog' ).length, 'dialog is shown initially' );
		dialog.getPopup().click();
		assert.ok( $( '.mw-mmv-tipsy-dialog' ).length, 'dialog is not hidden when clicked' );
		dialog.getPopup().find( '.mw-mmv-tipsy-dialog-disable' ).click();
		assert.ok( !$( '.mw-mmv-tipsy-dialog' ).length, 'dialog is hidden when close icon is clicked' );
		dialog.open();
		$qf.click();
		assert.ok( !$( '.mw-mmv-tipsy-dialog' ).length, 'dialog is hidden when clicked outside' );
	} );
}( mediaWiki, jQuery ) );
