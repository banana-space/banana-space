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

( function () {
	QUnit.module( 'mw.mmv.ui.Permission', QUnit.newMwEnvironment( {
		setup: function () {
			// animation would keep running, conflict with other tests
			this.sandbox.stub( $.fn, 'animate' ).returnsThis();
		}
	} ) );

	QUnit.test( 'Constructor sanity check', function ( assert ) {
		var $qf = $( '#qunit-fixture' ),
			permission = new mw.mmv.ui.Permission( $qf );

		assert.ok( permission, 'constructor does not throw error' );
	} );

	QUnit.test( 'set()', function ( assert ) {
		var $qf = $( '#qunit-fixture' ),
			permission = new mw.mmv.ui.Permission( $qf ),
			text = 'Nothing to see here.';

		permission.set( text );

		// FIXME get rid of "view more" - this is temporary
		assert.strictEqual( permission.$text.children().remove().end().text(),
			text, 'permission text is set' );
		assert.strictEqual( permission.$html.text(), text, 'permission html is set' );
		assert.strictEqual( permission.$text.is( ':visible' ), true, 'permission text is visible' );
		assert.strictEqual( permission.$html.is( ':visible' ), false, 'permission html is not visible' );
		assert.strictEqual( permission.$close.is( ':visible' ), false, 'close button is not visible' );
	} );

	QUnit.test( 'set() with html', function ( assert ) {
		var $qf = $( '#qunit-fixture' ),
			permission = new mw.mmv.ui.Permission( $qf ),
			text = '<b>Nothing</b> to see here.';

		permission.set( text );

		assert.strictEqual( permission.$text.find( 'b' ).length, 0, 'permission text has no html' );
		assert.strictEqual( permission.$html.find( 'b' ).length, 1, 'permission html has html' );
	} );

	QUnit.test( 'empty()', function ( assert ) {
		var $qf = $( '#qunit-fixture' ),
			permission = new mw.mmv.ui.Permission( $qf ),
			text = 'Nothing to see here.';

		permission.set( text );
		permission.empty();

		assert.strictEqual( permission.$text.is( ':visible' ), false, 'permission text is not visible' );
		assert.strictEqual( permission.$html.is( ':visible' ), false, 'permission html is not visible' );
		assert.strictEqual( permission.$close.is( ':visible' ), false, 'close button is not visible' );
	} );

	QUnit.test( 'grow()', function ( assert ) {
		var $qf = $( '#qunit-fixture' ),
			permission = new mw.mmv.ui.Permission( $qf ),
			text = 'Nothing to see here.';

		permission.set( text );
		permission.grow();

		assert.strictEqual( permission.$text.is( ':visible' ), false, 'permission text is not visible' );
		assert.strictEqual( permission.$html.is( ':visible' ), true, 'permission html is visible' );
		assert.strictEqual( permission.$close.is( ':visible' ), true, 'close button is visible' );
	} );

	QUnit.test( 'shrink()', function ( assert ) {
		var $qf = $( '#qunit-fixture' ),
			permission = new mw.mmv.ui.Permission( $qf ),
			text = 'Nothing to see here.';

		permission.set( text );
		permission.grow();
		permission.shrink();

		assert.strictEqual( permission.$text.is( ':visible' ), true, 'permission text is visible' );
		assert.strictEqual( permission.$html.is( ':visible' ), false, 'permission html is not visible' );
		assert.strictEqual( permission.$close.is( ':visible' ), false, 'close button is not visible' );
	} );

	QUnit.test( 'isFullSize()', function ( assert ) {
		var $qf = $( '#qunit-fixture' ),
			permission = new mw.mmv.ui.Permission( $qf ),
			text = 'Nothing to see here.';

		permission.set( text );
		assert.strictEqual( permission.isFullSize(), false, 'permission is not full-size' );
		permission.grow();
		assert.strictEqual( permission.isFullSize(), true, 'permission is full-size' );
		permission.shrink();
		assert.strictEqual( permission.isFullSize(), false, 'permission is not full-size again' );
	} );
}() );
