'use strict';

const ParsoidExtApi = module.parent.require('./extapi.js').versionCheck('^0.11.0');
const { DOMDataUtils, DOMUtils, Promise } = ParsoidExtApi;

/**
 * See tests/parser/ParserTestParserHook.php in core.
 */

const dumpHook = Promise.method(function(state, content, args) {
	return state.env.createDocument('<pre />');
});

const staticTagHook = Promise.method(function(state, content, args) {
	// FIXME: Choose a better DOM representation that doesn't mess with
	// newline constraints.
	return state.env.createDocument('<span />');
});

const staticTagPostProcessor = function(node, obj) {
	if (DOMUtils.isElt(node)) {
		const typeOf = node.getAttribute('typeof');
		if ((/(?:^|\s)mw:Extension\/statictag(?=$|\s)/).test(typeOf)) {
			const dataMw = DOMDataUtils.getDataMw(node);
			if (dataMw.attrs.action === 'flush') {
				node.appendChild(node.ownerDocument.createTextNode(obj.buf));
				obj.buf = '';
			} else {
				obj.buf += dataMw.body.extsrc;
			}
		}
	}
};

class DOMPostProcessor {
	run(body, env, options, atTopLevel) {
		if (atTopLevel) {
			const obj = { buf: '' };
			DOMUtils.visitDOM(body, staticTagPostProcessor, obj);
		}
	}
}

// Tag constructor
module.exports = function() {
	this.config = {
		tags: [
			{ name: 'tag', toDOM: dumpHook },
			{ name: 'tåg', toDOM: dumpHook },
			{ name: 'statictag', toDOM: staticTagHook },
		],
		domProcessors: {
			wt2htmlPostProcessor: DOMPostProcessor,
		},
	};
};
