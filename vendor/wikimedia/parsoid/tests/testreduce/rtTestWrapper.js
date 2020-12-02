'use strict';

const fs = require('fs');
const path = require('path');

/* This is a wrapper around the roundtrip testing script */
const rtTest = require('../../bin/roundtrip-test.js');
const yaml = require('js-yaml');

// If we've started a Parsoid server, cache the URL so that subsequent calls
// don't need to start their own.
let parsoidURLOpts = null;

let htmlDiffConfig = null;

// Read ids from a file and return the first line of the file
function getTestRunId(opts) {
	const testRunIdFile = opts.testRunIdFile || path.resolve(__dirname, 'parsoid.rt-test.ids');
	return fs.readFileSync(testRunIdFile, 'utf-8').split('\n')[0];
}

function _run(test) {
	return rtTest.runTests(test.title, {
		prefix: test.prefix,
		rtTestMode: true,
		parsoidURLOpts: parsoidURLOpts,
		htmlDiffConfig: htmlDiffConfig
	}, rtTest.xmlFormat).then(function(result) {
		return result.output;
	});
}

function runRoundTripTest(config, test) {
	if (!parsoidURLOpts) {
		parsoidURLOpts = config.parsoidPHP;
		const configFile = path.resolve(__dirname, './htmldiffs.config.yaml');
		if (fs.existsSync(configFile)) {
			htmlDiffConfig = yaml.load(fs.readFileSync(configFile, 'utf8'));
		}
	}
	return _run(test);
}

if (typeof module === 'object') {
	module.exports.runRoundTripTest = runRoundTripTest;
	module.exports.getTestRunId = getTestRunId;
}
