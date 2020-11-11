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

( function ( mw, oo ) {
	/**
	 * Route for showing the main image on the page, (whatever that means might depend on the page).
	 * This is typically used on file pages.
	 *
	 * @class mw.mmv.routing.MainFileRoute
	 * @extends mw.mmv.routing.Route
	 * @constructor
	 */
	function MainFileRoute() {}
	oo.inheritClass( MainFileRoute, mw.mmv.routing.Route );
	mw.mmv.routing.MainFileRoute = MainFileRoute;
}( mediaWiki, OO ) );
