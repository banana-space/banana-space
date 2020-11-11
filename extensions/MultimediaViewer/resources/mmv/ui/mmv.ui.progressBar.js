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

( function ( mw, $, oo ) {
	var PBP;

	/**
	 * A progress bar for the loading of the image.
	 *
	 * @class mw.mmv.ui.ProgressBar
	 * @extends mw.mmv.ui.Element
	 * @constructor
	 * @param {jQuery} $container
	 */
	function ProgressBar( $container ) {
		mw.mmv.ui.Element.call( this, $container );
		this.init();
	}
	oo.inheritClass( ProgressBar, mw.mmv.ui.Element );
	PBP = ProgressBar.prototype;

	/**
	 * Initializes the progress display at the top of the panel.
	 */
	PBP.init = function () {
		this.$progress = $( '<div>' )
			.addClass( 'mw-mmv-progress empty' )
			.appendTo( this.$container );
		this.$percent = $( '<div>' )
			.addClass( 'mw-mmv-progress-percent' )
			.appendTo( this.$progress );
	};

	PBP.empty = function () {
		this.hide();
	};

	/**
	 * Hides the bar, resets it to 0 and stops any animation in progress.
	 */
	PBP.hide = function () {
		this.$progress.addClass( 'empty' );
		this.$percent.stop().css( { width: 0 } );
	};

	/**
	 * Handles the progress display when a percentage of progress is received
	 *
	 * @param {number} percent a number between 0 and 100
	 */
	PBP.animateTo = function ( percent ) {
		var panel = this;

		this.$progress.removeClass( 'empty' );
		this.$percent.stop();

		if ( percent === 100 ) {
			// When a 100% update comes in, we make sure that the bar is visible, we animate
			// fast to 100 and we hide the bar when the animation is done
			this.$percent.animate( { width: percent + '%' }, 50, 'swing', $.proxy( panel.hide, panel ) );
		} else {
			// When any other % update comes in, we make sure the bar is visible
			// and we animate to the right position
			this.$percent.animate( { width: percent + '%' } );
		}
	};

	/**
	 * Goes to the given percent without animation
	 *
	 * @param {number} percent a number between 0 and 100
	 */
	PBP.jumpTo = function ( percent ) {
		this.$progress.removeClass( 'empty' );
		this.$percent.stop().css( { width: percent + '%' } );
	};

	mw.mmv.ui.ProgressBar = ProgressBar;
}( mediaWiki, jQuery, OO ) );
