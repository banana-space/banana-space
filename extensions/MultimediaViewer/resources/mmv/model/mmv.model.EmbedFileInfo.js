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

( function ( mw ) {
	/**
	 * Contains information needed to embed and share files.
	 *
	 * @class mw.mmv.model.EmbedFileInfo
	 * @constructor
	 * @param {mw.mmv.model.Image} imageInfo
	 * @param {mw.mmv.model.Repo} repoInfo
	 * @param {string} [caption]
	 * @param {string} [alt]
	 */
	function EmbedFileInfo(
		imageInfo,
		repoInfo,
		caption,
		alt
	) {
		if ( !imageInfo || !repoInfo ) {
			throw new Error( 'imageInfo and repoInfo are required and must have a value' );
		}

		/** @property {mw.mmv.model.Image} imageInfo The title of the file */
		this.imageInfo = imageInfo;

		/** @property {mw.mmv.model.Repo} repoInfo The URL to the original file */
		this.repoInfo = repoInfo;

		/** @property {Object} [caption] Image caption, if any */
		this.caption = caption;

		/** @property {string} [alt] Alt text for image */
		this.alt = alt;
	}

	mw.mmv.model.EmbedFileInfo = EmbedFileInfo;
}( mediaWiki ) );
