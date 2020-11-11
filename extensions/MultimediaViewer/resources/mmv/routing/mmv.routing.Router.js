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
	var RP;

	/**
	 * Converts between routes and their URL hash representations such as `mediaviewer/File:Foo`.
	 *
	 * @class mw.mmv.routing.Router
	 * @constructor
	 */
	function Router() {}
	RP = Router.prototype;

	/**
	 * The prefix originally used to namespace MediaViewer routing hashes. Since there are many links
	 * out there pointing to those URLs, we should keep them working.
	 *
	 * @protected
	 * @property {string}
	 */
	RP.legacyPrefix = 'mediaviewer';

	/**
	 * The prefix used to namespace MediaViewer routing hashes
	 *
	 * @protected
	 * @property {string}
	 */
	RP.applicationPrefix = '/media';

	/**
	 * Takes an URL hash and returns a route (or null if it could not be parsed).
	 * Returns null for URL hashes which were not created by MediaViewer; you should use
	 * #isMediaViewerHash() if you want to differentiate such hashes.
	 * The hash can contain the starting `#` but does not have to; it should be in raw (percent-
	 * encoded) form. Note that the percent-encoding behavior of location.hash is not consistent
	 * between browsers; location.href can be used instead.
	 *
	 * @param {string} hash
	 * @return {mw.mmv.routing.Route|null}
	 */
	RP.parseHash = function ( hash ) {
		var hashParts, fileName;

		hashParts = this.tokenizeHash( hash );

		if ( hashParts.length === 0 ) {
			return null;
		} else if ( hashParts.length === 1 ) {
			return new mw.mmv.routing.MainFileRoute();
		} else if ( hashParts.length === 2 ) {
			fileName = this.decodeRouteComponent( hashParts[ 1 ] );
			return new mw.mmv.routing.ThumbnailRoute( new mw.Title( fileName ) );
		}

		return null;
	};

	/**
	 * Takes a route and returns a string representation which can be used in the URL fragment.
	 * The string does not contain the starting `#`, and it is encoded and guaranteed to be a
	 * valid URL.
	 *
	 * @param {mw.mmv.routing.Route} route
	 * @return {string}
	 */
	RP.createHash = function ( route ) {
		if ( route instanceof mw.mmv.routing.ThumbnailRoute ) {
			return this.applicationPrefix + '/' +
				this.encodeRouteComponent( 'File:' + route.fileTitle.getMain() );
		} else if ( route instanceof mw.mmv.routing.MainFileRoute ) {
			return this.applicationPrefix;
		} else if ( route instanceof mw.mmv.routing.Route ) {
			throw new Error( 'mw.mmv.routing.Router.createHash: not implemented for ' + route.constructor.name );
		} else {
			throw new Error( 'mw.mmv.routing.Router.createHash: invalid argument' );
		}
	};

	/**
	 * Like #parseHash(), but takes a window.location object. This is a helper function to make
	 * sure that hashes are decoded correctly in spite of browser inconsistencies.
	 *
	 * @param {{href: string}} location window.location object
	 * @return {mw.mmv.routing.Route|null}
	 */
	RP.parseLocation = function ( location ) {
		// Firefox percent-decodes location.hash: https://bugzilla.mozilla.org/show_bug.cgi?id=483304
		// which would cause inconsistent cross-browser behavior for files which have % or /
		// characters in their names. Using location.href is safe.
		return this.parseHash( location.href.split( '#' )[ 1 ] || '' );
	};

	/**
	 * Like #createHash(), but appends the hash to a specified URL
	 *
	 * @param {mw.mmv.routing.Route} route
	 * @param {string} baseUrl the URL of the page the image is on (can contain a hash part,
	 *  which will be stripped)
	 * @return {string} an URL to the same page as baseUrl, with the hash for the given route
	 */
	RP.createHashedUrl = function ( route, baseUrl ) {
		return baseUrl.replace( /#.*/, '' ) + '#' + this.createHash( route );
	};

	/**
	 * Returns true if this hash looks like it was created by MediaViewer.
	 * The hash can contain the starting `#` but does not have to.
	 *
	 * @param {string} hash
	 * @return {boolean}
	 */
	RP.isMediaViewerHash = function ( hash ) {
		return this.tokenizeHash( hash ).length !== 0;
	};

	/**
	 * Returns "segments" of a hash. The first segment is always the #applicationPrefix.
	 * If the hash is not a MediaViewer routing hash, an empty array is returned.
	 * The input hash can contain the starting `#` but does not have to.
	 *
	 * @protected
	 * @param {string} hash
	 * @return {string[]}
	 */
	RP.tokenizeHash = function ( hash ) {
		var prefix,
			hashParts;

		if ( hash[ 0 ] === '#' ) {
			hash = hash.slice( 1 );
		}

		if ( hash.indexOf( this.legacyPrefix ) === 0 ) {
			prefix = this.legacyPrefix;
		}

		if ( hash.indexOf( this.applicationPrefix ) === 0 ) {
			prefix = this.applicationPrefix;
		}

		if ( prefix === undefined ) {
			return [];
		}

		hash = hash.slice( prefix.length );

		hashParts = hash.split( '/' );
		hashParts[ 0 ] = prefix;

		return hashParts;
	};

	/**
	 * URL-encodes a route component.
	 * Almost identical to mw.util.wikiUrlencode but makes sure there are no unencoded `/`
	 * characters left since we use those to delimit components.
	 *
	 * @protected
	 * @param {string} component
	 * @return {string}
	 */
	RP.encodeRouteComponent = function ( component ) {
		return mw.util.wikiUrlencode( component ).replace( /\//g, '%2F' );
	};

	/**
	 * URL-decodes a route component.
	 * This is basically just a standard percent-decode, but for backwards compatibility with
	 * older schemes, we also replace spaces which underlines (the current scheme never has spaces).
	 *
	 * @protected
	 * @param {string} component
	 * @return {string}
	 */
	RP.decodeRouteComponent = function ( component ) {
		return decodeURIComponent( component ).replace( / /g, '_' );
	};

	mw.mmv.routing.Router = Router;
}( mediaWiki ) );
