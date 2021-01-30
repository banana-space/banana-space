( function () {
	/**
	 * Flow Content class
	 *
	 * @class
	 *
	 * @constructor
	 * @param {Object} [representations]
	 *  {
	 *    content: "content in the default format",
	 *    format: "name of the default format",
	 *    "(other format name 1)": "content in the specified format"
	 *    "(other format name n)": "content in the specified format"
	 *  }
	 */
	mw.flow.dm.Content = function mwFlowContent( representations ) {
		// Mixin constructor
		OO.EventEmitter.call( this );

		// Initialize properties
		this.set( representations );
	};

	/* Inheritance */

	OO.mixinClass( mw.flow.dm.Content, OO.EventEmitter );

	/* Events */

	/**
	 * Change of content
	 *
	 * @event contentChange
	 */

	/* Methods */

	/**
	 * Get content representation for the specified format or the default format if none is specified.
	 *
	 * @param {string} [format=this.defaultFormat] Can be wikitext, html, fixed-html, topic-title-wikitext, topic-title-html, plaintext
	 * @return {string|null} Content
	 */
	mw.flow.dm.Content.prototype.get = function ( format ) {
		if ( !this.contentRepresentations ) {
			return null;
		}

		format = format || this.defaultFormat;

		if ( Object.prototype.hasOwnProperty.call( this.contentRepresentations, format ) ) {
			return this.contentRepresentations[ format ];
		}
		return null;
	};

	/**
	 * Set content representations
	 *
	 * @param {Object} [representations]
	 * @fires contentChange
	 */
	mw.flow.dm.Content.prototype.set = function ( representations ) {
		var format;
		this.defaultFormat = null;
		this.contentRepresentations = {};

		if ( representations ) {
			this.defaultFormat = representations.format;
			this.contentRepresentations[ this.defaultFormat ] = representations.content;

			for ( format in representations ) {
				if ( Object.prototype.hasOwnProperty.call( representations, format ) ) {
					this.contentRepresentations[ format ] = representations[ format ];
				}
			}
		}

		this.emit( 'contentChange' );
	};
}() );
