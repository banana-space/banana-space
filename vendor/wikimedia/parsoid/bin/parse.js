#!/usr/bin/env node
/**
 * Command line parse utility.
 * Read from STDIN, write to STDOUT.
 */

'use strict';

require('../core-upgrade.js');

var fs = require('pn/fs');
var path = require('path');
var yargs = require('yargs');
var yaml = require('js-yaml');
var workerFarm = require('worker-farm');

var parseJsPath = require.resolve('../lib/parse.js');
var ContentUtils = require('../lib/utils/ContentUtils.js').ContentUtils;
var DOMDataUtils = require('../lib/utils/DOMDataUtils.js').DOMDataUtils;
var DOMUtils = require('../lib/utils/DOMUtils.js').DOMUtils;
var ScriptUtils = require('../tools/ScriptUtils.js').ScriptUtils;
var TestUtils = require('../tests/TestUtils.js').TestUtils;
var Promise = require('../lib/utils/promise.js');

// Get some default values to display in argument descriptions
var ParserEnvProto = require('../lib/config/MWParserEnvironment.js').MWParserEnvironment.prototype;

var standardOpts = ScriptUtils.addStandardOptions({
	'wt2html': {
		description: 'Wikitext -> HTML',
		'boolean': true,
		'default': false,
	},
	'html2wt': {
		description: 'HTML -> Wikitext',
		'boolean': true,
		'default': false,
	},
	'wt2wt': {
		description: 'Wikitext -> HTML -> Wikitext',
		'boolean': true,
		'default': false,
	},
	'html2html': {
		description: 'HTML -> Wikitext -> HTML',
		'boolean': true,
		'default': false,
	},
	'selser': {
		description: 'Use the selective serializer to go from HTML to Wikitext.',
		'boolean': true,
		'default': false,
	},
	'normalize': {
		description: 'Normalize the output as parserTests would do. Use --normalize for PHP tests, and --normalize=parsoid for parsoid-only tests',
	},
	'body_only': {
		description: 'Just return the body, without any normalizations as in --normalize',
		'boolean': true,
		'default': false,
	},
	'config': {
		description: "Path to a config.yaml file.  Use --config w/ no argument to default to the server's config.yaml",
		'default': false,
	},
	'oldtext': {
		description: 'The old page text for a selective-serialization (see --selser)',
		'boolean': false,
		'default': null,
	},
	'oldtextfile': {
		description: 'File containing the old page text for a selective-serialization (see --selser)',
		'boolean': false,
		'default': null,
	},
	'oldhtmlfile': {
		description: 'File containing the old HTML for a selective-serialization (see --selser)',
		'boolean': false,
		'default': null,
	},
	'domdiff': {
		description: 'File containing the diff-marked HTML for used with selective-serialization (see --selser)',
		'boolean': false,
		'default': null,
	},
	'inputfile': {
		description: 'File containing input as an alternative to stdin',
		'boolean': false,
		'default': false,
	},
	'pbin': {
		description: 'Input pagebundle JSON',
		'boolean': false,
		'default': '',
	},
	'pbinfile': {
		description: 'Input pagebundle JSON file',
		'boolean': false,
		'default': '',
	},
	'pboutfile': {
		description: 'Output pagebundle JSON to file',
		'boolean': false,
		'default': false,
	},
	'offline': {
		description: 'Shortcut to turn off various network fetches during parse.',
		'boolean': true,
		'default': false,
	},
	'record': {
		description: 'Record http requests for later replay',
		'boolean': true,
		'default': false,
	},
	'replay': {
		description: 'Replay recorded http requests for later replay',
		'boolean': true,
		'default': false,
	},
	'verbose': {
		description: 'Log at level "info" as well',
		'boolean': true,
		'default': false,
	},
	'useWorker': {
		description: 'Use a worker farm',
		'boolean': true,
		'default': false,
	},

	// These are ParsoidConfig properties

	'linting': {
		description: 'Parse with linter enabled',
		'boolean': true,
		'default': false,
	},
	'loadWMF': {
		description: 'Use WMF mediawiki API config',
		'boolean': true,
		'default': true,
	},

	// These are MWParserEnvironment properties

	'prefix': {
		description: 'Which wiki prefix to use; e.g. "enwiki" for English wikipedia, "eswiki" for Spanish, "mediawikiwiki" for mediawiki.org',
		'boolean': false,
		'default': null,
	},
	'domain': {
		description: 'Which wiki to use; e.g. "en.wikipedia.org" for English wikipedia, "es.wikipedia.org" for Spanish, "mediawiki.org" for mediawiki.org',
		'boolean': false,
		'default': null,
	},
	'oldid': {
		description: 'Oldid of the given page.',
		'boolean': false,
		'default': null,
	},
	'outputContentVersion': {
		description: 'The acceptable content version.',
		'boolean': false,
		'default': ParserEnvProto.outputContentVersion,
	},
	'pageName': {
		description: 'The page name, returned for {{PAGENAME}}. If no input is given (ie. empty/stdin closed), it downloads and parses the page. This should be the actual title of the article (that is, not including any URL-encoding that might be necessary in wikitext).',
		'boolean': false,
		'default': '',
	},
	'pageBundle': {
		description: 'Output pagebundle JSON',
		'boolean': true,
		'default': false,
	},
	'scrubWikitext': {
		description: 'Apply wikitext scrubbing while serializing.  This is also used for a mode of normalization (--normalize) applied when parsing.',
		'boolean': true,
		'default': false,
	},
	'contentmodel': {
		description: 'The content model of the input.  Defaults to "wikitext" but extensions may support others (for example, "json").',
		'boolean': false,
		'default': null,
	},
	'wrapSections': {
		description: 'Output <section> tags (default false)',
		'boolean': true,
		'default': false, // override the default in MWParserEnvironment.prototype since the wrappers are annoying in dev-mode
	},
});

Promise.async(function *() {
	var defaultModeStr = "Default conversion mode : --wt2html";

	var opts = yargs
	.usage('Usage: echo wikitext | $0 [options]\n\n' + defaultModeStr)
	.options(standardOpts)
	.strict();

	var argv = opts.parse(process.argv);

	if (ScriptUtils.booleanOption(argv.help)) {
		opts.showHelp();
		return;
	}

	var mode = ['selser', 'html2html', 'html2wt', 'wt2wt']
	.find(function(m) { return argv[m]; }) || 'wt2html';

	var selser;
	if (mode === 'selser') {
		selser = {
			oldtext: argv.oldtext,
		};
		if (argv.oldtextfile) {
			selser.oldtext = yield fs.readFile(argv.oldtextfile, 'utf8');
		}
		if (selser.oldtext === null) {
			throw new Error('Please provide original wikitext ' +
				'(--oldtext or --oldtextfile). Selser requires that.');
		}
		if (argv.oldhtmlfile) {
			selser.oldhtml = yield fs.readFile(argv.oldhtmlfile, 'utf8');
		}
		if (argv.domdiff) {
			selser.domdiff = yield fs.readFile(argv.domdiff, 'utf8');
		}
	}

	var pb;
	if (argv.pbin.length > 0) {
		pb = JSON.parse(argv.pbin);
	} else if (argv.pbinfile) {
		pb = JSON.parse(yield fs.readFile(argv.pbinfile, 'utf8'));
	}

	var prefix = argv.prefix || null;
	var domain = argv.domain || null;

	if (argv.apiURL) {
		prefix = 'customwiki';
		domain = null;
	} else if (!(prefix || domain)) {
		domain = 'en.wikipedia.org';
	}

	var parsoidOptions = {
		linting: argv.linting,
		loadWMF: argv.loadWMF,
		useWorker: argv.useWorker,
	};

	if (ScriptUtils.booleanOption(argv.config)) {
		var p = (typeof (argv.config) === 'string') ?
			path.resolve('.', argv.config) :
			path.resolve(__dirname, '../config.yaml');
		// Assuming Parsoid is the first service in the list
		parsoidOptions = yaml.load(yield fs.readFile(p, 'utf8')).services[0].conf;
	}

	ScriptUtils.setTemplatingAndProcessingFlags(parsoidOptions, argv);
	ScriptUtils.setDebuggingFlags(parsoidOptions, argv);

	// Offline shortcut
	if (argv.offline) {
		parsoidOptions.fetchConfig = false;
		parsoidOptions.fetchTemplates = false;
		parsoidOptions.fetchImageInfo = false;
		parsoidOptions.usePHPPreProcessor = false;
		parsoidOptions.expandExtensions = false;
	}

	if (parsoidOptions.localsettings) {
		parsoidOptions.localsettings = path.resolve(__dirname, parsoidOptions.localsettings);
	}

	var nock, dir, nocksFile;
	if (argv.record || argv.replay) {
		if (!argv.pageName) {
			throw new Error(
				'pageName must be specified to use --record or --replay'
			);
		}
		dir = path.resolve(__dirname, '../nocks/');
		if (!(yield fs.exists(dir))) {
			yield fs.mkdir(dir);
		}
		dir = dir + '/' + (domain || prefix || 'enwiki');
		if (!(yield fs.exists(dir))) {
			yield fs.mkdir(dir);
		}
		nocksFile = dir + '/' + encodeURIComponent(argv.pageName || 'stdin') + '.js';
		if (argv.record) {
			nock = require('nock');
			nock.recorder.rec({ dont_print: true });
		} else {
			require(nocksFile);
		}
	}

	var logLevels;
	if (!argv.verbose) {
		logLevels = ["fatal", "error", "warn"];
	}

	var envOptions = {
		domain: domain,
		prefix: prefix,
		pageName: argv.pageName,
		scrubWikitext: argv.scrubWikitext,
		pageBundle: argv.pageBundle || argv.pboutfile,
		wrapSections: argv.wrapSections,
		logLevels: logLevels,
	};

	var input = yield Promise.resolve()
	.then(function() {
		if (argv.inputfile) {
			// read input from the file, then process
			return fs.readFile(argv.inputfile, 'utf8');
		}

		// Send a message to stderr if there is no input for a while, since the
		// convention that --pageName must be used with </dev/null is confusing.
		// Note: To run code in WebStorm where </dev/null is not possible to set,
		// and WebStorm hangs on waiting for stdin, comment out this block of code temporarily
		var stdinTimer = setTimeout(function() {
			console.error('Waiting for stdin...');
		}, 1000);

		return new Promise(function(resolve) {
			// collect input
			var inputChunks = [];
			var stdin = process.stdin;
			stdin.resume();
			stdin.setEncoding('utf8');
			stdin.on('data', function(chunk) {
				inputChunks.push(chunk);
			});
			stdin.on('end', function() {
				resolve(inputChunks);
			});
		})
		.then(function(inputChunks) {
			clearTimeout(stdinTimer);
			// parse page if no input
			if (inputChunks.length > 0) {
				return inputChunks.join('');
			} else if (argv.html2wt || argv.html2html) {
				throw new Error(
					'Fetching page content is only supported when starting at wikitext.'
				);
			}
		});
	});
	var obj = {
		input: input,
		mode: mode,
		parsoidOptions: parsoidOptions,
		envOptions: envOptions,
		oldid: argv.oldid,
		selser: selser,
		pb: pb,
		contentmodel: argv.contentmodel,
		outputContentVersion: argv.outputContentVersion,
		body_only: argv.body_only,
	};
	var out;
	if (parsoidOptions.useWorker) {
		var farmOptions = {
			maxConcurrentWorkers: 1,
			maxConcurrentCallsPerWorker: 1,
			maxCallTime: 2 * 60 * 1000,
			maxRetries: 0,
			autoStart: true,
		};
		var workers = workerFarm(farmOptions, parseJsPath);
		var promiseWorkers = Promise.promisify(workers);
		out = yield promiseWorkers(obj)
			.finally(function() {
				workerFarm.end(workers);
			});
	} else {
		out = yield require(parseJsPath)(obj);
	}
	var str;
	if (['wt2html', 'html2html'].includes(mode)) {
		var html = out.html;
		var doc;
		if (argv.pboutfile) {
			yield fs.writeFile(argv.pboutfile, JSON.stringify(out.pb), 'utf8');
		} else if (argv.pageBundle) {
			// Stitch this back in, even though it was just extracted
			doc = DOMUtils.parseHTML(html);
			DOMDataUtils.injectPageBundle(doc, out.pb);
			html = ContentUtils.toXML(doc);
		}
		if (argv.normalize) {
			str = TestUtils.normalizeOut(html, {
				parsoidOnly: (argv.normalize === 'parsoid'),
				scrubWikitext: argv.scrubWikitext,
			});
		} else {
			str = html;
		}
	} else {
		str = out.wt;
	}
	var stdout = process.stdout;
	stdout.write(str);
	if (stdout.isTTY) {
		stdout.write('\n');
	}
	if (argv.record) {
		var nockCalls = nock.recorder.play();
		yield fs.writeFile(
			nocksFile,
			"var nock = require('nock');\n" + nockCalls.join('\n'),
			'utf8'
		);
	}
})().done();
