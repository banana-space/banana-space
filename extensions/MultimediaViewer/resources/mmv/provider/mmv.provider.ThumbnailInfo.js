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

( function ( mw, oo, $ ) {

	/**
	 * Gets thumbnail information.
	 *
	 * See https://www.mediawiki.org/wiki/API:Properties#imageinfo_.2F_ii
	 *
	 * @class mw.mmv.provider.ThumbnailInfo
	 * @extends mw.mmv.provider.Api
	 * @constructor
	 * @param {mw.Api} api
	 * @param {Object} [options]
	 * @cfg {number} [maxage] cache expiration time, in seconds
	 *  Will be used for both client-side cache (maxage) and reverse proxies (s-maxage)
	 */
	function ThumbnailInfo( api, options ) {
		mw.mmv.provider.Api.call( this, api, options );
	}
	oo.inheritClass( ThumbnailInfo, mw.mmv.provider.Api );

	/**
	 * Runs an API GET request to get the thumbnail info for the specified size.
	 * The thumbnail always has the same aspect ratio as the full image.
	 * One of width or height can be null; if both are set, the API will return the largest
	 * thumbnail which fits into a width x height bounding box (or the full-sized image - whichever
	 * is smaller).
	 *
	 * @param {mw.Title} file
	 * @param {number} width thumbnail width in pixels
	 * @param {number} height thumbnail height in pixels
	 * @return {jQuery.Promise.<mw.mmv.model.Thumbnail>}
	 */
	ThumbnailInfo.prototype.get = function ( file, width, height ) {
		var provider = this,
			cacheKey = file.getPrefixedDb() + '|' + ( width || '' ) + '|' + ( height || '' );

		return this.getCachedPromise( cacheKey, function () {
			return provider.apiGetWithMaxAge( {
				action: 'query',
				prop: 'imageinfo',
				titles: file.getPrefixedDb(),
				iiprop: 'url',
				iiurlwidth: width, // mw.Api will omit null/undefined parameters
				iiurlheight: height
			} ).then( function ( data ) {
				return provider.getQueryPage( file, data );
			} ).then( function ( page ) {
				var imageInfo;
				if ( page.imageinfo && page.imageinfo[ 0 ] ) {
					imageInfo = page.imageinfo[ 0 ];
					if ( imageInfo.thumburl && imageInfo.thumbwidth && imageInfo.thumbheight ) {
						return $.Deferred().resolve(
							new mw.mmv.model.Thumbnail(
								imageInfo.thumburl,
								imageInfo.thumbwidth,
								imageInfo.thumbheight
							)
						);
					} else {
						return $.Deferred().reject( 'error in provider, thumb info not found' );
					}
				} else if ( page.missing === '' && page.imagerepository === '' ) {
					return $.Deferred().reject( 'file does not exist: ' + file.getPrefixedDb() );
				} else {
					return $.Deferred().reject( 'unknown error' );
				}
			} );
		} );
	};

	mw.mmv.provider.ThumbnailInfo = ThumbnailInfo;
}( mediaWiki, OO, jQuery ) );
