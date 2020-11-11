/*!
 * VisualEditor DataModel Cite-specific example data sets.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

ve.dm.citeExample = {};

ve.dm.citeExample.createExampleDocument = function ( name, store ) {
	return ve.dm.example.createExampleDocumentFromObject( name, store, ve.dm.citeExample );
};

ve.dm.citeExample.domToDataCases = {
	'mw:Reference': {
		// Wikitext:
		// Foo<ref name="bar" /> Baz<ref group="g1" name=":0">Quux</ref> Whee<ref name="bar">[[Bar]]</ref> Yay<ref group="g1">No name</ref> Quux<ref name="bar">Different content</ref> Foo<ref group="g1" name="foo" />
		// <references group="g1"><ref group="g1" name="foo">Ref in refs</ref></references>
		body:
			'<p>Foo' +
				'<sup about="#mwt1" class="reference" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;bar&quot;}}" id="cite_ref-bar-1-0" rel="dc:references" typeof="mw:Extension/ref">' +
					'<a href="#cite_note-bar-1">[1]</a>' +
				'</sup>' +
				' Baz' +
				'<sup about="#mwt2" class="reference" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:{&quot;html&quot;:&quot;Quux&quot;},&quot;attrs&quot;:{&quot;group&quot;:&quot;g1&quot;,&quot;name&quot;:&quot;:0&quot;}}" id="cite_ref-quux-2-0" rel="dc:references" typeof="mw:Extension/ref">' +
					'<a href="#cite_note-.3A0-2">[g1 1]</a>' +
				'</sup>' +
				' Whee' +
				'<sup about="#mwt3" class="reference" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:{&quot;html&quot;:&quot;' +
				'<a rel=\\&quot;mw:WikiLink\\&quot; href=\\&quot;./Bar\\&quot;>Bar' +
				'</a>&quot;},&quot;attrs&quot;:{&quot;name&quot;:&quot;bar&quot;}}" id="cite_ref-bar-1-1" rel="dc:references" typeof="mw:Extension/ref">' +
					'<a href="#cite_note-bar-1">[1]</a>' +
				'</sup>' +
				' Yay' +
				// This reference has .body.id instead of .body.html
				'<sup about="#mwt4" class="reference" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:{&quot;id&quot;:&quot;mw-cite-3&quot;},&quot;attrs&quot;:{&quot;group&quot;:&quot;g1&quot;}}" id="cite_ref-1-0" rel="dc:references" typeof="mw:Extension/ref">' +
					'<a href="#cite_note-3">[g1 2]</a>' +
				'</sup>' +
				' Quux' +
				'<sup about="#mwt5" class="reference" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:{&quot;html&quot;:&quot;Different content&quot;},&quot;attrs&quot;:{&quot;name&quot;:&quot;bar&quot;}}" id="cite_ref-bar-1-2" rel="dc:references" typeof="mw:Extension/ref">' +
					'<a href="#cite_note-bar-1">[1]</a>' +
				'</sup>' +
				' Foo' +
				'<sup about="#mwt6" class="reference" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;group&quot;:&quot;g1&quot;,&quot;name&quot;:&quot;foo&quot;}}" ' +
					'id="cite_ref-foo-4" rel="dc:references" typeof="mw:Extension/ref">' +
					'<a href="#cite_ref-foo-4">[g1 3]</a>' +
				'</sup>' +
			'</p>' +
			// The HTML below is enriched to wrap reference contents in <span id="mw-cite-[...]">
			// which Parsoid doesn't do yet, but T88290 asks for
			'<ol class="references" typeof="mw:Extension/references" about="#mwt7"' +
				'data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;body&quot;:{' +
				'&quot;html&quot;:&quot;<sup about=\\&quot;#mwt8\\&quot; class=\\&quot;reference\\&quot; ' +
				'data-mw=\\&quot;{&amp;quot;name&amp;quot;:&amp;quot;ref&amp;quot;,&amp;quot;body&amp;quot;:{&amp;quot;html&amp;quot;:&amp;quot;Ref in refs&amp;quot;},' +
				'&amp;quot;attrs&amp;quot;:{&amp;quot;group&amp;quot;:&amp;quot;g1&amp;quot;,&amp;quot;name&amp;quot;:&amp;quot;foo&amp;quot;}}\\&quot; ' +
				'rel=\\&quot;dc:references\\&quot; typeof=\\&quot;mw:Extension/ref\\&quot;>' +
				'<a href=\\&quot;#cite_note-foo-3\\&quot;>[3]</a></sup>&quot;},&quot;attrs&quot;:{&quot;group&quot;:&quot;g1&quot;}}">' +
				'<li about="#cite_note-.3A0-2" id="cite_note-.3A0-2"><span rel="mw:referencedBy"><a href="#cite_ref-.3A0_2-0">↑</a></span> <span id="mw-cite-:0">Quux</span></li>' +
				'<li about="#cite_note-3" id="cite_note-3"><span rel="mw:referencedBy"><a href="#cite_ref-3">↑</a></span> <span id="mw-cite-3">No name</span></li>' +
				'<li about="#cite_note-foo-4" id="cite_note-foo-4"><span rel="mw:referencedBy"><a href="#cite_ref-foo_4-0">↑</a></span> <span id="mw-cite-foo">Ref in refs</span></li>' +
			'</ol>',
		fromDataBody:
			'<p>Foo' +
				'<sup data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;bar&quot;}}" typeof="mw:Extension/ref">' +
				'</sup>' +
				' Baz' +
				'<sup data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:{&quot;html&quot;:&quot;Quux&quot;},&quot;attrs&quot;:{&quot;group&quot;:&quot;g1&quot;,&quot;name&quot;:&quot;:0&quot;}}" typeof="mw:Extension/ref">' +
				'</sup>' +
				' Whee' +
				'<sup data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:{&quot;html&quot;:&quot;' +
				'<a rel=\\&quot;mw:WikiLink\\&quot; href=\\&quot;./Bar\\&quot;>Bar' +
				'</a>&quot;},&quot;attrs&quot;:{&quot;name&quot;:&quot;bar&quot;}}" typeof="mw:Extension/ref">' +
				'</sup>' +
				' Yay' +
				'<sup data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:{&quot;id&quot;:&quot;mw-cite-3&quot;},&quot;attrs&quot;:{&quot;group&quot;:&quot;g1&quot;}}" typeof="mw:Extension/ref">' +
				'</sup>' +
				' Quux' +
				'<sup data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:{&quot;html&quot;:&quot;Different content&quot;},&quot;attrs&quot;:{&quot;name&quot;:&quot;bar&quot;}}" typeof="mw:Extension/ref">' +
				'</sup>' +
				' Foo' +
				'<sup data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;group&quot;:&quot;g1&quot;,&quot;name&quot;:&quot;foo&quot;}}" ' +
					'typeof="mw:Extension/ref">' +
				'</sup>' +
			'</p>' +
			'<div typeof="mw:Extension/references" ' +
				'data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{&quot;group&quot;:&quot;g1&quot;},&quot;body&quot;:{' +
				'&quot;html&quot;:&quot;<sup typeof=\\&quot;mw:Extension/ref\\&quot; ' +
				'data-mw=\\&quot;{&amp;quot;name&amp;quot;:&amp;quot;ref&amp;quot;,&amp;quot;body&amp;quot;:{&amp;quot;html&amp;quot;:&amp;quot;Ref in refs&amp;quot;},' +
				'&amp;quot;attrs&amp;quot;:{&amp;quot;group&amp;quot;:&amp;quot;g1&amp;quot;,&amp;quot;name&amp;quot;:&amp;quot;foo&amp;quot;}}\\&quot;>' +
				'</sup>&quot;}}">' +
			'</div>',
		clipboardBody:
			'<p>Foo' +
				'<sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;bar&quot;}}" class="mw-ref">' +
					'<a style="counter-reset: mw-Ref 1;"><span class="mw-reflink-text">[1]</span></a>' +
				'</sup>' +
				' Baz' +
				'<sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:{&quot;html&quot;:&quot;Quux&quot;},&quot;attrs&quot;:{&quot;group&quot;:&quot;g1&quot;,&quot;name&quot;:&quot;:0&quot;}}" class="mw-ref">' +
					'<a data-mw-group="g1" style="counter-reset: mw-Ref 1;"><span class="mw-reflink-text">[g1 1]</span></a>' +
				'</sup>' +
				' Whee' +
				'<sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:{&quot;html&quot;:&quot;' +
				'<a href=\\&quot;./Bar\\&quot; rel=\\&quot;mw:WikiLink\\&quot;>Bar' +
				'</a>&quot;},&quot;attrs&quot;:{&quot;name&quot;:&quot;bar&quot;}}" class="mw-ref">' +
					'<a style="counter-reset: mw-Ref 1;"><span class="mw-reflink-text">[1]</span></a>' +
				'</sup>' +
				' Yay' +
				// This reference has .body.id instead of .body.html
				'<sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:{&quot;id&quot;:&quot;mw-cite-3&quot;,&quot;html&quot;:&quot;No name&quot;},&quot;attrs&quot;:{&quot;group&quot;:&quot;g1&quot;}}" class="mw-ref">' +
					'<a data-mw-group="g1" style="counter-reset: mw-Ref 2;"><span class="mw-reflink-text">[g1 2]</span></a>' +
				'</sup>' +
				' Quux' +
				'<sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:{&quot;html&quot;:&quot;Different content&quot;},&quot;attrs&quot;:{&quot;name&quot;:&quot;bar&quot;}}" class="mw-ref">' +
					'<a style="counter-reset: mw-Ref 1;"><span class="mw-reflink-text">[1]</span></a>' +
				'</sup>' +
				' Foo' +
				'<sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;group&quot;:&quot;g1&quot;,&quot;name&quot;:&quot;foo&quot;}}" class="mw-ref">' +
					'<a data-mw-group="g1" style="counter-reset: mw-Ref 3;"><span class="mw-reflink-text">[g1 3]</span></a>' +
				'</sup>' +
			'</p>' +
			// The HTML below is enriched to wrap reference contents in <span id="mw-cite-[...]">
			// which Parsoid doesn't do yet, but T88290 asks for
			'<div typeof="mw:Extension/references" ' +
				'data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{&quot;group&quot;:&quot;g1&quot;},&quot;body&quot;:{' +
				'&quot;html&quot;:&quot;<sup typeof=\\&quot;mw:Extension/ref\\&quot; ' +
				'data-mw=\\&quot;{&amp;quot;name&amp;quot;:&amp;quot;ref&amp;quot;,&amp;quot;attrs&amp;quot;:{&amp;quot;group&amp;quot;:&amp;quot;g1&amp;quot;,&amp;quot;name&amp;quot;:&amp;quot;foo&amp;quot;},&amp;quot;body&amp;quot;:{&amp;quot;html&amp;quot;:&amp;quot;Ref in refs&amp;quot;}}' +
				'\\&quot; class=\\&quot;mw-ref\\&quot;><a data-mw-group=\\&quot;g1\\&quot; style=\\&quot;counter-reset: mw-Ref 3;\\&quot;><span class=\\&quot;mw-reflink-text\\&quot;>[g1 3]</span></a></sup>&quot;}}">' +
					'<ol class="mw-references references" data-mw-group="g1">' +
						'<li>' +
							'<a rel="mw:referencedBy" data-mw-group="g1"><span class="mw-linkback-text">↑ </span></a>' +
							'<span class="reference-text"><span class="ve-ce-branchNode ve-ce-internalItemNode">Quux</span></span>' +
						'</li>' +
						'<li>' +
							'<a rel="mw:referencedBy" data-mw-group="g1"><span class="mw-linkback-text">↑ </span></a>' +
							'<span class="reference-text"><span class="ve-ce-branchNode ve-ce-internalItemNode">No name</span></span>' +
						'</li>' +
						'<li>' +
							'<a rel="mw:referencedBy" data-mw-group="g1"><span class="mw-linkback-text">↑ </span></a>' +
							'<span class="reference-text"><span class="ve-ce-branchNode ve-ce-internalItemNode">Ref in refs</span></span>' +
						'</li>' +
					'</ol>' +
			'</div>',
		head: '<base href="http://example.com" />',
		data: [
			{ type: 'paragraph' },
			'F', 'o', 'o',
			{
				type: 'mwReference',
				attributes: {
					listIndex: 0,
					listGroup: 'mwReference/',
					listKey: 'literal/bar',
					refGroup: '',
					mw: { name: 'ref', attrs: { name: 'bar' } },
					originalMw: '{"name":"ref","attrs":{"name":"bar"}}',
					contentsUsed: false
				}
			},
			{ type: '/mwReference' },
			' ', 'B', 'a', 'z',
			{
				type: 'mwReference',
				attributes: {
					listIndex: 1,
					listGroup: 'mwReference/g1',
					listKey: 'literal/:0',
					refGroup: 'g1',
					mw: { name: 'ref', body: { html: 'Quux' }, attrs: { group: 'g1', name: ':0' } },
					originalMw: '{"name":"ref","body":{"html":"Quux"},"attrs":{"group":"g1","name":":0"}}',
					contentsUsed: true
				}
			},
			{ type: '/mwReference' },
			' ', 'W', 'h', 'e', 'e',
			{
				type: 'mwReference',
				attributes: {
					listIndex: 0,
					listGroup: 'mwReference/',
					listKey: 'literal/bar',
					refGroup: '',
					mw: { name: 'ref', body: { html: '<a rel="mw:WikiLink" href="./Bar">Bar</a>' }, attrs: { name: 'bar' } },
					originalMw: '{"name":"ref","body":{"html":"<a rel=\\"mw:WikiLink\\" href=\\"./Bar\\">Bar</a>"},"attrs":{"name":"bar"}}',
					contentsUsed: true
				}
			},
			{ type: '/mwReference' },
			' ', 'Y', 'a', 'y',
			{
				type: 'mwReference',
				attributes: {
					listIndex: 2,
					listGroup: 'mwReference/g1',
					listKey: 'auto/0',
					refGroup: 'g1',
					mw: { name: 'ref', body: { id: 'mw-cite-3' }, attrs: { group: 'g1' } },
					originalMw: '{"name":"ref","body":{"id":"mw-cite-3"},"attrs":{"group":"g1"}}',
					contentsUsed: true,
					refListItemId: 'mw-cite-3'
				}
			},
			{ type: '/mwReference' },
			' ', 'Q', 'u', 'u', 'x',
			{
				type: 'mwReference',
				attributes: {
					listIndex: 0,
					listGroup: 'mwReference/',
					listKey: 'literal/bar',
					refGroup: '',
					mw: { name: 'ref', body: { html: 'Different content' }, attrs: { name: 'bar' } },
					originalMw: '{"name":"ref","body":{"html":"Different content"},"attrs":{"name":"bar"}}',
					contentsUsed: false
				}
			},
			{ type: '/mwReference' },
			' ', 'F', 'o', 'o',
			{
				type: 'mwReference',
				attributes: {
					listGroup: 'mwReference/g1',
					listIndex: 3,
					listKey: 'literal/foo',
					refGroup: 'g1',
					mw: { name: 'ref', attrs: { group: 'g1', name: 'foo' } },
					originalMw: '{"name":"ref","attrs":{"group":"g1","name":"foo"}}',
					contentsUsed: false
				}
			},
			{ type: '/mwReference' },
			{ type: '/paragraph' },
			{
				type: 'mwReferencesList',
				attributes: {
					mw: {
						name: 'references',
						attrs: { group: 'g1' },
						body: {
							html: '<sup about="#mwt8" class="reference" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:{&quot;html&quot;:&quot;Ref in refs&quot;},&quot;attrs&quot;:{&quot;group&quot;:&quot;g1&quot;,&quot;name&quot;:&quot;foo&quot;}}" rel="dc:references" typeof="mw:Extension/ref"><a href="#cite_note-foo-3">[3]</a></sup>'
						}
					},
					originalMw: '{"name":"references","body":{"html":"<sup about=\\"#mwt8\\" class=\\"reference\\" data-mw=\\"{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:{&quot;html&quot;:&quot;Ref in refs&quot;},&quot;attrs&quot;:{&quot;group&quot;:&quot;g1&quot;,&quot;name&quot;:&quot;foo&quot;}}\\" rel=\\"dc:references\\" typeof=\\"mw:Extension/ref\\"><a href=\\"#cite_note-foo-3\\">[3]</a></sup>"},"attrs":{"group":"g1"}}',
					listGroup: 'mwReference/g1',
					refGroup: 'g1',
					isResponsive: true,
					templateGenerated: false
				}
			},
			{ type: 'paragraph', internal: { generated: 'wrapper' } },
			{
				type: 'mwReference',
				attributes: {
					contentsUsed: true,
					listGroup: 'mwReference/g1',
					listIndex: 3,
					listKey: 'literal/foo',
					mw: { name: 'ref', attrs: { group: 'g1', name: 'foo' }, body: { html: 'Ref in refs' } },
					originalMw: '{"name":"ref","body":{"html":"Ref in refs"},"attrs":{"group":"g1","name":"foo"}}',
					refGroup: 'g1'
				}
			},
			{ type: '/mwReference' },
			{ type: '/paragraph' },
			{ type: '/mwReferencesList' },
			{ type: 'internalList' },
			{ type: 'internalItem', attributes: { originalHtml: '<a rel="mw:WikiLink" href="./Bar">Bar</a>' } },
			{ type: 'paragraph', internal: { generated: 'wrapper' } },
			[
				'B',
				[ {
					type: 'link/mwInternal',
					attributes: {
						title: 'Bar',
						origTitle: 'Bar',
						normalizedTitle: 'Bar',
						lookupTitle: 'Bar',
						hrefPrefix: './'
					}
				} ]
			],
			[
				'a',
				[ {
					type: 'link/mwInternal',
					attributes: {
						title: 'Bar',
						origTitle: 'Bar',
						normalizedTitle: 'Bar',
						lookupTitle: 'Bar',
						hrefPrefix: './'
					}
				} ]
			],
			[
				'r',
				[ {
					type: 'link/mwInternal',
					attributes: {
						title: 'Bar',
						origTitle: 'Bar',
						normalizedTitle: 'Bar',
						lookupTitle: 'Bar',
						hrefPrefix: './'
					}
				} ]
			],
			{ type: '/paragraph' },
			{ type: '/internalItem' },
			{ type: 'internalItem', attributes: { originalHtml: 'Quux' } },
			{ type: 'paragraph', internal: { generated: 'wrapper' } },
			'Q', 'u', 'u', 'x',
			{ type: '/paragraph' },
			{ type: '/internalItem' },
			{ type: 'internalItem', attributes: { originalHtml: 'No name' } },
			{ type: 'paragraph', internal: { generated: 'wrapper' } },
			'N', 'o', ' ', 'n', 'a', 'm', 'e',
			{ type: '/paragraph' },
			{ type: '/internalItem' },
			{ type: 'internalItem', attributes: { originalHtml: 'Ref in refs' } },
			{ type: 'paragraph', internal: { generated: 'wrapper' } },
			'R', 'e', 'f', ' ', 'i', 'n', ' ', 'r', 'e', 'f', 's',
			{ type: '/paragraph' },
			{ type: '/internalItem' },
			{ type: '/internalList' }
		]
	},
	'mw:Reference with comment': {
		body: '<p><sup about="#mwt2" class="reference" ' +
			'data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:' +
			'{&quot;html&quot;:&quot;Foo<!-- bar -->&quot;},&quot;attrs&quot;:{}}" ' +
			'id="cite_ref-1-0" rel="dc:references" typeof="mw:Extension/ref">' +
			'<a href="#cite_note-bar-1">[1]</a></sup></p>',
		fromDataBody: '<p><sup ' +
			'data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:' +
			'{&quot;html&quot;:&quot;Foo<!-- bar -->&quot;},&quot;attrs&quot;:{}}" ' +
			'typeof="mw:Extension/ref"></sup></p>',
		clipboardBody: '<p><sup typeof="mw:Extension/ref" ' +
			'data-mw="{&quot;attrs&quot;:{},&quot;body&quot;:' +
			'{&quot;html&quot;:&quot;Foo<span rel=\\&quot;ve:Comment\\&quot; data-ve-comment=\\&quot; bar \\&quot;>&amp;nbsp;</span>&quot;},&quot;name&quot;:&quot;ref&quot;}" ' +
			' class="mw-ref">' +
			'<a style="counter-reset: mw-Ref 1;"><span class="mw-reflink-text">[1]</span></a></sup></p>',
		head: '<base href="http://example.com" />',
		data: [
			{ type: 'paragraph' },
			{
				type: 'mwReference',
				attributes: {
					contentsUsed: true,
					listGroup: 'mwReference/',
					listIndex: 0,
					listKey: 'auto/0',
					mw: {
						attrs: {},
						body: {
							html: 'Foo<!-- bar -->'
						},
						name: 'ref'
					},
					originalMw: '{"name":"ref","body":{"html":"Foo<!-- bar -->"},"attrs":{}}',
					refGroup: ''
				}
			},
			{ type: '/mwReference' },
			{ type: '/paragraph' },
			{ type: 'internalList' },
			{ type: 'internalItem', attributes: { originalHtml: 'Foo<!-- bar -->' } },
			{
				internal: {
					generated: 'wrapper'
				},
				type: 'paragraph'
			},
			'F', 'o', 'o',
			{
				type: 'comment',
				attributes: {
					text: ' bar '
				}
			},
			{ type: '/comment' },
			{ type: '/paragraph' },
			{ type: '/internalItem' },
			{ type: '/internalList' }
		]
	},
	'Template generated reflist': {
		body: '<p><sup about="#mwt2" class="mw-ref" id="cite_ref-1" rel="dc:references" typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:{&quot;id&quot;:&quot;mw-reference-text-cite_note-1&quot;},&quot;attrs&quot;:{&quot;group&quot;:&quot;notes&quot;}}"><a href="./Main_Page#cite_note-1" style="counter-reset: mw-Ref 1;" data-mw-group="notes"><span class="mw-reflink-text">[notes 1]</span></a></sup></p>' +
			'<div class="mw-references-wrap" typeof="mw:Extension/references mw:Transclusion" about="#mwt4" data-mw="{&quot;parts&quot;:[{&quot;template&quot;:{&quot;target&quot;:{&quot;wt&quot;:&quot;echo&quot;,&quot;href&quot;:&quot;./Template:Echo&quot;},&quot;params&quot;:{&quot;1&quot;:{&quot;wt&quot;:&quot;<references group=\\&quot;notes\\&quot; />&quot;}},&quot;i&quot;:0}}]}">' +
				'<ol class="mw-references references" data-mw-group="notes">' +
					'<li about="#cite_note-1" id="cite_note-1"><a href="./Main_Page#cite_ref-1" data-mw-group="notes" rel="mw:referencedBy"><span class="mw-linkback-text">↑ </span></a> <span id="mw-reference-text-cite_note-1" class="mw-reference-text">Foo</span></li>' +
				'</ol>' +
			'</div>',
		fromDataBody: '<p><sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:{&quot;id&quot;:&quot;mw-reference-text-cite_note-1&quot;},&quot;attrs&quot;:{&quot;group&quot;:&quot;notes&quot;}}"></sup></p>' +
			'<span typeof="mw:Transclusion" data-mw="{&quot;parts&quot;:[{&quot;template&quot;:{&quot;target&quot;:{&quot;wt&quot;:&quot;echo&quot;,&quot;href&quot;:&quot;./Template:Echo&quot;},&quot;params&quot;:{&quot;1&quot;:{&quot;wt&quot;:&quot;<references group=\\&quot;notes\\&quot; />&quot;}},&quot;i&quot;:0}}]}"></span>',
		clipboardBody: '<p><sup typeof="mw:Extension/ref" data-mw="{&quot;attrs&quot;:{&quot;group&quot;:&quot;notes&quot;},&quot;body&quot;:{&quot;id&quot;:&quot;mw-reference-text-cite_note-1&quot;,&quot;html&quot;:&quot;Foo&quot;},&quot;name&quot;:&quot;ref&quot;}" class="mw-ref"><a data-mw-group="notes" style="counter-reset: mw-Ref 1;"><span class="mw-reflink-text">[notes 1]</span></a></sup></p>' +
			'<div typeof="mw:Extension/references" data-mw="{&quot;parts&quot;:[{&quot;template&quot;:{&quot;params&quot;:{&quot;1&quot;:{&quot;wt&quot;:&quot;<references group=\\&quot;notes\\&quot; />&quot;}},&quot;target&quot;:{&quot;wt&quot;:&quot;echo&quot;,&quot;href&quot;:&quot;./Template:Echo&quot;},&quot;i&quot;:0}}],&quot;name&quot;:&quot;references&quot;}">' +
				// TODO: This should list should get populated on copy
				'<ol class="mw-references references"></ol>' +
			'</div>',
		data: [
			{ type: 'paragraph' },
			{
				type: 'mwReference',
				attributes: {
					contentsUsed: true,
					listGroup: 'mwReference/notes',
					listIndex: 0,
					listKey: 'auto/0',
					mw: {
						attrs: {
							group: 'notes'
						},
						body: {
							id: 'mw-reference-text-cite_note-1'
						},
						name: 'ref'
					},
					originalMw: '{"name":"ref","body":{"id":"mw-reference-text-cite_note-1"},"attrs":{"group":"notes"}}',
					refGroup: 'notes',
					refListItemId: 'mw-reference-text-cite_note-1'
				}
			},
			{ type: '/mwReference' },
			{ type: '/paragraph' },
			{
				type: 'mwReferencesList',
				attributes: {
					mw: {
						parts: [ {
							template: {
								params: {
									1: { wt: '<references group="notes" />' }
								},
								target: { wt: 'echo', href: './Template:Echo' },
								i: 0
							}
						} ]
					},
					originalMw: '{"parts":[{"template":{"target":{"wt":"echo","href":"./Template:Echo"},"params":{"1":{"wt":"<references group=\\"notes\\" />"}},"i":0}}]}',
					refGroup: '',
					listGroup: 'mwReference/',
					isResponsive: true,
					templateGenerated: true
				}
			},
			{ type: '/mwReferencesList' },
			{ type: 'internalList' },
			{ type: 'internalItem', attributes: { originalHtml: 'Foo' } },
			{
				internal: {
					generated: 'wrapper'
				},
				type: 'paragraph'
			},
			'F', 'o', 'o',
			{ type: '/paragraph' },
			{ type: '/internalItem' },
			{ type: '/internalList' }
		]
	},
	'Template generated reflist (div wrapped)': {
		body: '<p><sup about="#mwt2" class="mw-ref" id="cite_ref-1" rel="dc:references" typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:{&quot;id&quot;:&quot;mw-reference-text-cite_note-1&quot;},&quot;attrs&quot;:{}}"><a href="./Main_Page#cite_note-1" style="counter-reset: mw-Ref 1;"><span class="mw-reflink-text">[1]</span></a></sup></p>' +
			'<div about="#mwt3" typeof="mw:Transclusion" data-mw="{&quot;parts&quot;:[{&quot;template&quot;:{&quot;target&quot;:{&quot;wt&quot;:&quot;reflist&quot;,&quot;href&quot;:&quot;./Template:Reflist&quot;},&quot;params&quot;:{},&quot;i&quot;:0}}]}">' +
				'<div typeof="mw:Extension/references" about="#mwt5" data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{}}">' +
					'<ol class="mw-references references">' +
						'<li about="#cite_note-1" id="cite_note-1"><a href="./Main_Page#cite_ref-1" rel="mw:referencedBy"><span class="mw-linkback-text">↑ </span></a> <span id="mw-reference-text-cite_note-1" class="mw-reference-text">Foo</span></li>' +
					'</ol>' +
				'</div>' +
			'</div>',
		fromDataBody: '<p><sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:{&quot;id&quot;:&quot;mw-reference-text-cite_note-1&quot;},&quot;attrs&quot;:{}}"></sup></p>' +
			'<span typeof="mw:Transclusion" data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{}}"></span>',
		clipboardBody: '<p><sup typeof="mw:Extension/ref" data-mw="{&quot;attrs&quot;:{},&quot;body&quot;:{&quot;id&quot;:&quot;mw-reference-text-cite_note-1&quot;,&quot;html&quot;:&quot;Foo&quot;},&quot;name&quot;:&quot;ref&quot;}" class="mw-ref"><a style="counter-reset: mw-Ref 1;"><span class="mw-reflink-text">[1]</span></a></sup></p>' +
			'<div typeof="mw:Extension/references" data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{}}">' +
				'<ol class="mw-references references">' +
					'<li><a rel="mw:referencedBy"><span class="mw-linkback-text">↑ </span></a><span class="reference-text"><span class="ve-ce-branchNode ve-ce-internalItemNode">Foo</span></span></li>' +
				'</ol>' +
			'</div>',
		data: [
			{ type: 'paragraph' },
			{
				type: 'mwReference',
				attributes: {
					contentsUsed: true,
					listGroup: 'mwReference/',
					listIndex: 0,
					listKey: 'auto/0',
					mw: {
						attrs: {},
						body: {
							id: 'mw-reference-text-cite_note-1'
						},
						name: 'ref'
					},
					originalMw: '{"name":"ref","body":{"id":"mw-reference-text-cite_note-1"},"attrs":{}}',
					refGroup: '',
					refListItemId: 'mw-reference-text-cite_note-1'
				}
			},
			{ type: '/mwReference' },
			{ type: '/paragraph' },
			{
				type: 'mwReferencesList',
				attributes: {
					mw: {
						name: 'references',
						attrs: {}
					},
					originalMw: '{"name":"references","attrs":{}}',
					refGroup: '',
					listGroup: 'mwReference/',
					isResponsive: true,
					templateGenerated: true
				}
			},
			{ type: '/mwReferencesList' },
			{ type: 'internalList' },
			{ type: 'internalItem', attributes: { originalHtml: 'Foo' } },
			{
				internal: {
					generated: 'wrapper'
				},
				type: 'paragraph'
			},
			'F', 'o', 'o',
			{ type: '/paragraph' },
			{ type: '/internalItem' },
			{ type: '/internalList' }
		]
	}
};

ve.dm.citeExample.references = [
	{ type: 'paragraph' },
	{
		type: 'mwReference',
		attributes: {
			contentsUsed: true,
			listGroup: 'mwReference/',
			listIndex: 0,
			listKey: 'auto/0',
			mw: {
				attrs: {},
				body: { html: 'No name 1' },
				name: 'ref'
			},
			originalMw: '{"name":"ref","body":{"html":"No name 1"},"attrs":{}}',
			refGroup: ''
		}
	},
	{ type: '/mwReference' },
	{ type: '/paragraph' },
	{ type: 'paragraph' },
	'F', 'o', 'o',
	{
		type: 'mwReference',
		attributes: {
			contentsUsed: true,
			listGroup: 'mwReference/',
			listIndex: 1,
			listKey: 'literal/bar',
			mw: {
				attrs: { name: 'bar' },
				body: { html: 'Bar' },
				name: 'ref'
			},
			originalMw: '{"body":{"html":""},"attrs":{"name":"bar"}}',
			refGroup: ''
		}
	},
	{ type: '/mwReference' },
	' ', 'B', 'a', 'z',
	{
		type: 'mwReference',
		attributes: {
			contentsUsed: true,
			listGroup: 'mwReference/',
			listIndex: 2,
			listKey: 'literal/:3',
			mw: {
				attrs: { name: ':3' },
				body: { html: 'Quux' },
				name: 'ref'
			},
			originalMw: '{"name":"ref","body":{"html":"Quux"},"attrs":{"name":":3"}}',
			refGroup: ''
		}
	},
	{ type: '/mwReference' },
	' ', 'W', 'h', 'e', 'e',
	{
		type: 'mwReference',
		attributes: {
			contentsUsed: false,
			listGroup: 'mwReference/',
			listIndex: 1,
			listKey: 'literal/bar',
			mw: {
				attrs: { name: 'bar' },
				name: 'ref'
			},
			originalMw: '{"body":{"html":""},"attrs":{"name":"bar"}}',
			refGroup: ''
		}
	},
	{ type: '/mwReference' },
	' ', 'Y', 'a', 'y',
	{ type: '/paragraph' },
	{ type: 'paragraph' },
	{
		type: 'mwReference',
		attributes: {
			contentsUsed: true,
			listGroup: 'mwReference/',
			listIndex: 3,
			listKey: 'auto/1',
			mw: {
				attrs: {},
				body: { html: 'No name 2' },
				name: 'ref'
			},
			originalMw: '{"name":"ref","body":{"html":"No name 2"},"attrs":{}}',
			refGroup: ''
		}
	},
	{ type: '/mwReference' },
	{
		type: 'mwReference',
		attributes: {
			contentsUsed: true,
			listGroup: 'mwReference/foo',
			listIndex: 4,
			listKey: 'auto/2',
			mw: {
				attrs: { group: 'foo' },
				body: { html: 'No name 3' },
				name: 'ref'
			},
			originalMw: '{"name":"ref","body":{"html":"No name 3"},"attrs":{"group":"foo"}}',
			refGroup: 'foo'
		}
	},
	{ type: '/mwReference' },
	{ type: '/paragraph' },
	{
		type: 'mwReferencesList',
		// originalDomElements: HTML,
		attributes: {
			mw: {
				name: 'references',
				attrs: { group: 'g1' }
			},
			originalMw: '{"name":"references","attrs":{"group":"g1"}"}',
			listGroup: 'mwReference/',
			refGroup: '',
			isResponsive: true,
			templateGenerated: false
		}
	},
	{ type: '/mwReferencesList' },
	{ type: 'internalList' },
	{ type: 'internalItem' },
	{ type: 'paragraph' },
	'N', 'o', ' ', 'n', 'a', 'm', 'e', ' ', '1',
	{ type: '/paragraph' },
	{ type: '/internalItem' },
	{ type: 'internalItem' },
	{ type: 'paragraph' },
	'B', 'a', 'r',
	{ type: '/paragraph' },
	{ type: '/internalItem' },
	{ type: 'internalItem' },
	{ type: 'paragraph' },
	'Q', 'u', 'u', 'x',
	{ type: '/paragraph' },
	{ type: '/internalItem' },
	{ type: 'internalItem' },
	{ type: 'paragraph' },
	'N', 'o', ' ', 'n', 'a', 'm', 'e', ' ', '2',
	{ type: '/paragraph' },
	{ type: '/internalItem' },
	{ type: 'internalItem' },
	{ type: 'paragraph' },
	'N', 'o', ' ', 'n', 'a', 'm', 'e', ' ', '3',
	{ type: '/paragraph' },
	{ type: '/internalItem' },
	{ type: '/internalList' }
];

ve.dm.citeExample.complexInternalData = [
	// 0
	{ type: 'paragraph' },
	'F', [ 'o', [ ve.dm.example.bold ] ], [ 'o', [ ve.dm.example.italic ] ],
	// 4
	{ type: 'mwReference', attributes: {
		about: '#mwt1',
		listIndex: 0,
		listGroup: 'mwReference/',
		listKey: 'auto/0',
		refGroup: ''
	} },
	// 5
	{ type: '/mwReference' },
	// 6
	{ type: '/paragraph' },
	// 7
	{ type: 'internalList' },
	// 8
	{ type: 'internalItem' },
	// 9
	{ type: 'paragraph', internal: { generated: 'wrapper' } },
	'R', [ 'e', [ ve.dm.example.bold ] ], 'f',
	// 13
	'e', [ 'r', [ ve.dm.example.italic ] ], [ 'e', [ ve.dm.example.italic ] ],
	// 16
	{ type: 'mwReference', attributes: {
		mw: {},
		about: '#mwt2',
		listIndex: 1,
		listGroup: 'mwReference/',
		listKey: 'foo',
		refGroup: '',
		contentsUsed: true
	} },
	// 17
	{ type: '/mwReference' },
	'n', 'c', 'e',
	// 21
	{ type: '/paragraph' },
	// 22
	{ type: '/internalItem' },
	// 23
	{ type: 'internalItem' },
	// 24
	{ type: 'preformatted' },
	// 25
	{ type: 'mwEntity', attributes: { character: '€' } },
	// 26
	{ type: '/mwEntity' },
	'2', '5', '0',
	// 30
	{ type: '/preformatted' },
	// 31
	{ type: '/internalItem' },
	// 32
	{ type: '/internalList' }
	// 33
];

ve.dm.citeExample.complexInternalData.internalItems = [
	{ group: 'mwReference', key: null, body: 'First reference' },
	{ group: 'mwReference', key: 'foo', body: 'Table in ref: <table><tr><td>because I can</td></tr></table>' }
];

ve.dm.citeExample.complexInternalData.internalListNextUniqueNumber = 1;
