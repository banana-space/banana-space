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

( function () {

	/**
	 * Gets file information.
	 *
	 * See https://www.mediawiki.org/wiki/API:Properties#imageinfo_.2F_ii
	 *
	 * @class mw.mmv.provider.ImageInfo
	 * @extends mw.mmv.provider.Api
	 * @constructor
	 * @param {mw.Api} api
	 * @param {Object} [options]
	 * @cfg {string} [language=null] image metadata language
	 * @cfg {number} [maxage] cache expiration time, in seconds
	 *  Will be used for both client-side cache (maxage) and reverse proxies (s-maxage)
	 */
	function ImageInfo( api, options ) {
		options = $.extend( {
			language: null
		}, options );

		mw.mmv.provider.Api.call( this, api, options );
	}
	OO.inheritClass( ImageInfo, mw.mmv.provider.Api );

	/**
	 * List of imageinfo API properties which are needed to construct an Image model.
	 *
	 * @property {string}
	 */
	ImageInfo.prototype.iiprop = [
		'timestamp',
		'url',
		'size',
		'mime',
		'mediatype',
		'extmetadata'
	].join( '|' );

	/**
	 * List of imageinfo extmetadata fields which are needed to construct an Image model.
	 *
	 * @property {string}
	 */
	ImageInfo.prototype.iiextmetadatafilter = [
		'DateTime',
		'DateTimeOriginal',
		'ObjectName',
		'ImageDescription',
		'License',
		'LicenseShortName',
		'UsageTerms',
		'LicenseUrl',
		'Credit',
		'Artist',
		'AuthorCount',
		'GPSLatitude',
		'GPSLongitude',
		'Permission',
		'Attribution',
		'AttributionRequired',
		'NonFree',
		'Restrictions',
		'DeletionReason'
	].join( '|' );

	/**
	 * Runs an API GET request to get the image info.
	 *
	 * @param {mw.Title} file
	 * @return {jQuery.Promise} a promise which resolves to an mw.mmv.model.Image object.
	 */
	ImageInfo.prototype.get = function ( file ) {
		var provider = this;

		return this.getCachedPromise( file.getPrefixedDb(), function () {
			return provider.apiGetWithMaxAge( {
				action: 'query',
				prop: 'imageinfo',
				titles: file.getPrefixedDb(),
				iiprop: provider.iiprop,
				iiextmetadatafilter: provider.iiextmetadatafilter,
				iiextmetadatalanguage: provider.options.language,
				uselang: 'content'
			} ).then( function ( data ) {
				return provider.getQueryPage( file, data );
			} ).then( function ( page ) {
				if ( page.imageinfo && page.imageinfo.length ) {
					return mw.mmv.model.Image.newFromImageInfo( file, page );
				} else if ( page.missing === '' && page.imagerepository === '' ) {
					return $.Deferred().reject( 'file does not exist: ' + file.getPrefixedDb() );
				} else {
					return $.Deferred().reject( 'unknown error' );
				}
			} );
		} );
	};

	mw.mmv.provider.ImageInfo = ImageInfo;
}() );
