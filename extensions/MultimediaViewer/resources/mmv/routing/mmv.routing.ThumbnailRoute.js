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
	 * Route for a specific thumbnail on the current page. The thumbnail must be that of a wiki
	 * file (can't be e.g. an external image); can be a file from a remote repo though.
	 *
	 * @class mw.mmv.routing.ThumbnailRoute
	 * @extends mw.mmv.routing.Route
	 * @constructor
	 * @param {mw.Title} fileTitle the name of the image
	 */
	function ThumbnailRoute( fileTitle ) {
		if ( !fileTitle ) {
			throw new Error( 'mw.mmv.routing.ThumbnailRoute: fileTitle parameter is required' );
		}
		this.fileTitle = fileTitle;
	}
	oo.inheritClass( ThumbnailRoute, mw.mmv.routing.Route );
	mw.mmv.routing.ThumbnailRoute = ThumbnailRoute;
}( mediaWiki, OO ) );
