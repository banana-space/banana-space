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
	var SBP;

	/**
	 * Class for buttons which are placed on the metadata stripe (the always visible part of the
	 * metadata panel).
	 *
	 * @class mw.mmv.ui.StripeButtons
	 * @extends mw.mmv.ui.Element
	 * @constructor
	 * @param {jQuery} $container the title block (.mw-mmv-title-contain) which wraps the buttons and all
	 *  other title elements
	 */
	function StripeButtons( $container ) {
		mw.mmv.ui.Element.call( this, $container );

		this.$buttonContainer = $( '<div>' )
			.addClass( 'mw-mmv-stripe-button-container' )
			.appendTo( $container );

		/**
		 * This holds the actual buttons.
		 *
		 * @property {Object.<string, jQuery>}
		 */
		this.buttons = {};

		this.initDescriptionPageButton();
	}
	OO.inheritClass( StripeButtons, mw.mmv.ui.Element );
	SBP = StripeButtons.prototype;

	/**
	 * Creates a new button on the metadata stripe.
	 *
	 * @protected
	 * @param {string} cssClass CSS class name for the button
	 * @return {jQuery} Button
	 */
	SBP.createButton = function ( cssClass ) {
		var $button;

		// eslint-disable-next-line mediawiki/class-doc
		$button = $( '<a>' )
			.addClass( 'mw-mmv-stripe-button empty ' + cssClass )
			// elements are right-floated so we use prepend instead of append to keep the order
			.prependTo( this.$buttonContainer )
			.attr( 'tabindex', '0' );

		return $button;
	};

	/**
	 * Creates a button linking to the file description page.
	 *
	 * @protected
	 */
	SBP.initDescriptionPageButton = function () {
		this.buttons.$descriptionPage = this.createButton(
			'empty mw-mmv-description-page-button mw-ui-big mw-ui-button mw-ui-progressive'
		).on( 'click', function () {
			mw.mmv.actionLogger.log( 'file-description-page-abovefold' );
		} );
	};

	/**
	 * Runs code for each button, similarly to $.each.
	 *
	 * @protected
	 * @param {function(jQuery, string)} callback a function that will be called with each button
	 */
	SBP.eachButton = function ( callback ) {
		var buttonName;
		for ( buttonName in this.buttons ) {
			callback( this.buttons[ buttonName ], buttonName );
		}
	};

	/**
	 * @inheritdoc
	 * @param {mw.mmv.model.Image} imageInfo
	 * @param {mw.mmv.model.Repo} repoInfo
	 */
	SBP.set = function ( imageInfo, repoInfo ) {
		this.eachButton( function ( $button ) {
			$button.removeClass( 'empty' );
		} );

		this.setDescriptionPageButton( imageInfo, repoInfo );
	};

	/**
	 * Updates the button linking to the file page.
	 *
	 * @protected
	 * @param {mw.mmv.model.Image} imageInfo
	 * @param {mw.mmv.model.Repo} repoInfo
	 */
	SBP.setDescriptionPageButton = function ( imageInfo, repoInfo ) {
		var $button = this.buttons.$descriptionPage;

		$button.text( mw.message( 'multimediaviewer-repository-local' ).text() )
			.attr( 'href', imageInfo.descriptionUrl );

		$button.toggleClass( 'mw-mmv-repo-button-commons', repoInfo.isCommons() );
	};

	/**
	 * @inheritdoc
	 */
	SBP.empty = function () {
		this.eachButton( function ( $button ) {
			$button.addClass( 'empty' );
		} );

		this.buttons.$descriptionPage.attr( { href: null, title: null, 'original-title': null } )
			.removeClass( 'mw-mmv-repo-button-commons' );
	};

	mw.mmv.ui.StripeButtons = StripeButtons;
}() );
