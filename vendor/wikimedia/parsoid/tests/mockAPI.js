// This file is used to run a stub API that mimics the MediaWiki interface
// for the purposes of testing extension expansion.

'use strict';

require('../core-upgrade.js');

var fs = require('fs');
var yaml = require('js-yaml');
var path = require('path');
var express = require('express');
var crypto = require('crypto');
var busboy = require('connect-busboy');

var Promise = require('../lib/utils/promise.js');

// Get Parsoid limits.
var optionsPath = path.resolve(__dirname, './mocha/test.config.yaml');
var optionsYaml = fs.readFileSync(optionsPath, 'utf8');
var parsoidOptions = yaml.load(optionsYaml).services[0].conf;

// configuration to match PHP parserTests
var IMAGE_BASE_URL = 'http://example.com/images';
var IMAGE_DESC_URL = IMAGE_BASE_URL;
// IMAGE_BASE_URL='http://upload.wikimedia.org/wikipedia/commons';
// IMAGE_DESC_URL='http://commons.wikimedia.org/wiki';
var FILE_PROPS = {
	'Foobar.jpg': {
		size: 7881,
		width: 1941,
		height: 220,
		bits: 8,
		mime: 'image/jpeg',
	},
	'Thumb.png': {
		size: 22589,
		width: 135,
		height: 135,
		bits: 8,
		mime: 'image/png',
	},
	'Foobar.svg': {
		size: 12345,
		width: 240,
		height: 180,
		bits: 24,
		mime: 'image/svg+xml',
	},
	'LoremIpsum.djvu': {
		size: 3249,
		width: 2480,
		height: 3508,
		bits: 8,
		mime: 'image/vnd.djvu',
	},
	'Video.ogv': {
		size: 12345,
		width: 320,
		height: 240,
		bits: 0,
		duration: 160.733333333333,
		mime: 'application/ogg',
		mediatype: 'VIDEO',
	},
	'Audio.oga': {
		size: 12345,
		width: 0,
		height: 0,
		bits: 0,
		duration: 160.733333333333,
		mime: 'application/ogg',
		mediatype: 'AUDIO',
	},
};

/* -------------------- web app access points below --------------------- */

var app = express();

// application/x-www-form-urlencoded
// multipart/form-data
app.use(busboy({
	limits: {
		fields: 10,
		fieldSize: 15 * 1024 * 1024,
	},
}));
app.use(function(req, res, next) {
	req.body = req.body || {};
	if (!req.busboy) {
		return next();
	}
	req.busboy.on('field', function(field, val) {
		req.body[field] = val;
	});
	req.busboy.on('finish', function() {
		next();
	});
	req.pipe(req.busboy);
});

var mainPage = {
	query: {
		pages: {
			'1': {
				pageid: 1,
				ns: 0,
				title: 'Main Page',
				revisions: [
					{
						revid: 1,
						parentid: 0,
						slots: {
							main: {
								contentmodel: 'wikitext',
								contentformat: 'text/x-wiki',
								'*': '<strong>MediaWiki has been successfully installed.</strong>\n\nConsult the [//meta.wikimedia.org/wiki/Help:Contents User\'s Guide] for information on using the wiki software.\n\n== Getting started ==\n* [//www.mediawiki.org/wiki/Special:MyLanguage/Manual:Configuration_settings Configuration settings list]\n* [//www.mediawiki.org/wiki/Special:MyLanguage/Manual:FAQ MediaWiki FAQ]\n* [https://lists.wikimedia.org/mailman/listinfo/mediawiki-announce MediaWiki release mailing list]\n* [//www.mediawiki.org/wiki/Special:MyLanguage/Localisation#Translation_resources Localise MediaWiki for your language]',
							},
						},
					},
				],
			},
		},
	},
};

// Old response structure, pre-mcr
var oldResponse = {
	query: {
		pages: {
			'999': {
				pageid: 999,
				ns: 0,
				title: 'Old Response',
				revisions: [
					{
						revid: 999,
						parentid: 0,
						contentmodel: 'wikitext',
						contentformat: 'text/x-wiki',
						'*': '<strong>MediaWiki was successfully installed.</strong>\n\nConsult the [//meta.wikimedia.org/wiki/Help:Contents User\'s Guide] for information on using the wiki software.\n\n== Getting started ==\n* [//www.mediawiki.org/wiki/Special:MyLanguage/Manual:Configuration_settings Configuration settings list]\n* [//www.mediawiki.org/wiki/Special:MyLanguage/Manual:FAQ MediaWiki FAQ]\n* [https://lists.wikimedia.org/mailman/listinfo/mediawiki-announce MediaWiki release mailing list]\n* [//www.mediawiki.org/wiki/Special:MyLanguage/Localisation#Translation_resources Localise MediaWiki for your language]',
					},
				],
			},
		},
	},
};

var junkPage = {
	query: {
		pages: {
			'2': {
				pageid: 2,
				ns: 0,
				title: "Junk Page",
				revisions: [
					{
						revid: 2,
						parentid: 0,
						slots: {
							main: {
								contentmodel: 'wikitext',
								contentformat: 'text/x-wiki',
								'*': '2. This is just some junk. See the comment above.',
							},
						},
					},
				],
			},
		},
	},
};

var largePage = {
	query: {
		pages: {
			'3': {
				pageid: 3,
				ns: 0,
				title: 'Large_Page',
				revisions: [
					{
						revid: 3,
						parentid: 0,
						slots: {
							main: {
								contentmodel: 'wikitext',
								contentformat: 'text/x-wiki',
								'*': 'a'.repeat(parsoidOptions.limits.wt2html.maxWikitextSize + 1),
							},
						},
					},
				],
			},
		},
	},
};

var reusePage = {
	query: {
		pages: {
			'100': {
				pageid: 100,
				ns: 0,
				title: 'Reuse_Page',
				revisions: [
					{
						revid: 100,
						parentid: 0,
						slots: {
							main: {
								contentmodel: 'wikitext',
								contentformat: 'text/x-wiki',
								'*': '{{colours of the rainbow}}',
							},
						},
					},
				],
			},
		},
	},
};

var jsonPage = {
	query: {
		pages: {
			'101': {
				pageid: 101,
				ns: 0,
				title: 'JSON_Page',
				revisions: [
					{
						revid: 101,
						parentid: 0,
						slots: {
							main: {
								contentmodel: 'json',
								contentformat: 'text/json',
								'*': '[1]',
							},
						},
					},
				],
			},
		},
	},
};

var lintPage = {
	query: {
		pages: {
			'102': {
				pageid: 102,
				ns: 0,
				title: "Lint Page",
				revisions: [
					{
						revid: 102,
						parentid: 0,
						slots: {
							main: {
								contentmodel: 'wikitext',
								contentformat: 'text/x-wiki',
								'*': '{|\nhi\n|ho\n|}',
							},
						},
					},
				],
			},
		},
	},
};

var redlinksPage = {
	query: {
		pages: {
			'103': {
				pageid: 103,
				ns: 0,
				title: "Redlinks Page",
				revisions: [
					{
						revid: 103,
						parentid: 0,
						slots: {
							main: {
								contentmodel: 'wikitext',
								contentformat: 'text/x-wiki',
								'*': '[[Special:Version]] [[Doesnotexist]] [[Redirected]]',
							},
						},
					},
				],
			},
		},
	},
};

var variantPage = {
	query: {
		pages: {
			'104': {
				pageid: 104,
				ns: 0,
				title: "Variant Page",
				revisions: [
					{
						revid: 104,
						parentid: 0,
						slots: {
							main: {
								contentmodel: 'wikitext',
								contentformat: 'text/x-wiki',
								'*': 'абвг abcd',
							},
						},
					},
				],
				pagelanguage: 'sr',
				pagelanguagedir: 'ltr',
			},
		},
	},
};

var noVariantPage = {
	query: {
		pages: {
			'105': {
				pageid: 105,
				ns: 0,
				title: "No Variant Page",
				revisions: [
					{
						revid: 105,
						parentid: 0,
						slots: {
							main: {
								contentmodel: 'wikitext',
								contentformat: 'text/x-wiki',
								'*': 'абвг abcd\n__NOCONTENTCONVERT__',
							},
						},
					},
				],
				pagelanguage: 'sr',
				pagelanguagedir: 'ltr',
			},
		},
	},
};

var revisionPage = {
	query: {
		pages: {
			'63': {
				pageid: 63,
				ns: 0,
				title: 'Revision ID',
				revisions: [
					{
						revid: 63,
						parentid: 0,
						slots: {
							main: {
								contentmodel: 'wikitext',
								contentformat: 'text/x-wiki',
								'*': '{{REVISIONID}}',
							},
						},
					},
				],
			},
		},
	},
};

var fnames = {
	'Image:Foobar.jpg': 'Foobar.jpg',
	'File:Foobar.jpg': 'Foobar.jpg',
	'Archivo:Foobar.jpg': 'Foobar.jpg',
	'Mynd:Foobar.jpg': 'Foobar.jpg',
	'Датотека:Foobar.jpg': 'Foobar.jpg',
	'Image:Foobar.svg': 'Foobar.svg',
	'File:Foobar.svg': 'Foobar.svg',
	'Image:Thumb.png': 'Thumb.png',
	'File:Thumb.png': 'Thumb.png',
	'File:LoremIpsum.djvu': 'LoremIpsum.djvu',
	'File:Video.ogv': 'Video.ogv',
	'File:Audio.oga': 'Audio.oga',
};

var pnames = {
	'Image:Foobar.jpg': 'File:Foobar.jpg',
	'Image:Foobar.svg': 'File:Foobar.svg',
	'Image:Thumb.png': 'File:Thumb.png',
};

// This templatedata description only provides a subset of fields
// that mediawiki API returns. Parsoid only uses the format and
// paramOrder fields at this point, so keeping these lean.
var templateData = {
	'Template:NoFormatWithParamOrder': {
		'paramOrder': ['f0', 'f1', 'unused2', 'f2', 'unused3'],
	},
	'Template:InlineTplNoParamOrder': {
		'format': 'inline',
	},
	'Template:BlockTplNoParamOrder': {
		'format': 'block',
	},
	'Template:InlineTplWithParamOrder': {
		'format': 'inline',
		'paramOrder': ['f1','f2'],
	},
	'Template:BlockTplWithParamOrder': {
		'format': 'block',
		'paramOrder': ['f1','f2'],
	},
	'Template:WithParamOrderAndAliases': {
		'params': {
			'f1': { 'aliases': ['f4','f3'] }
		},
		'paramOrder': ['f1','f2'],
	},
	'Template:InlineFormattedTpl_1': {
		'format': '{{_|_=_}}',
	},
	'Template:InlineFormattedTpl_2': {
		'format': '\n{{_ | _ = _}}',
	},
	'Template:InlineFormattedTpl_3': {
		'format': '{{_| _____ = _}}',
	},
	'Template:BlockFormattedTpl_1': {
		'format': '{{_\n| _ = _\n}}',
	},
	'Template:BlockFormattedTpl_2': {
		'format': '\n{{_\n| _ = _\n}}\n',
	},
	'Template:BlockFormattedTpl_3': {
		'format': '{{_|\n _____ = _}}',
	},
};

var formatters = {
	json: function(data) {
		return JSON.stringify(data);
	},
	jsonfm: function(data) {
		return JSON.stringify(data, null, 2);
	},
};

var preProcess = function(text, revid, formatversion) {
	var match = text.match(/{{1x\|(.*?)}}/);
	if (match) {
		return { wikitext: match[1] };
	} else if (text === '{{colours of the rainbow}}') {
		return { wikitext: 'purple' };
	} else if (text === '{{REVISIONID}}') {
		return { wikitext: String(revid) };
	} else {
		return null;
	}
};

var imageInfo = function(filename, twidth, theight, useBatchAPI) {
	var normPagename = pnames[filename] || filename;
	var normFilename = fnames[filename] || filename;
	if (!(normFilename in FILE_PROPS)) {
		return null;
	}
	var props = FILE_PROPS[normFilename] || Object.create(null);
	var md5 = crypto.createHash('md5').update(normFilename).digest('hex');
	var md5prefix = md5[0] + '/' + md5[0] + md5[1] + '/';
	var baseurl = IMAGE_BASE_URL + '/' + md5prefix + normFilename;
	var height = props.hasOwnProperty('height') ? props.height : 220;
	var width = props.hasOwnProperty('width') ? props.width : 1941;
	var turl = IMAGE_BASE_URL + '/thumb/' + md5prefix + normFilename;
	var durl = IMAGE_DESC_URL + '/' + normFilename;
	var mediatype = props.mediatype ||
			(props.mime === 'image/svg+xml' ? 'DRAWING' : 'BITMAP');
	var result = {
		size: props.size || 12345,
		height: height,
		width: width,
		url: baseurl,
		descriptionurl: durl,
		mediatype: mediatype,
		mime: props.mime,
	};
	if (props.hasOwnProperty('duration')) {
		result.duration = props.duration;
	}
	// The batch api always generates thumbs, as does the videoinfo handler
	if ((useBatchAPI || result.mediatype === 'VIDEO') &&
			(theight === undefined || theight === null) &&
			(twidth === undefined || twidth === null)) {
		twidth = width;
		theight = height;
	}
	if ((theight !== undefined && theight !== null) ||
			(twidth !== undefined && twidth !== null)) {
		if (twidth && (theight === undefined || theight === null)) {
			// File::scaleHeight in PHP
			theight = Math.round(height * twidth / width);
		} else if (theight && (twidth === undefined || twidth === null)) {
			// MediaHandler::fitBoxWidth in PHP
			// This is crazy!
			var idealWidth = width * theight / height;
			var roundedUp = Math.ceil(idealWidth);
			if (Math.round(roundedUp * height / width) > theight) {
				twidth = Math.floor(idealWidth);
			} else {
				twidth = roundedUp;
			}
		} else {
			if (Math.round(height * twidth / width) > theight) {
				twidth = Math.ceil(width * theight / height);
			} else {
				theight = Math.round(height * twidth / width);
			}
		}
		console.assert(typeof (twidth) === 'number');
		var urlWidth = twidth;
		if (twidth > width) {
			// The PHP api won't enlarge a bitmap ... but the batch api will.
			// But, to match the PHP sections, don't scale.
			if (mediatype !== 'DRAWING') {
				urlWidth = width;
			}
		}
		if (urlWidth !== width || ['AUDIO', 'VIDEO'].includes(mediatype)) {
			turl += '/' + urlWidth + 'px-' + normFilename;
			switch (mediatype) {
				case 'AUDIO':
					// No thumbs are generated for audio
					turl = IMAGE_BASE_URL + '/w/resources/assets/file-type-icons/fileicon-ogg.png';
					break;
				case 'VIDEO':
					turl += '.jpg';
					break;
				case 'DRAWING':
					turl += '.png';
					break;
			}
		} else {
			turl = baseurl;
		}
		result.thumbwidth = twidth;
		result.thumbheight = theight;
		result.thumburl = turl;
	}
	return {
		result: result,
		normPagename: normPagename,
	};
};

var querySiteinfo = function(prefix, formatversion, cb) {
	cb(null, require(`../baseconfig/${formatversion === 2 ? '2/' : ''}${prefix}.json`));
};

var parse = function(text, onlypst, formatversion) {
	var fmt = (text) => {
		return { text: (formatversion === 2) ? text : { "*": text } };
	};
	// We're performing a subst
	if (onlypst) {
		return fmt(text.replace(/\{\{subst:1x\|([^}]+)\}\}/, "$1"));
	}
	// Render to html the contents of known extension tags
	var match = text.match(/<([A-Za-z][^\t\n\v />\0]*)/);
	switch ((match && match[1]) || '') {
		// FIXME: this isn't really used by the mocha tests
		// since some mocha tests hit the production db, but
		// when we fix that, they should go through this.
		case 'templatestyles':
			return fmt("<style data-mw-deduplicate='TemplateStyles:r123456'>small { font-size: 120% } big { font-size: 80% }</style>"); // Silliness
		case 'translate':
			return fmt(text);
		case 'indicator':
		case 'section':
			return fmt('\n');
		default:
			throw new Error("Unhandled extension type encountered in: " + text);
	}
};

var missingTitles = new Set([
	'Doesnotexist',
]);

var specialTitles = new Set([
	'Special:Version',
]);

var redirectTitles = new Set([
	'Redirected',
]);

var disambigTitles = new Set([
	'Disambiguation',
]);

var pageProps = function(titles) {
	if (!Array.isArray(titles)) { return null; }
	return titles.map(function(t) {
		var props = { title: t };
		if (missingTitles.has(t)) { props.missing = true; }
		if (specialTitles.has(t)) { props.special = true; }
		if (redirectTitles.has(t)) { props.redirect = true; }
		if (disambigTitles.has(t)) {
			props.pageprops = { disambiguation: true };
		}
		return props;
	});
};

const fv2Queries = new Map();

var availableActions = {
	parse: function(prefix, body, cb) {
		var formatversion = +(body.formatversion || 1);
		var result = parse(body.text, body.onlypst, formatversion);
		cb(null, { parse: result });
	},

	query: function(prefix, body, cb) {
		var formatversion = +(body.formatversion || 1);
		if (body.meta === 'siteinfo') {
			return querySiteinfo(prefix, formatversion, cb);
		} else if (body.prop === "info|pageprops") {
			console.assert(formatversion === 2);
			return cb(null, {
				query: {
					pages: pageProps(body.titles.split('|')),
				},
			});
		}
		const title = (body.titles || '').replace(/_/g, ' ');
		const revid = body.revids;
		if (body.prop === "info|revisions") {
			let query = null;
			if (revid === "1" || title === "Main Page") {
				query = mainPage;
			} else if (revid === "2" || title === "Junk Page") {
				query = junkPage;
			} else if (revid === '3' || title === 'Large Page') {
				query = largePage;
			} else if (revid === '63' || title === 'Revision ID') {
				query = revisionPage;
			} else if (revid === '100' || title === 'Reuse Page') {
				query = reusePage;
			} else if (revid === '101' || title === 'JSON Page') {
				query = jsonPage;
			} else if (revid === '102' || title === 'Lint Page') {
				query = lintPage;
			} else if (revid === '103' || title === 'Redlinks Page') {
				query = redlinksPage;
			} else if (revid === '104' || title === 'Variant Page') {
				query = variantPage;
			} else if (revid === '105' || title === 'No Variant Page') {
				query = noVariantPage;
			} else if (revid === '999' || title === 'Old Response') {
				query = oldResponse;
			} else {
				query = {
					query: {
						pages: {
							'-1': {
								ns: 6,
								title: title,
								missing: '',
								imagerepository: '',
							},
						}
					}
				};
			}
			if (formatversion === 2) {
				if (!fv2Queries.has(query)) {
					const clone = JSON.parse(JSON.stringify(query));
					clone.query.pages = Object.keys(clone.query.pages).reduce((ps, k) => {
						const page = clone.query.pages[k];
						if (Array.isArray(page.revisions)) {
							page.revisions[0].slots.main = Object.assign(
								{},
								page.revisions[0].slots.main,
								{
									'*': undefined,
									'content': page.revisions[0].slots.main['*'],
								}
							);
							page.pagelanguage = page.pagelanguage || 'en';
							page.pagelanguagedir = page.pagelanguagedir || 'ltr';
						} else {
							page.missing = true;
						}
						ps.push(page);
						return ps;
					}, []);
					fv2Queries.set(query, clone);
				}
				query = fv2Queries.get(query);
			}
			return cb(null, query);
		}
		if (body.prop === 'imageinfo') {
			var response = { query: { } };
			var filename = body.titles;
			var tonum = (x) => {
				return (x === null || x === undefined) ? undefined : (+x);
			};
			var ii = imageInfo(filename, tonum(body.iiurlwidth), tonum(body.iiurlheight), false);
			var p;
			if (ii === null) {
				p = {
					ns: 6,
					title: filename,
					missing: '',
					imagerepository: '',
					imageinfo: [{
						size: 0,
						width: 0,
						height: 0,
						filemissing: '',
						mime: null,
						mediatype: null
					}],
				};
				if (formatversion === 2) {
					p.missing = p.imageinfo.filemissing = true;
					p.badfile = false;
				}
			} else {
				if (filename !== ii.normPagename) {
					response.query.normalized = [{ from: filename, to: ii.normPagename }];
				}
				p = {
					pageid: 1,
					ns: 6,
					title: ii.normPagename,
					imageinfo: [ii.result],
				};
				if (formatversion === 2) {
					p.badfile = false;
				}
			}
			if (formatversion === 2) {
				response.query.pages = [ p ];
			} else {
				response.query.pages = { };
				response.query.pages[p.pageid || '-1'] = p;
			}
			return cb(null, response);
		}
		return cb(new Error('Uh oh!'));
	},

	expandtemplates: function(prefix, body, cb) {
		var formatversion = +(body.formatversion || 1);
		var res = preProcess(body.text, body.revid, formatversion);
		if (res === null) {
			cb(new Error('Sorry!'));
		} else {
			cb(null, { expandtemplates: res });
		}
	},

	'parsoid-batch': function(prefix, body, cb) {
		var formatversion = +(body.formatversion || 1);
		var batch;
		try {
			batch = JSON.parse(body.batch);
			console.assert(Array.isArray(batch));
		} catch (e) {
			return cb(e);
		}
		var errs = [];
		var results = batch.map(function(b) {
			var res = null;
			switch (b.action) {
				case 'preprocess':
					res = preProcess(b.text, b.revid, formatversion);
					break;
				case 'imageinfo':
					var txopts = b.txopts || {};
					var ii = imageInfo('File:' + b.filename, txopts.width, txopts.height, true);
					// NOTE: Return early here since a null is acceptable.
					return (ii !== null) ? ii.result : null;
				case 'parse':
					res = parse(b.text, /* onlypst*/false, formatversion);
					break;
				case 'pageprops':
					res = pageProps(b.titles);
					break;
			}
			if (res === null) { errs.push(b); }
			return res;
		});
		var err = (errs.length > 0) ? new Error(JSON.stringify(errs)) : null;
		cb(err, { 'parsoid-batch': results });
	},

	// Return a dummy response
	templatedata: function(prefix, body, cb) {
		cb(null, {
			// FIXME: Assumes that body.titles is a single title
			// (which is how Parsoid uses this endpoint right now).
			'pages': {
				'1': templateData[body.titles] || {},
			},
		});
	},

	paraminfo: function(prefix, body, cb) {
		cb(null, { /* Just don't 400 for now. */ });
	},
};

var actionDefinitions = {
	parse: {
		parameters: {
			text: 'text',
			title: 'text',
			onlypst: 'boolean',
		},
	},
	query: {
		parameters: {
			titles: 'text',
			prop: 'text',
			iiprop: 'text',
			iiurlwidth: 'text',
			iiurlheight: 'text',
		},
	},
};

var actionRegex = Object.keys(availableActions).join('|');

function buildOptions(options) {
	var optStr = '';
	for (var i = 0; i < options.length; i++) {
		optStr += '<option value="' + options[i] + '">' + options[i] + '</option>';
	}
	return optStr;
}

function buildActionList() {
	var actions = Object.keys(availableActions);
	var setStr = '';
	for (var i = 0; i < actions.length; i++) {
		var action = actions[i];
		var title = 'action=' + action;
		setStr += '<li id="' + title + '">';
		setStr += '<a href="/' + action + '">' + title + '</a></li>';
	}
	return setStr;
}

function buildForm(action) {
	var formStr = '';
	var actionDef = actionDefinitions[action];
	var params = actionDef.parameters;
	var paramList = Object.keys(params);

	for (var i = 0; i < paramList.length; i++) {
		var param = paramList[i];
		if (typeof params[param] === 'string') {
			formStr += '<input type="' + params[param] + '" name="' + param + '" />';
		} else if (params[param].length) {
			formStr += '<select name="' + param + '">';
			formStr += buildOptions(params[param]);
			formStr += '</select>';
		}
	}
	return formStr;
}

// GET request to root....should probably just tell the client how to use the service
app.get('/', function(req, res) {
	res.setHeader('Content-Type', 'text/html; charset=UTF-8');
	res.write(
		'<html><body>' +
			'<ul id="list-of-actions">' +
				buildActionList() +
			'</ul>' +
		'</body></html>');
	res.end();
});

// GET requests for any possible actions....tell the client how to use the action
app.get(new RegExp('^/(' + actionRegex + ')'), function(req, res) {
	var formats = buildOptions(Object.keys(formatters));
	var action = req.params[0];
	var returnHtml =
			'<form id="service-form" method="GET" action="api.php">' +
				'<h2>GET form</h2>' +
				'<input name="action" type="hidden" value="' + action + '" />' +
				'<select name="format">' +
					formats +
				'</select>' +
				buildForm(action) +
				'<input type="submit" />' +
			'</form>' +
			'<form id="service-form" method="POST" action="api.php">' +
				'<h2>POST form</h2>' +
				'<input name="action" type="hidden" value="' + action + '" />' +
				'<select name="format">' +
					formats +
				'</select>' +
				buildForm(action) +
				'<input type="submit" />' +
			'</form>';

	res.setHeader('Content-Type', 'text/html; charset=UTF-8');
	res.write(returnHtml);
	res.end();
});

function handleApiRequest(prefix, body, res) {
	var format = body.format;
	var action = body.action;
	var formatter = formatters[format || "json"];

	if (!availableActions.hasOwnProperty(action)) {
		return res.status(400).end("Unknown action.");
	}

	availableActions[action](prefix, body, function(err, data) {
		if (err === null) {
			res.setHeader('Content-Type', 'application/json');
			res.write(formatter(data));
			res.end();
		} else {
			res.setHeader('Content-Type', 'text/plain');
			res.status(err.httpStatus || 500);
			res.write(err.stack || err.toString());
			res.end();
		}
	});
}

// GET request to api.php....actually perform an API request
app.get('/:prefix/api.php', function(req, res) {
	handleApiRequest(req.params.prefix, req.query, res);
});
app.get('/api.php', function(req, res) {
	handleApiRequest('enwiki', req.query, res);
});

// POST request to api.php....actually perform an API request
app.post('/:prefix/api.php', function(req, res) {
	handleApiRequest(req.params.prefix, req.body, res);
});
app.post('/api.php', function(req, res) {
	handleApiRequest('enwiki', req.body, res);
});

const start = function(options) {
	var logger = options.logger;
	var server;
	return new Promise(function(resolve, reject) {
		app.on('error', function(err) {
			logger.log('error', err);
			reject(err);
		});
		server = app.listen(options.config.port, options.config.iface, resolve);
	})
	.then(function() {
		var port = server.address().port;
		logger.log('info', 'Mock MediaWiki API: Started on ' + port);
		return {
			close: function() {
				return Promise.promisify(server.close, false, server)();
			},
			port: port,
		};
	});
};

if (require.main === module) {
	start({
		config: { port: process.env.MOCKPORT || 0 },
		logger: { log: function(...args) { console.log(...args); } },
	})
	.catch((e) => { console.error(e); });
} else {
	module.exports = start;
}
