/*!
 * VisualEditor initialization support checker.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

( function () {
	/**
	 * Check whether the environment has the needed features to load VisualEditor.
	 * This considers every ES5 feature, support for contentEditable itself, those
	 * specific DOM features we use, and SVG support for the user interface. As we
	 * use this to check for feature compatibility this file must be ES3-parsable.
	 *
	 * @method VisualEditorSupportCheck
	 * @member global
	 * @return {boolean} True if the environment should support VisualEditor.
	 */
	window.VisualEditorSupportCheck = function () {
		return (
			/* ES5 */
			( function () {
				'use strict';
				return !this && !!Function.prototype.bind && !!window.JSON;
			}() ) &&

			/* contentEditable */
			!!( 'contentEditable' in document.createElement( 'div' ) ) &&

			/* createElementNS */
			!!document.createElementNS &&

			/* classList */
			!!(
				( 'classList' in document.createElement( '_' ) ) ||
				( 'classList' in document.createElementNS( 'http://www.w3.org/2000/svg ', 'g' ) )
			) &&

			/* SVG */
			!!( 'createSVGRect' in document.createElementNS( 'http://www.w3.org/2000/svg', 'svg' ) )
		);
	};
}() );
