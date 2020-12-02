/**
 * @module
 */

'use strict';

require('../core-upgrade.js');

var colors = require('colors');
var entities = require('entities');
var yargs = require('yargs');

var Diff = require('../lib/utils/Diff.js').Diff;
var ContentUtils = require('../lib/utils/ContentUtils.js').ContentUtils;
var DOMUtils = require('../lib/utils/DOMUtils.js').DOMUtils;
var DOMDataUtils = require('../lib/utils/DOMDataUtils.js').DOMDataUtils;
var ScriptUtils = require('../tools/ScriptUtils.js').ScriptUtils;
var Util = require('../lib/utils/Util.js').Util;
var WTUtils = require('../lib/utils/WTUtils.js').WTUtils;
var DOMNormalizer = require('../lib/html2wt/DOMNormalizer.js').DOMNormalizer;
var MockEnv = require('./MockEnv.js').MockEnv;
var JSUtils = require('../lib/utils/jsutils.js').JSUtils;

var TestUtils = {};

/**
 * Little helper function for encoding XML entities.
 *
 * @param {string} string
 * @return {string}
 */
TestUtils.encodeXml = function(string) {
	return entities.encodeXML(string);
};

/**
 * Specialized normalization of the PHP parser & Parsoid output, to ignore
 * a few known-ok differences in parser test runs.
 *
 * This code is also used by the Parsoid round-trip testing code.
 *
 * If parsoidOnly is true-ish, we allow more markup through (like property
 * and typeof attributes), for better checking of parsoid-only test cases.
 *
 * @param {string} domBody
 * @param {Object} options
 * @param {boolean} [options.parsoidOnly=false]
 * @param {boolean} [options.preserveIEW=false]
 * @param {boolean} [options.scrubWikitext=false]
 * @param {boolean} [options.rtTestMode=false]
 * @return {string}
 */
TestUtils.normalizeOut = function(domBody, options) {
	if (!options) {
		options = {};
	}
	const parsoidOnly = options.parsoidOnly;
	const preserveIEW = options.preserveIEW;

	if (options.scrubWikitext) {
		// Mock env obj
		//
		// FIXME: This is ugly.
		// (a) The normalizer shouldn't need the full env.
		//     Pass options and a logger instead?
		// (b) DOM diff code is using page-id for some reason.
		//     That feels like a carryover of 2013 era code.
		//     If possible, get rid of it and diff-mark dependency
		//     on the env object.
		const env = new MockEnv({ scrubWikitext: true }, null);
		if (typeof (domBody) === 'string') {
			domBody = env.createDocument(domBody).body;
		}
		var mockState = {
			env,
			selserMode: false,
			rtTestMode: options.rtTestMode,
		};
		DOMDataUtils.visitAndLoadDataAttribs(domBody, { markNew: true });
		domBody = (new DOMNormalizer(mockState).normalize(domBody));
		DOMDataUtils.visitAndStoreDataAttribs(domBody);
	} else {
		if (typeof (domBody) === 'string') {
			domBody = DOMUtils.parseHTML(domBody).body;
		}
	}

	var stripTypeof = parsoidOnly ?
		/(?:^|mw:DisplaySpace\s+)mw:Placeholder$/ :
		/^mw:(?:(?:DisplaySpace\s+mw:)?Placeholder|Nowiki|Transclusion|Entity)$/;
	domBody = this.unwrapSpansAndNormalizeIEW(domBody, stripTypeof, parsoidOnly, preserveIEW);
	var out = ContentUtils.toXML(domBody, { innerXML: true });
	// NOTE that we use a slightly restricted regexp for "attribute"
	//  which works for the output of DOM serialization.  For example,
	//  we know that attribute values will be surrounded with double quotes,
	//  not unquoted or quoted with single quotes.  The serialization
	//  algorithm is given by:
	//  http://www.whatwg.org/specs/web-apps/current-work/multipage/the-end.html#serializing-html-fragments
	if (!/[^<]*(<\w+(\s+[^\0-\cZ\s"'>\/=]+(="[^"]*")?)*\/?>[^<]*)*/.test(out)) {
		throw new Error("normalizeOut input is not in standard serialized form");
	}

	// Eliminate a source of indeterminacy from leaked strip markers
	out = out.replace(/UNIQ-.*?-QINU/g, '');

	// And from the imagemap extension - the id attribute is not always around, it appears!
	out = out.replace(/<map name="ImageMap_[^"]*"( id="ImageMap_[^"]*")?( data-parsoid="[^"]*")?>/g, '<map>');

	// Normalize COINS ids -- they aren't stable
	out = out.replace(/\s?id=['"]coins_\d+['"]/ig, '');

	// Eliminate transience from priority hints (T216499)
	out = out.replace(/\s?importance="high"/g, '');
	out = out.replace(/\s?elementtiming="thumbnail-(high|top)"/g, '');

	// maplink extension
	out = out.replace(/\s?data-overlays='[^']*'/ig, '');

	if (parsoidOnly) {
		// unnecessary attributes, we don't need to check these
		// style is in there because we should only check classes.
		out = out.replace(/ (data-parsoid|prefix|about|rev|datatype|inlist|usemap|vocab|content|style)=\\?"[^\"]*\\?"/g, '');
		// single-quoted variant
		out = out.replace(/ (data-parsoid|prefix|about|rev|datatype|inlist|usemap|vocab|content|style)=\\?'[^\']*\\?'/g, '');
		// apos variant
		out = out.replace(/ (data-parsoid|prefix|about|rev|datatype|inlist|usemap|vocab|content|style)=&apos;.*?&apos;/g, '');

		// strip self-closed <nowiki /> because we frequently test WTS
		// <nowiki> insertion by providing an html/parsoid section with the
		// <meta> tags stripped out, allowing the html2wt test to verify that
		// the <nowiki> is correctly added during WTS, while still allowing
		// the html2html and wt2html versions of the test to pass as a
		// sanity check.  If <meta>s were not stripped, these tests would all
		// have to be modified and split up.  Not worth it at this time.
		// (see commit 689b22431ad690302420d049b10e689de6b7d426)
		out = out
			.replace(/<span typeof="mw:Nowiki"><\/span>/g, '');

		return out;
	}

	// Normalize headings by stripping out Parsoid-added ids so that we don't
	// have to add these ids to every parser test that uses headings.
	// We will test the id generation scheme separately via mocha tests.
	out = out.replace(/(<h[1-6].*?) id="[^"]*"([^>]*>)/g, '$1$2');

	// strip meta/link elements
	out = out
		.replace(/<\/?(?:meta|link)(?: [^\0-\cZ\s"'>\/=]+(?:=(?:"[^"]*"|'[^']*'))?)*\/?>/g, '');
	// Ignore troublesome attributes.
	// Strip JSON attributes like data-mw and data-parsoid early so that
	// comment stripping in normalizeNewlines does not match unbalanced
	// comments in wikitext source.
	out = out.replace(/ (data-mw|data-parsoid|resource|rel|prefix|about|rev|datatype|inlist|property|usemap|vocab|content|class)=\\?"[^\"]*\\?"/g, '');
	// single-quoted variant
	out = out.replace(/ (data-mw|data-parsoid|resource|rel|prefix|about|rev|datatype|inlist|property|usemap|vocab|content|class)=\\?'[^\']*\\?'/g, '');
	// strip typeof last
	out = out.replace(/ typeof="[^\"]*"/g, '');

	return out
		// replace mwt ids
		.replace(/ id="mw((t\d+)|([\w-]{2,}))"/g, '')
		.replace(/<span[^>]+about="[^"]*"[^>]*>/g, '')
		.replace(/(\s)<span>\s*<\/span>\s*/g, '$1')
		.replace(/<span>\s*<\/span>/g, '')
		.replace(/(href=")(?:\.?\.\/)+/g, '$1')
		// replace unnecessary URL escaping
		.replace(/ href="[^"]*"/g, Util.decodeURI)
		// strip thumbnail size prefixes
		.replace(/(src="[^"]*?)\/thumb(\/[0-9a-f]\/[0-9a-f]{2}\/[^\/]+)\/[0-9]+px-[^"\/]+(?=")/g, '$1$2');
};

/**
 * Normalize newlines in IEW to spaces instead.
 *
 * @param {Node} body
 *   The document `<body>` node to normalize.
 * @param {RegExp} [stripSpanTypeof]
 * @param {boolean} [parsoidOnly=false]
 * @param {boolean} [preserveIEW=false]
 * @return {Node}
 */
TestUtils.unwrapSpansAndNormalizeIEW = function(body, stripSpanTypeof, parsoidOnly, preserveIEW) {
	var newlineAround = function(node) {
		return node && /^(BODY|CAPTION|DIV|DD|DT|LI|P|TABLE|TR|TD|TH|TBODY|DL|OL|UL|H[1-6])$/.test(node.nodeName);
	};
	var unwrapSpan;  // forward declare
	var cleanSpans = function(node) {
		var child, next;
		if (!stripSpanTypeof) { return; }
		for (child = node.firstChild; child; child = next) {
			next = child.nextSibling;
			if (child.nodeName === 'SPAN' &&
				stripSpanTypeof.test(child.getAttribute('typeof') || '')) {
				unwrapSpan(node, child);
			}
		}
	};
	unwrapSpan = function(parent, node) {
		// first recurse to unwrap any spans in the immediate children.
		cleanSpans(node);
		// now unwrap this span.
		DOMUtils.migrateChildren(node, parent, node);
		parent.removeChild(node);
	};
	var visit = function(node, stripLeadingWS, stripTrailingWS, inPRE) {
		var child, next, prev;
		if (node.nodeName === 'PRE') {
			// Preserve newlines in <pre> tags
			inPRE = true;
		}
		if (!preserveIEW && DOMUtils.isText(node)) {
			if (!inPRE) {
				node.data = node.data.replace(/\s+/g, ' ');
			}
			if (stripLeadingWS) {
				node.data = node.data.replace(/^\s+/, '');
			}
			if (stripTrailingWS) {
				node.data = node.data.replace(/\s+$/, '');
			}
		}
		// unwrap certain SPAN nodes
		cleanSpans(node);
		// now remove comment nodes
		if (!parsoidOnly) {
			for (child = node.firstChild; child; child = next) {
				next = child.nextSibling;
				if (DOMUtils.isComment(child)) {
					node.removeChild(child);
				}
			}
		}
		// reassemble text nodes split by a comment or span, if necessary
		node.normalize();
		// now recurse.
		if (node.nodeName === 'PRE') {
			// hack, since PHP adds a newline before </pre>
			stripLeadingWS = false;
			stripTrailingWS = true;
		} else if (node.nodeName === 'SPAN' &&
				/^mw[:]/.test(node.getAttribute('typeof') || '')) {
			// SPAN is transparent; pass the strip parameters down to kids
		} else {
			stripLeadingWS = stripTrailingWS = newlineAround(node);
		}
		child = node.firstChild;
		// Skip over the empty mw:FallbackId <span> and strip leading WS
		// on the other side of it.
		if (/^H[1-6]$/.test(node.nodeName) &&
			child && WTUtils.isFallbackIdSpan(child)) {
			child = child.nextSibling;
		}
		for (; child; child = next) {
			next = child.nextSibling;
			visit(child,
				stripLeadingWS,
				stripTrailingWS && !child.nextSibling,
				inPRE);
			stripLeadingWS = false;
		}
		if (inPRE || preserveIEW) { return node; }
		// now add newlines around appropriate nodes.
		for (child = node.firstChild; child; child = next) {
			prev = child.previousSibling;
			next = child.nextSibling;
			if (newlineAround(child)) {
				if (prev && DOMUtils.isText(prev)) {
					prev.data = prev.data.replace(/\s*$/, '\n');
				} else {
					prev = node.ownerDocument.createTextNode('\n');
					node.insertBefore(prev, child);
				}
				if (next && DOMUtils.isText(next)) {
					next.data = next.data.replace(/^\s*/, '\n');
				} else {
					next = node.ownerDocument.createTextNode('\n');
					node.insertBefore(next, child.nextSibling);
				}
			}
		}
		return node;
	};
	// clone body first, since we're going to destructively mutate it.
	return visit(body.cloneNode(true), true, true, false);
};

/**
 * Strip some php output we aren't generating.
 */
TestUtils.normalizePhpOutput = function(html) {
	return html
		// do not expect section editing for now
		.replace(/<span[^>]+class="mw-headline"[^>]*>(.*?)<\/span> *(<span class="mw-editsection"><span class="mw-editsection-bracket">\[<\/span>.*?<span class="mw-editsection-bracket">\]<\/span><\/span>)?/g, '$1')
		.replace(/<a[^>]+class="mw-headline-anchor"[^>]*>§<\/a>/g, '');
};

/**
 * Normalize the expected parser output by parsing it using a HTML5 parser and
 * re-serializing it to HTML. Ideally, the parser would normalize inter-tag
 * whitespace for us. For now, we fake that by simply stripping all newlines.
 *
 * @param {string} source
 * @return {string}
 */
TestUtils.normalizeHTML = function(source) {
	try {
		var body = this.unwrapSpansAndNormalizeIEW(DOMUtils.parseHTML(source).body);
		var html = ContentUtils.toXML(body, { innerXML: true })
			// a few things we ignore for now..
			//  .replace(/\/wiki\/Main_Page/g, 'Main Page')
			// do not expect a toc for now
			.replace(/<div[^>]+?id="toc"[^>]*>\s*<div id="toctitle"[^>]*>[\s\S]+?<\/div>[\s\S]+?<\/div>\s*/g, '');
		return this.normalizePhpOutput(html)
			// remove empty span tags
			.replace(/(\s)<span>\s*<\/span>\s*/g, '$1')
			.replace(/<span>\s*<\/span>/g, '')
			// general class and titles, typically on links
			.replace(/ (class|rel|about|typeof)="[^"]*"/g, '')
			// strip red link markup, we do not check if a page exists yet
			.replace(/\/index.php\?title=([^']+?)&amp;action=edit&amp;redlink=1/g, '/wiki/$1')
			// strip red link title info
			.replace(/ \((?:page does not exist|encara no existeix|bet ele jaratılmag'an|lonkásá  ezalí tɛ̂)\)/g, '')  // eslint-disable-line
			// the expected html has some extra space in tags, strip it
			.replace(/<a +href/g, '<a href')
			.replace(/href="\/wiki\//g, 'href="')
			.replace(/" +>/g, '">')
			// parsoid always add a page name to lonely fragments
			.replace(/href="#/g, 'href="Main Page#')
			// replace unnecessary URL escaping
			.replace(/ href="[^"]*"/g, Util.decodeURI)
			// strip empty spans
			.replace(/(\s)<span>\s*<\/span>\s*/g, '$1')
			.replace(/<span>\s*<\/span>/g, '');
	} catch (e) {
		console.log("normalizeHTML failed on" +
			source + " with the following error: " + e);
		console.trace();
		return source;
	}
};

/**
 * Colorize given number if <> 0.
 *
 * @param {number} count
 * @param {string} color
 * @return {string} Colorized count
 */
var colorizeCount = function(count, color) {
	// We need a string to use colors methods
	var s = count.toString();
	if (count === 0 || !s[color]) {
		return s;
	}
	return s[color] + '';
};

/**
 * @param {Array} modesRan
 * @param {Object} stats
 * @param {number} stats.failedTests Number of failed tests due to differences in output.
 * @param {number} stats.passedTests Number of tests passed without any special consideration.
 * @param {Object} stats.modes All of the stats (failedTests and passedTests) per-mode.
 * @param {string} file
 * @param {number} loggedErrorCount
 * @param {RegExp|null} testFilter
 * @param {boolean} blacklistChanged
 * @return {number} The number of failures.
 */
var reportSummary = function(modesRan, stats, file, loggedErrorCount, testFilter, blacklistChanged) {
	var curStr, mode, thisMode;
	var failTotalTests = stats.failedTests;
	var happiness = (
		stats.passedTestsUnexpected === 0 && stats.failedTestsUnexpected === 0
	);
	var filename = (file === null) ? "ALL TESTS" : file;

	if (file === null) { console.log(); }
	console.log("==========================================================");
	console.log("SUMMARY:", happiness ? filename.green : filename.red);
	if (console.time && console.timeEnd && file !== null) {
		console.timeEnd('Execution time');
	}

	if (failTotalTests !== 0) {
		for (var i = 0; i < modesRan.length; i++) {
			mode = modesRan[i];
			curStr = mode + ': ';
			thisMode = stats.modes[mode];
			curStr += colorizeCount(thisMode.passedTests, 'green') + ' passed (';
			curStr += colorizeCount(thisMode.passedTestsUnexpected, 'red') + ' unexpected) / ';
			curStr += colorizeCount(thisMode.failedTests, 'red') + ' failed (';
			curStr += colorizeCount(thisMode.failedTestsUnexpected, 'red') + ' unexpected)';
			console.log(curStr);
		}

		curStr = 'TOTAL' + ': ';
		curStr += colorizeCount(stats.passedTests, 'green') + ' passed (';
		curStr += colorizeCount(stats.passedTestsUnexpected, 'red') + ' unexpected) / ';
		curStr += colorizeCount(stats.failedTests, 'red') + ' failed (';
		curStr += colorizeCount(stats.failedTestsUnexpected, 'red') + ' unexpected)';
		console.log(curStr);

		if (file === null) {
			console.log(colorizeCount(stats.passedTests, 'green') +
				' total passed tests (expected ' +
				(stats.passedTests - stats.passedTestsUnexpected + stats.failedTestsUnexpected) +
				'), ' +
				colorizeCount(failTotalTests , 'red') + ' total failures (expected ' +
				(stats.failedTests - stats.failedTestsUnexpected + stats.passedTestsUnexpected) +
				')');
		}
	} else {
		if (testFilter !== null) {
			console.log("Passed " + stats.passedTests +
					" of " + stats.passedTests + " tests matching " + testFilter +
					"... " + "ALL TESTS PASSED!".green);
		} else {
			// Should not happen if it does: Champagne!
			console.log("Passed " + stats.passedTests + " of " + stats.passedTests +
					" tests... " + "ALL TESTS PASSED!".green);
		}
	}

	// If we logged error messages, complain about it.
	var logMsg = 'No errors logged.'.green;
	if (loggedErrorCount > 0) {
		logMsg = (loggedErrorCount + " errors logged.").red;
	}
	if (file === null) {
		if (loggedErrorCount > 0) {
			logMsg = ('' + loggedErrorCount).red;
		} else {
			logMsg = ('' + loggedErrorCount).green;
		}
		logMsg += ' errors logged.';
	}
	console.log(logMsg);

	var failures = (
		stats.passedTestsUnexpected +
		stats.failedTestsUnexpected +
		loggedErrorCount
	);

	// If the blacklist changed, complain about it.
	if (blacklistChanged) {
		console.log("Blacklist changed!".red);
	}

	if (file === null) {
		if (failures === 0) {
			console.log('--> ' + 'NO UNEXPECTED RESULTS'.green + ' <--');
			if (blacklistChanged) {
				console.log("Perhaps some tests were deleted or renamed.");
				console.log("Use `bin/parserTests.js --rewrite-blacklist` to update blacklist.");
			}
		} else {
			console.log(('--> ' + failures + ' UNEXPECTED RESULTS. <--').red);
		}
	}

	return failures;
};

var prettyPrintIOptions = function(iopts) {
	if (!iopts) { return ''; }
	var ppValue = function(v) {
		if (Array.isArray(v)) {
			return v.map(ppValue).join(',');
		}
		if (typeof v !== 'string') {
			return JSON.stringify(v);
		}
		if (/^\[\[[^\]]*\]\]$/.test(v) || /^[-\w]+$/.test(v)) {
			return v;
		}
		return JSON.stringify(v);
	};
	return Object.keys(iopts).map(function(k) {
		if (iopts[k] === '') { return k; }
		return k + '=' + ppValue(iopts[k]);
	}).join(' ');
};

/**
 * @param {Object} stats
 * @param {Object} item
 * @param {Object} options
 * @param {string} mode
 * @param {string} title
 * @param {Object} actual
 * @param {Object} expected
 * @param {boolean} expectFail Whether this test was expected to fail (on blacklist).
 * @param {boolean} failureOnly Whether we should print only a failure message, or go on to print the diff.
 * @param {Object} bl BlackList.
 * @return {boolean} True if the failure was expected.
 */
var printFailure = function(stats, item, options, mode, title, actual, expected, expectFail, failureOnly, bl) {
	stats.failedTests++;
	stats.modes[mode].failedTests++;
	var fail = {
		title: title,
		raw: actual ? actual.raw : null,
		expected: expected ? expected.raw : null,
		actualNormalized: actual ? actual.normal : null,
	};
	stats.modes[mode].failList.push(fail);

	const extTitle = `${title} (${mode})`.replace('\n', ' ');

	var blacklisted = false;
	if (ScriptUtils.booleanOption(options.blacklist) && expectFail) {
		// compare with remembered output
		var normalizeAbout = s => s.replace(/(about=\\?["']#mwt)\d+/g, '$1');
		if (normalizeAbout(bl[title][mode]) !== normalizeAbout(actual.raw)) {
			blacklisted = true;
		} else {
			if (!ScriptUtils.booleanOption(options.quiet)) {
				console.log('EXPECTED FAIL'.red + ': ' + extTitle.yellow);
			}
			return true;
		}
	}

	stats.failedTestsUnexpected++;
	stats.modes[mode].failedTestsUnexpected++;
	fail.unexpected = true;

	if (!failureOnly) {
		console.log('=====================================================');
	}

	if (blacklisted) {
		console.log('UNEXPECTED BLACKLIST FAIL'.red.inverse + ': ' + extTitle.yellow);
		console.log('Blacklisted, but the output changed!'.red);
	} else {
		console.log('UNEXPECTED FAIL'.red.inverse + ': ' + extTitle.yellow);
	}

	if (mode === 'selser') {
		if (item.hasOwnProperty('wt2wtPassed') && item.wt2wtPassed) {
			console.log('Even worse, the non-selser wt2wt test passed!'.red);
		} else if (actual && item.hasOwnProperty('wt2wtResult') &&
				item.wt2wtResult !== actual.raw) {
			console.log('Even worse, the non-selser wt2wt test had a different result!'.red);
		}
	}

	if (!failureOnly) {
		console.log(item.comments.join('\n'));
		if (options) {
			console.log('OPTIONS'.cyan + ':');
			console.log(prettyPrintIOptions(item.options) + '\n');
		}
		console.log('INPUT'.cyan + ':');
		console.log(actual.input + '\n');
		console.log(options.getActualExpected(actual, expected, options.getDiff));
	}

	return false;
};

/**
 * @param {Object} stats
 * @param {Object} item
 * @param {Object} options
 * @param {string} mode
 * @param {string} title
 * @param {boolean} expectSuccess Whether this success was expected (or was this test blacklisted?).
 * @return {boolean} True if the success was expected.
 */
var printSuccess = function(stats, item, options, mode, title, expectSuccess) {
	var quiet = ScriptUtils.booleanOption(options.quiet);
	stats.passedTests++;
	stats.modes[mode].passedTests++;

	const extTitle = `${title} (${mode})`.replace('\n', ' ');

	if (ScriptUtils.booleanOption(options.blacklist) && !expectSuccess) {
		stats.passedTestsUnexpected++;
		stats.modes[mode].passedTestsUnexpected++;
		console.log('UNEXPECTED PASS'.green.inverse +
			':' + extTitle.yellow);
		return false;
	}
	if (!quiet) {
		var outStr = 'EXPECTED PASS';

		outStr = outStr.green + ': ' + extTitle.yellow;

		console.log(outStr);

		if (mode === 'selser' && item.hasOwnProperty('wt2wtPassed') &&
				!item.wt2wtPassed) {
			console.log('Even better, the non-selser wt2wt test failed!'.red);
		}
	}
	return true;
};

/**
 * Print the actual and expected outputs.
 *
 * @param {Object} actual
 * @param {string} actual.raw
 * @param {string} actual.normal
 * @param {Object} expected
 * @param {string} expected.raw
 * @param {string} expected.normal
 * @param {Function} getDiff Returns a string showing the diff(s) for the test.
 * @param {Object} getDiff.actual
 * @param {Object} getDiff.expected
 * @return {string}
 */
var getActualExpected = function(actual, expected, getDiff) {
	let mkVisible =
		s => s.replace(/\n/g, '\u21b5\n'.white).replace(/\xA0/g, '\u2423'.white);
	if (colors.mode === 'none') {
		mkVisible = s => s;
	}
	var returnStr = '';
	returnStr += 'RAW EXPECTED'.cyan + ':\n';
	returnStr += expected.raw + '\n';

	returnStr += 'RAW RENDERED'.cyan + ':\n';
	returnStr += actual.raw + '\n';

	returnStr += 'NORMALIZED EXPECTED'.magenta + ':\n';
	returnStr += mkVisible(expected.normal) + '\n';

	returnStr += 'NORMALIZED RENDERED'.magenta + ':\n';
	returnStr += mkVisible(actual.normal) + '\n';

	returnStr += 'DIFF'.cyan + ':\n';
	returnStr += getDiff(actual, expected);

	return returnStr;
};

/**
 * @param {Object} actual
 * @param {string} actual.normal
 * @param {Object} expected
 * @param {string} expected.normal
 * @return {string} Colorized diff
 */
var doDiff = function(actual, expected) {
	// safe to always request color diff, because we set color mode='none'
	// if colors are turned off.
	var e = expected.normal.replace(/\xA0/g, '\u2423');
	var a = actual.normal.replace(/\xA0/g, '\u2423');
	return Diff.colorDiff(e, a, {
		context: 2,
		noColor: (colors.mode === 'none'),
	});
};

/**
 * @param {Function} reportFailure
 * @param {Function} reportSuccess
 * @param {Object} bl BlackList.
 * @param {Object} stats
 * @param {Object} item
 * @param {Object} options
 * @param {string} mode
 * @param {Object} expected
 * @param {Object} actual
 * @param {Function} pre
 * @param {Function} post
 * @return {boolean} True if the result was as expected.
 */
function printResult(reportFailure, reportSuccess, bl, stats, item, options, mode, expected, actual, pre, post) {
	var title = item.title;  // Title may be modified here, so pass it on.

	var quick = ScriptUtils.booleanOption(options.quick);

	if (mode === 'selser') {
		title += ' ' + (item.changes ? JSON.stringify(item.changes) : '[manual]');
	} else if (mode === 'wt2html' && item.options.langconv) {
		title += ' [langconv]';
	}

	var tb = bl[title];
	var expectFail = (tb && tb.hasOwnProperty(mode));
	var fail = (expected.normal !== actual.normal);
	// Return whether the test was as expected, independent of pass/fail
	var asExpected;

	if (mode === 'wt2wt') {
		item.wt2wtPassed = !fail;
		item.wt2wtResult = actual.raw;
	}

	// don't report selser fails when nothing was changed or it's a dup
	if (
		mode === 'selser' && !JSUtils.deepEquals(item.changetree, ['manual']) &&
		(JSUtils.deepEquals(item.changes, []) || item.duplicateChange)
	) {
		return true;
	}

	if (typeof pre === 'function') {
		pre(stats, mode, title, item.time);
	}

	if (fail) {
		asExpected = reportFailure(stats, item, options, mode, title, actual, expected, expectFail, quick, bl);
	} else {
		asExpected = reportSuccess(stats, item, options, mode, title, !expectFail);
	}

	if (typeof post === 'function') {
		post(stats, mode);
	}

	return asExpected;
}

var _reportOnce = false;
/**
 * Simple function for reporting the start of the tests.
 *
 * This method can be reimplemented in the options of the ParserTests object.
 */
var reportStartOfTests = function() {
	if (!_reportOnce) {
		_reportOnce = true;
		console.log('ParserTests running with node', process.version);
		console.log('Initialization complete. Now launching tests.');
	}
};

/**
 * Get the actual and expected outputs encoded for XML output.
 *
 * @inheritdoc getActualExpected
 *
 * @return {string} The XML representation of the actual and expected outputs.
 */
var getActualExpectedXML = function(actual, expected, getDiff) {
	var returnStr = '';

	returnStr += 'RAW EXPECTED:\n';
	returnStr += TestUtils.encodeXml(expected.raw) + '\n\n';

	returnStr += 'RAW RENDERED:\n';
	returnStr += TestUtils.encodeXml(actual.raw) + '\n\n';

	returnStr += 'NORMALIZED EXPECTED:\n';
	returnStr += TestUtils.encodeXml(expected.normal) + '\n\n';

	returnStr += 'NORMALIZED RENDERED:\n';
	returnStr += TestUtils.encodeXml(actual.normal) + '\n\n';

	returnStr += 'DIFF:\n';
	returnStr += TestUtils.encodeXml(getDiff(actual, expected, false));

	return returnStr;
};

/**
 * Report the start of the tests output.
 *
 * @inheritdoc reportStart
 */
var reportStartXML = function() {};

/**
 * Report the end of the tests output.
 *
 * @inheritdoc reportSummary
 */
var reportSummaryXML = function(modesRan, stats, file, loggedErrorCount, testFilter, blacklistChanged) {
	if (file === null) {
		/* Summary for all tests; not included in XML format output. */
		return;
	}
	console.log('<testsuites file="' + file + '">');
	for (var i = 0; i < modesRan.length; i++) {
		var mode = modesRan[i];
		console.log('<testsuite name="parserTests-' + mode + '">');
		console.log(stats.modes[mode].result);
		console.log('</testsuite>');
	}
	console.log('</testsuites>');
};

/**
 * Print a failure message for a test in XML.
 *
 * @inheritdoc printFailure
 */
var reportFailureXML = function(stats, item, options, mode, title, actual, expected, expectFail, failureOnly, bl) {
	stats.failedTests++;
	stats.modes[mode].failedTests++;
	var failEle = '';
	var blacklisted = false;
	if (ScriptUtils.booleanOption(options.blacklist) && expectFail) {
		// compare with remembered output
		blacklisted = (bl[title][mode] === actual.raw);
	}
	if (!blacklisted) {
		failEle += '<failure type="parserTestsDifferenceInOutputFailure">\n';
		failEle += getActualExpectedXML(actual, expected, options.getDiff);
		failEle += '\n</failure>';
		stats.failedTestsUnexpected++;
		stats.modes[mode].failedTestsUnexpected++;
		stats.modes[mode].result += failEle;
	}
};

/**
 * Print a success method for a test in XML.
 *
 * @inheritdoc printSuccess
 */
var reportSuccessXML = function(stats, item, options, mode, title, expectSuccess) {
	stats.passedTests++;
	stats.modes[mode].passedTests++;
};

/**
 * Print the result of a test in XML.
 *
 * @inheritdoc printResult
 */
var reportResultXML = function() {
	function pre(stats, mode, title, time) {
		var testcaseEle;
		testcaseEle = '<testcase name="' + TestUtils.encodeXml(title) + '" ';
		testcaseEle += 'assertions="1" ';

		var timeTotal;
		if (time && time.end && time.start) {
			timeTotal = time.end - time.start;
			if (!isNaN(timeTotal)) {
				testcaseEle += 'time="' + ((time.end - time.start) / 1000.0) + '"';
			}
		}

		testcaseEle += '>';
		stats.modes[mode].result += testcaseEle;
	}

	function post(stats, mode) {
		stats.modes[mode].result += '</testcase>';
	}

	var args = Array.from(arguments);
	args = [ reportFailureXML, reportSuccessXML ].concat(args, pre, post);
	printResult.apply(this, args);

	// In xml, test all cases always
	return true;
};

/**
 * Get the options from the command line.
 *
 * @return {Object}
 */
var getOpts = function() {
	var standardOpts = ScriptUtils.addStandardOptions({
		'wt2html': {
			description: 'Wikitext -> HTML(DOM)',
			'default': false,
			'boolean': true,
		},
		'html2wt': {
			description: 'HTML(DOM) -> Wikitext',
			'default': false,
			'boolean': true,
		},
		'wt2wt': {
			description: 'Roundtrip testing: Wikitext -> DOM(HTML) -> Wikitext',
			'default': false,
			'boolean': true,
		},
		'html2html': {
			description: 'Roundtrip testing: HTML(DOM) -> Wikitext -> HTML(DOM)',
			'default': false,
			'boolean': true,
		},
		'selser': {
			description: 'Roundtrip testing: Wikitext -> DOM(HTML) -> Wikitext (with selective serialization). ' +
				'Set to "noauto" to just run the tests with manual selser changes.',
			'boolean': false,
		},
		'changetree': {
			description: 'Changes to apply to parsed HTML to generate new HTML to be serialized (useful with selser)',
			'default': null,
			'boolean': false,
		},
		'numchanges': {
			description: 'Make multiple different changes to the DOM, run a selser test for each one.',
			'default': 20,
			'boolean': false,
		},
		'cache': {
			description: 'Get tests cases from cache file',
			'boolean': true,
			'default': false,
		},
		'filter': {
			description: 'Only run tests whose descriptions match given string',
		},
		'regex': {
			description: 'Only run tests whose descriptions match given regex',
			alias: ['regexp', 're'],
		},
		'run-disabled': {
			description: 'Run disabled tests',
			'default': false,
			'boolean': true,
		},
		'run-php': {
			description: 'Run php-only tests',
			'default': false,
			'boolean': true,
		},
		'maxtests': {
			description: 'Maximum number of tests to run',
			'boolean': false,
		},
		'quick': {
			description: 'Suppress diff output of failed tests',
			'boolean': true,
			'default': false,
		},
		'quiet': {
			description: 'Suppress notification of passed tests (shows only failed tests)',
			'boolean': true,
			'default': false,
		},
		'blacklist': {
			description: 'Compare against expected failures from blacklist',
			'default': true,
			'boolean': true,
		},
		'rewrite-blacklist': {
			description: 'Update parserTests-blacklist.json with failing tests.',
			'default': false,
			'boolean': true,
		},
		'exit-zero': {
			description: "Don't exit with nonzero status if failures are found.",
			'default': false,
			'boolean': true,
		},
		xml: {
			description: 'Print output in JUnit XML format.',
			'default': false,
			'boolean': true,
		},
		'exit-unexpected': {
			description: 'Exit after the first unexpected result.',
			'default': false,
			'boolean': true,
		},
		'update-tests': {
			description: 'Update parserTests.txt with results from wt2html fails.',
		},
		'update-unexpected': {
			description: 'Update parserTests.txt with results from wt2html unexpected fails.',
			'default': false,
			'boolean': true,
		},
	}, {
		// override defaults for standard options
		usePHPPreProcessor: false,
		fetchConfig: false,
	});

	return yargs
	.usage('Usage: $0 [options] [tests-file]')
	.options(standardOpts)
	.check(function(argv, aliases) {
		if (argv.filter === true) {
			throw "--filter needs an argument";
		}
		if (argv.regex === true) {
			throw "--regex needs an argument";
		}
		return true;
	})
	.strict();
};

TestUtils.prepareOptions = function() {
	var popts = getOpts();
	var options = popts.argv;

	if (options.help) {
		popts.showHelp();
		console.log("Additional dump options specific to parserTests script:");
		console.log("* dom:post-changes  : Dumps DOM after applying selser changetree\n");
		console.log("Examples");
		console.log("$ node parserTests --selser --filter '...' --dump dom:post-changes");
		console.log("$ node parserTests --selser --filter '...' --changetree '...' --dump dom:post-changes\n");
		process.exit(0);
	}

	ScriptUtils.setColorFlags(options);

	if (!(options.wt2wt || options.wt2html || options.html2wt || options.html2html || options.selser)) {
		options.wt2wt = true;
		options.wt2html = true;
		options.html2html = true;
		options.html2wt = true;
		if (ScriptUtils.booleanOption(options['rewrite-blacklist'])) {
			// turn on all modes by default for --rewrite-blacklist
			options.selser = true;
			// sanity checking (T53448 asks to be able to use --filter here)
			if (options.filter || options.regex || options.maxtests || options['exit-unexpected']) {
				console.log("\nERROR> can't combine --rewrite-blacklist with --filter, --maxtests or --exit-unexpected");
				process.exit(1);
			}
		}
	}

	if (options.xml) {
		options.reportResult = reportResultXML;
		options.reportStart = reportStartXML;
		options.reportSummary = reportSummaryXML;
		options.reportFailure = reportFailureXML;
		colors.mode = 'none';
	}

	if (typeof options.reportFailure !== 'function') {
		// default failure reporting is standard out,
		// see printFailure for documentation of the default.
		options.reportFailure = printFailure;
	}

	if (typeof options.reportSuccess !== 'function') {
		// default success reporting is standard out,
		// see printSuccess for documentation of the default.
		options.reportSuccess = printSuccess;
	}

	if (typeof options.reportStart !== 'function') {
		// default summary reporting is standard out,
		// see reportStart for documentation of the default.
		options.reportStart = reportStartOfTests;
	}

	if (typeof options.reportSummary !== 'function') {
		// default summary reporting is standard out,
		// see reportSummary for documentation of the default.
		options.reportSummary = reportSummary;
	}

	if (typeof options.reportResult !== 'function') {
		// default result reporting is standard out,
		// see printResult for documentation of the default.
		options.reportResult = (...args) => printResult(options.reportFailure, options.reportSuccess, ...args);
	}

	if (typeof options.getDiff !== 'function') {
		// this is the default for diff-getting, but it can be overridden
		// see doDiff for documentation of the default.
		options.getDiff = doDiff;
	}

	if (typeof options.getActualExpected !== 'function') {
		// this is the default for getting the actual and expected
		// outputs, but it can be overridden
		// see getActualExpected for documentation of the default.
		options.getActualExpected = getActualExpected;
	}

	options.modes = [];

	if (options.wt2html) {
		options.modes.push('wt2html');
	}
	if (options.wt2wt) {
		options.modes.push('wt2wt');
	}
	if (options.html2html) {
		options.modes.push('html2html');
	}
	if (options.html2wt) {
		options.modes.push('html2wt');
	}
	if (options.selser) {
		options.modes.push('selser');
	}

	return options;
};

// Hard-code some interwiki prefixes, as is done
// in parserTest.inc:setupInterwikis()
TestUtils.iwl = {
	local: {
		url: 'http://doesnt.matter.org/$1',
		localinterwiki: '',
	},
	wikipedia: {
		url: 'http://en.wikipedia.org/wiki/$1',
	},
	meatball: {
		// this has been updated in the live wikis, but the parser tests
		// expect the old value (as set in parserTest.inc:setupInterwikis())
		url: 'http://www.usemod.com/cgi-bin/mb.pl?$1',
	},
	memoryalpha: {
		url: 'http://www.memory-alpha.org/en/index.php/$1',
	},
	zh: {
		url: 'http://zh.wikipedia.org/wiki/$1',
		language: '\u4e2d\u6587',
		local: '',
	},
	es: {
		url: 'http://es.wikipedia.org/wiki/$1',
		language: 'espa\u00f1ol',
		local: '',
	},
	fr: {
		url: 'http://fr.wikipedia.org/wiki/$1',
		language: 'fran\u00e7ais',
		local: '',
	},
	ru: {
		url: 'http://ru.wikipedia.org/wiki/$1',
		language: '\u0440\u0443\u0441\u0441\u043a\u0438\u0439',
		local: '',
	},
	mi: {
		url: 'http://mi.wikipedia.org/wiki/$1',
		// better for testing if one of the
		// localinterwiki prefixes is also a
		// language
		language: 'Test',
		local: '',
		localinterwiki: '',
	},
	mul: {
		url: 'http://wikisource.org/wiki/$1',
		extralanglink: '',
		linktext: 'Multilingual',
		sitename: 'WikiSource',
		local: '',
	},
	// not in PHP setupInterwikis(), but needed
	en: {
		url: 'http://en.wikipedia.org/wiki/$1',
		language: 'English',
		local: '',
		protorel: '',
	},
	stats: {
		local: '',
		url: 'https://stats.wikimedia.org/$1'
	},
	gerrit: {
		local: '',
		url: 'https://gerrit.wikimedia.org/$1'
	}
};

TestUtils.addNamespace = function(wikiConf, name) {
	var nsid = name.id;
	var old = wikiConf.siteInfo.namespaces[nsid];
	if (old) {  // Id may already be defined; if so, clear it.
		if (old === name) { return; }  // ParserTests does a lot redundantly.
		wikiConf.namespaceIds.delete(Util.normalizeNamespaceName(old['*']));
		wikiConf.canonicalNamespaces[Util.normalizeNamespaceName(old.canonical ? old.canonical : old['*'])] = undefined;
	}
	wikiConf.namespaceNames[nsid] = name['*'];
	wikiConf.namespaceIds.set(Util.normalizeNamespaceName(name['*']), Number(nsid));
	wikiConf.canonicalNamespaces[Util.normalizeNamespaceName(name.canonical ? name.canonical : name['*'])] = Number(nsid);
	wikiConf.namespacesWithSubpages[nsid] = true;
	wikiConf.siteInfo.namespaces[nsid] = name;
};

if (typeof module === "object") {
	module.exports.TestUtils = TestUtils;
}
