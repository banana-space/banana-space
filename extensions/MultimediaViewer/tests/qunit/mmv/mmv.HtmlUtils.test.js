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

( function ( mw, $ ) {
	QUnit.module( 'mmv.HtmlUtils', QUnit.newMwEnvironment() );

	QUnit.test( 'wrapAndJquerify() for single node', function ( assert ) {
		var utils = new mw.mmv.HtmlUtils(),
			$el = $( '<span>' ),
			el = $( '<span>' ).get( 0 ),
			html = '<span></span>',
			invalid = {};

		assert.strictEqual( utils.wrapAndJquerify( $el ).html(), '<span></span>', 'jQuery' );
		assert.strictEqual( utils.wrapAndJquerify( el ).html(), '<span></span>', 'HTMLElement' );
		assert.strictEqual( utils.wrapAndJquerify( html ).html(), '<span></span>', 'HTML string' );

		try {
			utils.wrapAndJquerify( invalid );
		} catch ( e ) {
			assert.ok( e, 'throws exception for invalid type' );
		}
	} );

	QUnit.test( 'wrapAndJquerify() for multiple nodes', function ( assert ) {
		var utils = new mw.mmv.HtmlUtils(),
			$el = $( '<span></span><span></span>' ),
			html = '<span></span><span></span>';

		assert.strictEqual( utils.wrapAndJquerify( $el ).html(), '<span></span><span></span>', 'jQuery' );
		assert.strictEqual( utils.wrapAndJquerify( html ).html(), '<span></span><span></span>', 'HTML string' );
	} );

	QUnit.test( 'wrapAndJquerify() for text', function ( assert ) {
		var utils = new mw.mmv.HtmlUtils(),
			$el = $( document.createTextNode( 'foo' ) ),
			html = 'foo';

		assert.strictEqual( utils.wrapAndJquerify( $el ).html(), 'foo', 'jQuery' );
		assert.strictEqual( utils.wrapAndJquerify( html ).html(), 'foo', 'HTML string' );
	} );

	QUnit.test( 'wrapAndJquerify() does not change original', function ( assert ) {
		var utils = new mw.mmv.HtmlUtils(),
			$el = $( '<span>' ),
			el = $( '<span>' ).get( 0 );

		utils.wrapAndJquerify( $el ).find( 'span' ).prop( 'data-x', 1 );
		utils.wrapAndJquerify( el ).find( 'span' ).prop( 'data-x', 1 );
		assert.strictEqual( $el.prop( 'data-x' ), undefined, 'wrapped jQuery element is not the same as original' );
		assert.strictEqual( $( el ).prop( 'data-x' ), undefined, 'wrapped HTMLElement is not the same as original' );
	} );

	QUnit.test( 'filterInvisible()', function ( assert ) {
		var utils = new mw.mmv.HtmlUtils(),
			$visibleChild = $( '<div><span></span></div>' ),
			$invisibleChild = $( '<div><span style="display: none"></span></div>' ),
			$invisibleChildInVisibleChild = $( '<div><span><abbr style="display: none"></abbr></span></div>' ),
			$visibleChildInInvisibleChild = $( '<div><span style="display: none"><abbr></abbr></span></div>' ),
			$invisibleChildWithVisibleSiblings = $( '<div><span></span><abbr style="display: none"></abbr><b></b></div>' );

		utils.filterInvisible( $visibleChild );
		utils.filterInvisible( $invisibleChild );
		utils.filterInvisible( $invisibleChildInVisibleChild );
		utils.filterInvisible( $visibleChildInInvisibleChild );
		utils.filterInvisible( $invisibleChildWithVisibleSiblings );

		assert.ok( $visibleChild.has( 'span' ).length, 'visible child is not filtered' );
		assert.ok( !$invisibleChild.has( 'span' ).length, 'invisible child is filtered' );
		assert.ok( $invisibleChildInVisibleChild.has( 'span' ).length, 'visible child is not filtered...' );
		assert.ok( !$invisibleChildInVisibleChild.has( 'abbr' ).length, '... but its invisible child is' );
		assert.ok( !$visibleChildInInvisibleChild.has( 'span' ).length, 'invisible child is filtered...' );
		assert.ok( !$visibleChildInInvisibleChild.has( 'abbr' ).length, '...and its children too' );
		assert.ok( $visibleChild.has( 'span' ).length, 'visible child is not filtered' );
		assert.ok( !$invisibleChildWithVisibleSiblings.has( 'abbr' ).length, 'invisible sibling is filtered...' );
		assert.ok( $invisibleChildWithVisibleSiblings.has( 'span' ).length, '...but its visible siblings are not' );
		assert.ok( $invisibleChildWithVisibleSiblings.has( 'b' ).length, '...but its visible siblings are not' );
	} );

	QUnit.test( 'whitelistHtml()', function ( assert ) {
		var utils = new mw.mmv.HtmlUtils(),
			$whitelisted = $( '<div>abc<a>def</a>ghi</div>' ),
			$nonWhitelisted = $( '<div>abc<span>def</span>ghi</div>' ),
			$nonWhitelistedInWhitelisted = $( '<div>abc<a>d<span>e</span>f</a>ghi</div>' ),
			$whitelistedInNonWhitelisted = $( '<div>abc<span>d<a>e</a>f</span>ghi</div>' ),
			$siblings = $( '<div>ab<span>c</span>d<a>e</a>f<span>g</span>hi</div>' );

		utils.whitelistHtml( $whitelisted, 'a' );
		utils.whitelistHtml( $nonWhitelisted, 'a' );
		utils.whitelistHtml( $nonWhitelistedInWhitelisted, 'a' );
		utils.whitelistHtml( $whitelistedInNonWhitelisted, 'a' );
		utils.whitelistHtml( $siblings, 'a' );

		assert.ok( $whitelisted.has( 'a' ).length, 'Whitelisted elements are kept.' );
		assert.ok( !$nonWhitelisted.has( 'span' ).length, 'Non-whitelisted elements are removed.' );
		assert.ok( $nonWhitelistedInWhitelisted.has( 'a' ).length, 'Whitelisted parents are kept.' );
		assert.ok( !$nonWhitelistedInWhitelisted.has( 'span' ).length, 'Non-whitelisted children are removed.' );
		assert.ok( !$whitelistedInNonWhitelisted.has( 'span' ).length, 'Non-whitelisted parents are removed.' );
		assert.ok( $whitelistedInNonWhitelisted.has( 'a' ).length, 'Whitelisted children are kept.' );
		assert.ok( !$siblings.has( 'span' ).length, 'Non-whitelisted siblings are removed.' );
		assert.ok( $siblings.has( 'a' ).length, 'Whitelisted siblings are kept.' );
	} );

	QUnit.test( 'appendWhitespaceToBlockElements()', function ( assert ) {
		var utils = new mw.mmv.HtmlUtils(),
			$noBlockElement = $( '<div>abc<i>def</i>ghi</div>' ),
			$blockElement = $( '<div>abc<p>def</p>ghi</div>' ),
			$linebreak = $( '<div>abc<br>def</div>' );

		utils.appendWhitespaceToBlockElements( $noBlockElement );
		utils.appendWhitespaceToBlockElements( $blockElement );
		utils.appendWhitespaceToBlockElements( $linebreak );

		assert.ok( $noBlockElement.text().match( /abcdefghi/ ), 'Non-block elemens are not whitespaced.' );
		assert.ok( $blockElement.text().match( /abc\s+def\s+ghi/ ), 'Block elemens are whitespaced.' );
		assert.ok( $linebreak.text().match( /abc\s+def/ ), 'Linebreaks are whitespaced.' );
	} );

	QUnit.test( 'jqueryToHtml()', function ( assert ) {
		var utils = new mw.mmv.HtmlUtils();

		assert.strictEqual( utils.jqueryToHtml( $( '<a>' ) ), '<a></a>',
			'works for single element' );
		assert.strictEqual( utils.jqueryToHtml( $( '<b><a>foo</a></b>' ) ), '<b><a>foo</a></b>',
			'works for complex element' );
		assert.strictEqual( utils.jqueryToHtml( $( '<a>foo</a>' ).contents() ), 'foo',
			'works for text nodes' );
	} );

	QUnit.test( 'mergeWhitespace()', function ( assert ) {
		var utils = new mw.mmv.HtmlUtils();

		assert.strictEqual( utils.mergeWhitespace( ' x \n' ), 'x',
			'leading/trainling whitespace is trimmed' );
		assert.strictEqual( utils.mergeWhitespace( 'x \n\n \n y' ), 'x\ny',
			'whitespace containing a newline is collapsed into a single newline' );
		assert.strictEqual( utils.mergeWhitespace( 'x   y' ), 'x y',
			'multiple spaces are collapsed into a single one' );
	} );

	QUnit.test( 'htmlToText()', function ( assert ) {
		var utils = new mw.mmv.HtmlUtils(),
			html = '<table><tr><td>Foo</td><td><a>bar</a></td><td style="display: none">baz</td></tr></table>';

		assert.strictEqual( utils.htmlToText( html ), 'Foo bar', 'works' );
	} );

	QUnit.test( 'htmlToTextWithLinks()', function ( assert ) {
		var utils = new mw.mmv.HtmlUtils(),
			html = '<table><tr><td><b>F</b>o<i>o</i></td><td><a>bar</a></td><td style="display: none">baz</td></tr></table>';

		assert.strictEqual( utils.htmlToTextWithLinks( html ), 'Foo <a>bar</a>', 'works' );
	} );

	QUnit.test( 'htmlToTextWithTags()', function ( assert ) {
		var utils = new mw.mmv.HtmlUtils(),
			html = '<table><tr><td><b>F</b>o<i>o</i><sub>o</sub><sup>o</sup></td><td><a>bar</a></td><td style="display: none">baz</td></tr></table>';

		assert.strictEqual( utils.htmlToTextWithTags( html ), '<b>F</b>o<i>o</i><sub>o</sub><sup>o</sup> <a>bar</a>', 'works' );
	} );

	QUnit.test( 'isJQueryOrHTMLElement()', function ( assert ) {
		var utils = new mw.mmv.HtmlUtils();

		assert.ok( utils.isJQueryOrHTMLElement( $( '<span>' ) ), 'Recognizes jQuery objects correctly' );
		assert.ok( utils.isJQueryOrHTMLElement( $( '<span>' ).get( 0 ) ), 'Recognizes HTMLElements correctly' );
		assert.ok( !utils.isJQueryOrHTMLElement( '<span></span>' ), 'Recognizes jQuery objects correctly' );
	} );

	QUnit.test( 'makeLinkText()', function ( assert ) {
		var utils = new mw.mmv.HtmlUtils();

		assert.strictEqual( utils.makeLinkText( 'foo', {
			href: 'http://example.com',
			title: 'h<b>t</b><i>m</i>l'
		} ), '<a href="http://example.com" title="html">foo</a>', 'works' );
	} );
}( mediaWiki, jQuery ) );
