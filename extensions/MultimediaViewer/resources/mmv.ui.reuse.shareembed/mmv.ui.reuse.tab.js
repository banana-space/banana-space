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
	var TP;

	/**
	 * A tab in a mw.mmv.ui.Pane component
	 *
	 * @class mw.mmv.ui.reuse.Tab
	 * @extends mw.mmv.ui.Element
	 * @param {jQuery} $container
	 * @constructor
	 */
	function Tab( $container ) {
		Tab.super.call( this, $container );

		/**
		 * Container for the tab.
		 *
		 * @property {jQuery}
		 */
		this.$pane = $( '<div>' ).addClass( 'mw-mmv-reuse-pane' );

	}
	OO.inheritClass( Tab, mw.mmv.ui.Element );
	TP = Tab.prototype;

	/**
	 * Shows the pane.
	 */
	TP.show = function () {
		this.$pane.addClass( 'active' );
	};

	/**
	 * Hides the pane.
	 */
	TP.hide = function () {
		this.$pane.removeClass( 'active' );
	};

	mw.mmv.ui.reuse.Tab = Tab;
}() );
