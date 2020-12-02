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

// Included on every page which has images so keep it lightweight.
( function () {
	mw.mmv = {
		/**
		 * The media route prefix
		 *
		 * @member mw.mmv
		 */
		ROUTE: 'media',
		/**
		 * RegExp representing the media route
		 *
		 * @member mw.mmv
		 */
		ROUTE_REGEXP: /^\/media\/(.+)$/,
		/**
		 * @property {RegExp}
		 * Regular expression representing the legacy media route
		 * @member mw.mmv
		 */
		LEGACY_ROUTE_REGEXP: /^mediaviewer\/(.+)$/,
		/**
		 * Returns the location hash (route string) for the given file title.
		 *
		 * @param {string} imageFileTitle the file title
		 * @return {string} the location hash
		 * @member mw.mmv
		 */
		getMediaHash: function ( imageFileTitle ) {
			return '#/' + mw.mmv.ROUTE + '/' + imageFileTitle;
		}
	};
}() );
