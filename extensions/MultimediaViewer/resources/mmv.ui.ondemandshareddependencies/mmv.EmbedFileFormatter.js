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
	var EFFP;

	/**
	 * Converts data in various formats needed by the Embed sub-dialog
	 *
	 * @class mw.mmv.EmbedFileFormatter
	 * @constructor
	 */
	function EmbedFileFormatter() {
		/** @property {mw.mmv.HtmlUtils} htmlUtils - */
		this.htmlUtils = new mw.mmv.HtmlUtils();
	}
	EFFP = EmbedFileFormatter.prototype;

	/**
	 * Returns the caption of the image (possibly a fallback generated from image metadata).
	 *
	 * @param {Object} info
	 * @param {mw.mmv.model.Image} info.imageInfo
	 * @param {string} [info.caption]
	 * @return {string}
	 */
	EFFP.getCaption = function ( info ) {
		if ( info.caption ) {
			return this.htmlUtils.htmlToText( info.caption );
		} else {
			return info.imageInfo.title.getNameText();
		}
	};

	/**
	 * Helper function to generate thumbnail wikicode
	 *
	 * @param {mw.Title} title
	 * @param {number} [width]
	 * @param {string} [caption]
	 * @param {string} [alt]
	 * @return {string}
	 */
	EFFP.getThumbnailWikitext = function ( title, width, caption, alt ) {
		var widthSection, captionSection, altSection;

		widthSection = width ? '|' + width + 'px' : '';
		captionSection = caption ? '|' + caption : '';
		altSection = alt ? '|alt=' + alt : '';

		return '[[File:' + title.getMainText() + widthSection + '|thumb' + captionSection + altSection + ']]';
	};

	/**
	 * Helper function to generate thumbnail wikicode
	 *
	 * @param {Object} info
	 * @param {mw.mmv.model.Image} info.imageInfo
	 * @param {number} [width]
	 * @return {string}
	 */
	EFFP.getThumbnailWikitextFromEmbedFileInfo = function ( info, width ) {
		return this.getThumbnailWikitext( info.imageInfo.title, width, this.getCaption( info ), info.alt );
	};

	/**
	 * Byline construction
	 *
	 * @param {string} [author] author name (can contain HTML)
	 * @param {string} [source] source name (can contain HTML)
	 * @param {string} [attribution] custom attribution line (can contain HTML)
	 * @param {Function} [formatterFunction] Format function for the text - defaults to whitelisting HTML links, but all else sanitized.
	 * @return {string} Byline (can contain HTML)
	 */
	EFFP.getByline = function ( author, source, attribution, formatterFunction ) {
		var formatter = this;

		formatterFunction = formatterFunction || function ( txt ) {
			return formatter.htmlUtils.htmlToTextWithLinks( txt );
		};

		if ( attribution ) {
			attribution = attribution && formatterFunction( attribution );
			return attribution;
		} else {
			author = author && formatterFunction( author );
			source = source && formatterFunction( source );

			if ( author && source ) {
				return mw.message(
					'multimediaviewer-credit',
					author,
					source
				).parse();
			} else {
				return author || source;
			}
		}
	};

	/**
	 * Generates the plain text embed code for the image credit line.
	 *
	 * @param {Object} info
	 * @param {mw.mmv.model.Image} info.imageInfo
	 * @return {string}
	 */
	EFFP.getCreditText = function ( info ) {
		var creditText, creditParams,
			formatter = this,
			shortURL = info.imageInfo.descriptionShortUrl,
			license = info.imageInfo.license,
			byline = this.getByline( info.imageInfo.author, info.imageInfo.source, info.imageInfo.attribution, function ( txt ) {
				return formatter.htmlUtils.htmlToText( txt );
			} );

		// If both the byline and licence are missing, the credit text is simply the URL
		if ( !byline && !license ) {
			return shortURL;
		}

		creditParams = [
			'multimediaviewer-text-embed-credit-text-'
		];

		if ( byline ) {
			creditParams[ 0 ] += 'b';
			creditParams.push( byline );
		}

		if ( license ) {
			creditParams[ 0 ] += 'l';
			creditParams.push( this.htmlUtils.htmlToText( license.getShortName() ) );
		}

		creditParams.push( shortURL );
		creditText = mw.message.apply( mw, creditParams ).plain();

		return creditText;
	};

	/**
	 * Generates the HTML embed code for the image credit line.
	 *
	 * @param {Object} info
	 * @param {mw.mmv.model.Image} info.imageInfo
	 * @return {string}
	 */
	EFFP.getCreditHtml = function ( info ) {
		var creditText, creditParams,
			shortURL = info.imageInfo.descriptionShortUrl,
			shortLink = this.htmlUtils.makeLinkText( mw.message( 'multimediaviewer-html-embed-credit-link-text' ), { href: shortURL } ),
			license = info.imageInfo.license,
			byline = this.getByline( info.imageInfo.author, info.imageInfo.source, info.imageInfo.attribution );

		if ( !byline && !license ) {
			return shortLink;
		}

		creditParams = [
			'multimediaviewer-html-embed-credit-text-'
		];

		if ( byline ) {
			creditParams[ 0 ] += 'b';
			creditParams.push( byline );
		}
		if ( license ) {
			creditParams[ 0 ] += 'l';
			creditParams.push( license.getShortLink() );
		}

		creditParams.push( shortLink );
		creditText = mw.message.apply( mw, creditParams ).plain();

		return creditText;
	};

	/**
	 * Returns HTML code for a link to the site of the image.
	 *
	 * @param {Object} info
	 * @param {mw.mmv.model.Image} info.imageInfo
	 * @return {string}
	 */
	EFFP.getSiteLink = function ( info ) {
		var siteName = info.repoInfo.displayName,
			siteUrl = info.repoInfo.getSiteLink();

		if ( siteUrl ) {
			return this.htmlUtils.jqueryToHtml(
				$( '<a>' ).prop( 'href', siteUrl ).text( siteName )
			);
		} else {
			return siteName;
		}
	};

	/**
	 * Generates the HTML embed code for the image.
	 *
	 * @param {Object} info
	 * @param {mw.mmv.model.Image} info.imageInfo
	 * @param {string} imgUrl URL to the file itself.
	 * @param {number} [width] Width to put into the image element.
	 * @param {number} [height] Height to put into the image element.
	 * @return {string} Embed code.
	 */
	EFFP.getThumbnailHtml = function ( info, imgUrl, width, height ) {
		return this.htmlUtils.jqueryToHtml(
			$( '<p>' ).append(
				$( '<a>' )
					.attr( 'href', this.getLinkUrl( info ) )
					.append(
						$( '<img>' )
							.attr( 'src', imgUrl )
							.attr( 'alt', info.alt || info.imageInfo.title.getMainText() )
							.attr( 'height', height )
							.attr( 'width', width )
					),
				$( '<br>' ),
				this.getCreditHtml( info )
			)
		);
	};

	/**
	 * Generate a link which we will be using for sharing stuff.
	 *
	 * @param {Object} info
	 * @param {mw.mmv.model.Image} info.imageInfo
	 * @return {string} URL
	 */
	EFFP.getLinkUrl = function ( info ) {
		return info.imageInfo.descriptionUrl + mw.mmv.getMediaHash( info.imageInfo.title );
	};

	mw.mmv.EmbedFileFormatter = EmbedFileFormatter;
}() );
