/*!
 * VisualEditor Cite-specific DiffElement tests.
 *
 * @copyright 2011-2018 VisualEditor Team and others; see http://ve.mit-license.org
 */

QUnit.module( 've.ui.DiffElement (Cite)' );

QUnit.test( 'Diffing', function ( assert ) {
	var i, len,
		// spacer = '<div class="ve-ui-diffElement-spacer">⋮</div>',
		ref = function ( text, num, name ) {
			var dataMw = {
				name: 'ref',
				body: { html: text }
			};
			if ( name ) {
				dataMw.attrs = { name: name };
			}
			return '<sup typeof="mw:Extension/ref" data-mw="' + JSON.stringify( dataMw ).replace( /"/g, '&quot;' ) + '" class="mw-ref">' +
						'<a style="counter-reset: mw-Ref ' + num + ';"><span class="mw-reflink-text">[' + num + ']</span></a>' +
					'</sup>';
		},
		cases = [
			{
				msg: 'Simple ref change',
				oldDoc:
					'<p>' + ref( 'Foo' ) + ref( 'Bar' ) + ref( 'Baz' ) + '</p>' +
					'<h2>Notes</h2>' +
					'<div typeof="mw:Extension/references" data-mw="{&quot;name&quot;:&quot;references&quot;}"></div>',
				newDoc:
					'<p>' + ref( 'Foo' ) + ref( 'Bar ish' ) + ref( 'Baz' ) + '</p>' +
					'<h2>Notes</h2>' +
					'<div typeof="mw:Extension/references" data-mw="{&quot;name&quot;:&quot;references&quot;}"></div>',
				expected:
					'<div class="ve-ui-diffElement-doc-child-change">' +
						'<p>' +
							ref( 'Foo', '1' ) +
							'<span data-diff-action="change-remove">' +
								ref( 'Bar', '2', ':0' ) +
							'</span>' +
							'<span data-diff-action="change-insert">' +
								ref( 'Bar ish', '2', ':0' ) +
							'</span>' +
							ref( 'Baz', '3' ) +
						'</p>' +
					'</div>' +
					'<h2 data-diff-action="none">Notes</h2>' +
					'<div class="ve-ui-diffElement-doc-child-change" data-diff-move="undefined">' +
						'<ol start="1">' +
							'<li><p data-diff-action="none">Foo</p></li>' +
						'</ol>' +
						'<ol start="2">' +
							'<li><div class="ve-ui-diffElement-doc-child-change">Bar<ins data-diff-action="insert"> ish</ins></div></li>' +
						'</ol>' +
						'<ol start="3">' +
							'<li><p data-diff-action="none">Baz</p></li>' +
						'</ol>' +
					'</div>'
			}
		];

	for ( i = 0, len = cases.length; i < len; i++ ) {
		ve.test.utils.runDiffElementTest( assert, cases[ i ] );
	}

} );
