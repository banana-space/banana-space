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
( function ( mw ) {
	mw.mmv = {
		/**
		 * Feature-detects SVG support. MuyltimediaViewer uses SVG icons extensively and is
		 * unusable without them.
		 *
		 * @member mw.mmv.MultimediaViewer
		 * @return {boolean}
		 */
		isBrowserSupported: function () {
			// From modernizr 2.6.1
			var ns = { svg: 'http://www.w3.org/2000/svg' };
			return !!document.createElementNS && !!document.createElementNS( ns.svg, 'svg' ).createSVGRect;
		}
	};
}( mediaWiki ) );
