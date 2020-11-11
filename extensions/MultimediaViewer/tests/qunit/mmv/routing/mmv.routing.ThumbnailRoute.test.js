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
	QUnit.module( 'mmv.routing.ThumbnailRoute', QUnit.newMwEnvironment() );

	QUnit.test( 'Constructor sanity checks', function ( assert ) {
		var route,
			title = new mw.Title( 'File:Foo.png' );

		route = new mw.mmv.routing.ThumbnailRoute( title );
		assert.ok( route, 'ThumbnailRoute created successfully' );

		assert.ok( mw.mmv.testHelpers.getException( function () {
			return new mw.mmv.routing.ThumbnailRoute();
		} ), 'Exception is thrown when ThumbnailRoute is created without arguments' );
	} );
}( mediaWiki ) );
