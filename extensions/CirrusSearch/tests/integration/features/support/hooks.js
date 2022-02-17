'use strict';

const { After, Before } = require( 'cucumber' );
const Promise = require( 'bluebird' );
const MWBot = require( 'mwbot' );
const fs = require( 'fs' );
const path = require( 'path' );
const articlePath = path.dirname( path.dirname( path.dirname( __dirname ) ) ) + '/integration/articles/';

const BeforeOnce = function ( options, fn ) {
	Before( options, Promise.coroutine( function* () {
		const response = yield this.tags.check( options.tags );
		if ( response === 'complete' ) {
			return;
		} else if ( response === 'new' ) {
			try {
				yield fn.call( this );
			} catch ( err ) {
				console.log( `Failed initializing tag ${options.tags}: `, err );
				yield this.tags.reject( options.tags );
				return;
			}
			yield this.tags.complete( options.tags );
		} else if ( response === 'reject' ) {
			throw new Error( 'Tag failed to initialize previously' );
		} else {
			throw new Error( 'Unknown tag check response: ' + response );
		}
	} ) );
};

const articleText = ( fileName ) => fs.readFileSync( articlePath + fileName ).toString();

const waitForBatch = Promise.coroutine( function* ( wiki, batchJobs ) {
	const stepHelpers = this.stepHelpers.onWiki( wiki );
	const queue = [];
	if ( Array.isArray( batchJobs ) ) {
		for ( const job of batchJobs ) {
			queue.push( [ job[ 0 ], job[ 1 ] ] );
		}
	} else {
		for ( const operation in batchJobs ) {
			const operationJobs = batchJobs[ operation ];
			if ( Array.isArray( operationJobs ) ) {
				for ( const title of operationJobs ) {
					queue.push( [ operation, title ] );
				}
			} else {
				for ( const title in operationJobs ) {
					queue.push( [ operation, title ] );
				}
			}
		}
	}

	yield stepHelpers.waitForOperations( queue, ( title, i ) => MWBot.logStatus( '[=] ', i, queue.length, 'incirrus', title ) );
} );

const flattenJobs = ( batchJobs ) => {
	if ( !Array.isArray( batchJobs ) ) {
		const flatJobs = [];
		for ( const op in batchJobs ) {
			const data = batchJobs[ op ];
			const jobData = [ op ];
			if ( Array.isArray( data ) ) {
				for ( const title of data ) {
					flatJobs.push( jobData.concat( Array.isArray( title ) ? title : [ title ] ) );
				}
			} else {
				for ( const title in data ) {
					const d = data[ title ];
					flatJobs.push( jobData.concat( [ title ] )
						.concat( Array.isArray( d ) ? d : [ d ] ) );
				}
			}
		}
		return flatJobs;
	}
	return batchJobs;
};

// Run both in parallel so we don't have to wait for the batch to finish
// to start checking things. Hopefully they run in the same order...
const runBatch = Promise.coroutine( function* ( world, wiki, batchJobs ) {
	wiki = wiki || world.config.wikis.default;
	const client = yield world.onWiki( wiki );
	batchJobs = flattenJobs( batchJobs );
	// TODO: If the batch operation fails the waitForBatch will never complete,
	// it will just stick around forever ...
	yield Promise.all( [
		client.batch( batchJobs, 'CirrusSearch integration test edit', 2 ),
		waitForBatch.call( world, wiki, batchJobs )
	] );
} );

const runBatchFn = ( wiki, batchJobs ) => Promise.coroutine( function* () {
	if ( batchJobs === undefined ) {
		batchJobs = wiki;
		wiki = this.config.wikis.default;
	}
	yield runBatch( this, wiki, batchJobs );
} );

// Helpers for defining mwbot jobs in array syntax. Mostly needed
// for upload to specify text, but others come along for the ride
const job = {
	delete: ( title ) => [ 'delete', title ],
	edit: ( title, text ) => {
		if ( text[ 0 ] === '@' ) {
			text = fs.readFileSync( articlePath + text.substr( 1 ) ).toString();
		}
		return [ 'edit', title, text ];
	},
	upload: ( fileName, text ) => {
		const pathToFile = articlePath + fileName;
		return [ 'upload', fileName, pathToFile, '', { text: text } ];
	},
	uploadOverwrite: ( fileName, text ) => {
		const pathToFile = articlePath + fileName;
		return [ 'uploadOverwrite', fileName, pathToFile, '', { text: text } ];
	}
};

BeforeOnce( { tags: '@clean' }, runBatchFn( {
	delete: [ 'DeleteMeRedirect' ]
} ) );

BeforeOnce( { tags: '@prefix' }, runBatchFn( {
	edit: {
		"L'Oréal": "L'Oréal",
		'Jean-Yves Le Drian': 'Jean-Yves Le Drian'
	}
} ) );

BeforeOnce( { tags: '@redirect', timeout: 60000 }, runBatchFn( {
	edit: {
		'SEO Redirecttest': '#REDIRECT [[Search Engine Optimization Redirecttest]]',
		'Redirecttest Yikes': '#REDIRECT [[Redirecttest Yay]]',
		'User_talk:SEO Redirecttest': '#REDIRECT [[User_talk:Search Engine Optimization Redirecttest]]',
		'Seo Redirecttest': 'Seo Redirecttest',
		'Search Engine Optimization Redirecttest': 'Search Engine Optimization Redirecttest',
		'Redirecttest Yay': 'Redirecttest Yay',
		'User_talk:Search Engine Optimization Redirecttest': 'User_talk:Search Engine Optimization Redirecttest',
		'PrefixRedirectRanking 1': 'PrefixRedirectRanking 1',
		'LinksToPrefixRedirectRanking 1': '[[PrefixRedirectRanking 1]]',
		'TargetOfPrefixRedirectRanking 2': 'TargetOfPrefixRedirectRanking 2',
		'PrefixRedirectRanking 2': '#REDIRECT [[TargetOfPrefixRedirectRanking 2]]'
	}
} ) );

BeforeOnce( { tags: '@accent_squashing' }, runBatchFn( {
	edit: {
		'Áccent Sorting': 'Áccent Sorting',
		'Accent Sorting': 'Accent Sorting'
	}
} ) );

BeforeOnce( { tags: '@accented_namespace' }, runBatchFn( {
	edit: {
		'Mó:Test': 'some text'
	}
} ) );

BeforeOnce( { tags: '@setup_main or @filters or @prefix or @bad_syntax or @wildcard or @exact_quotes or @phrase_prefix', timeout: 60000 }, runBatchFn( {
	edit: {
		'Template:Template Test': 'pickles [[Category:TemplateTagged]]',
		'Catapult/adsf': 'catapult subpage [[Catapult]]',
		'Links To Catapult': '[[Catapult]]',
		Catapult: '♙ asdf [[Category:Weaponry]]',
		'Amazing Catapult': 'test [[Catapult]] [[Category:Weaponry]]',
		'Category:Weaponry': 'Weaponry refers to any items designed or used to attack and kill or destroy other people and property.',
		'Two Words': 'ffnonesenseword catapult {{Template_Test}} anotherword [[Category:TwoWords]] [[Category:Categorywith Twowords]] [[Category:Categorywith " Quote]]',
		AlphaBeta: '[[Category:Alpha]] [[Category:Beta]]',
		IHaveATwoWordCategory: '[[Category:CategoryWith ASpace]]',
		'Functional programming': 'Functional programming is referential transparency.',
		वाङ्मय: '\u0935\u093e\u0919\u094d\u092e\u092f',
		वाङ्‍मय: '\u0935\u093e\u0919\u094d\u200d\u092e\u092f',
		वाङ‍्मय: '\u0935\u093e\u0919\u200d\u094d\u092e\u092f',
		वाङ्‌मय: '\u0935\u093e\u0919\u094d\u200c\u092e\u092f',
		Wikitext: '{{#tag:somebug}}',
		'Page with non ascii letters': 'ἄνθρωπος, широкий',
		'Waffle Squash': articleText( 'wafflesquash.txt' ),
		'Waffle Squash 2': 'waffle<br>squash'
	}
} ) );

BeforeOnce( { tags: '@setup_main or @prefix or @bad_syntax' }, runBatchFn( [
	job.edit( 'Rdir', '#REDIRECT [[Two Words]]' ),
	job.edit( 'IHaveAVideo', '[[File:How to Edit Article in Arabic Wikipedia.ogg|thumb|267x267px]]' ),
	job.edit( 'IHaveASound', '[[File:Serenade for Strings -mvt-1- Elgar.ogg]]' ),
	job.upload( 'Savepage-greyed.png', 'Screenshot, for test purposes, associated with https://bugzilla.wikimedia.org/show_bug.cgi?id=52908 .' )
] ) );

BeforeOnce( { tags: '@setup_main or @prefix or @go or @bad_syntax or @smoke' }, runBatchFn( {
	edit: {
		África: 'for testing'
	}
} ) );

BeforeOnce( { tags: '@boost_template' }, runBatchFn( {
	edit: {
		'Template:BoostTemplateHigh': 'BoostTemplateTest',
		'Template:BoostTemplateLow': 'BoostTemplateTest',
		'NoTemplates BoostTemplateTest': 'nothing important',
		HighTemplate: '{{BoostTemplateHigh}}',
		LowTemplate: '{{BoostTemplateLow}}'
	}
} ) );

BeforeOnce( { tags: '@did_you_mean', timeout: 240000 }, function () {
	const edits = {
		'Popular Culture': 'popular culture',
		'Nobel Prize': 'nobel prize',
		'Noble Gasses': 'noble gasses',
		'Noble Somethingelse': 'noble somethingelse',
		'Noble Somethingelse2': 'noble somethingelse',
		'Noble Somethingelse3': 'noble somethingelse',
		'Noble Somethingelse4': 'noble somethingelse',
		'Noble Somethingelse5': 'noble somethingelse',
		'Noble Somethingelse6': 'noble somethingelse',
		'Noble Somethingelse7': 'noble somethingelse',
		'Template:Noble Pipe 1': 'pipes are so noble',
		'Template:Noble Pipe 2': 'pipes are so noble',
		'Template:Noble Pipe 3': 'pipes are so noble',
		'Template:Noble Pipe 4': 'pipes are so noble',
		'Template:Noble Pipe 5': 'pipes are so noble',
		'Rrr Word 1': '#REDIRECT [[Popular Culture]]',
		'Rrr Word 2': '#REDIRECT [[Popular Culture]]',
		'Rrr Word 3': '#REDIRECT [[Noble Somethingelse3]]',
		'Rrr Word 4': '#REDIRECT [[Noble Somethingelse4]]',
		'Rrr Word 5': '#REDIRECT [[Noble Somethingelse5]]',
		'Nobel Gassez': '#REDIRECT [[Noble Gasses]]'
	};
	return runBatch( this, false, { edit: edits } );
} );

BeforeOnce( { tags: '@did_you_mean or @stemming', timeout: 60000 }, runBatchFn( {
	edit: {
		'Stemming Multiwords': 'Stemming Multiwords',
		'Stemming Possessive’s': 'Stemming Possessive’s',
		Stemmingsinglewords: 'Stemmingsinglewords',
		'Stemmingsinglewords Other 1': 'Stemmingsinglewords Other 1',
		'Stemmingsinglewords Other 2': 'Stemmingsinglewords Other 2',
		'Stemmingsinglewords Other 3': 'Stemmingsinglewords Other 3',
		'Stemmingsinglewords Other 4': 'Stemmingsinglewords Other 4',
		'Stemmingsinglewords Other 5': 'Stemmingsinglewords Other 5',
		'Stemmingsinglewords Other 6': 'Stemmingsinglewords Other 6',
		'Stemmingsinglewords Other 7': 'Stemmingsinglewords Other 7',
		'Stemmingsinglewords Other 8': 'Stemmingsinglewords Other 8',
		'Stemmingsinglewords Other 9': 'Stemmingsinglewords Other 9',
		'Stemmingsinglewords Other 10': 'Stemmingsinglewords Other 10',
		'Stemmingsinglewords Other 11': 'Stemmingsinglewords Other 11',
		'Stemmingsinglewords Other 12': 'Stemmingsinglewords Other 12'
	}
} ) );

BeforeOnce( { tags: '@exact_quotes' }, runBatchFn( {
	edit: {
		'Contains A Stop Word': 'Contains A Stop Word',
		"Doesn't Actually Contain Stop Words": "Doesn't Actually Contain Stop Words",
		'Pick*': 'Pick*'
	}
} ) );

BeforeOnce( { tags: '@filesearch' }, Promise.coroutine( function* () {
	// Unfortunatly the current deduplication between wikis requires a file
	// be uploaded to commons before it's uploaded to any other wiki, or the
	// other wiki isn't tagged.
	yield runBatch( this, 'commons', [
		job.upload( 'DuplicatedLocally.svg', 'File stored on commons and duplicated locally' ),
		job.upload( 'OnCommons.svg', 'File stored on commons for test purposes' )
	] );

	yield runBatch( this, false, [
		job.upload( 'No_SVG.svg', '[[Category:Red circle with left slash]]' ),
		job.upload( 'Somethingelse_svg_SVG.svg', '[[Category:Red circle with left slash]]' ),
		job.upload( 'Savepage-greyed.png', 'Screenshot, for test purposes, associated with https://bugzilla.wikimedia.org/show_bug.cgi?id=52908 .' ),
		job.upload( 'DuplicatedLocally.svg', 'Locally stored file duplicated on commons' ),
		job.delete( 'File:Frozen.svg' )
	] );

} ) );

BeforeOnce( { tags: '@redirect_loop' }, Promise.coroutine( function* () {
	// These can't go through the normal runBatch because, as redirects that never
	// end up at an article, they don't actually make it into elasticsearch.
	const client = yield this.onWiki();
	yield client.batch( {
		edit: {
			'Redirect Loop': '#REDIRECT [[Redirect Loop 1]]',
			'Redirect Loop 1': '#REDIRECT [[Redirect Loop 2]]',
			'Redirect Loop 2': '#REDIRECT [[Redirect Loop 1]]'
		}
	} );
	// Randomly guess at how long to wait ...
	yield this.stepHelpers.waitForMs( 3000 );
} ) );

BeforeOnce( { tags: '@headings' }, runBatchFn( {
	edit: {
		HasHeadings: articleText( 'has_headings.txt' ),
		HasReferencesInText: 'References [[Category:HeadingsTest]]',
		HasHeadingsWithHtmlComment: articleText( 'has_headings_with_html_comment.txt' ),
		HasHeadingsWithReference: articleText( 'has_headings_with_reference.txt' )
	}
} ) );

BeforeOnce( { tags: '@javascript_injection' }, runBatchFn( {
	edit: {
		'Javascript Direct Inclusion': articleText( 'javascript.txt' ),
		'Javascript Pre Tag Inclusion': articleText( 'javascript_in_pre.txt' )
	}
} ) );

BeforeOnce( { tags: '@setup_namespaces' }, runBatchFn( {
	edit: {
		'Talk:Two Words': 'why is this page about catapults?',
		'Help:Smoosh': 'test',
		'File:Nothingasdf': 'nothingasdf'
	}
} ) );

BeforeOnce( { tags: '@highlighting' }, runBatchFn( {
	edit: {
		'Rashidun Caliphate': articleText( 'rashidun_caliphate.txt' ),
		'Crazy Rdir': '#REDIRECT [[Two Words]]',
		'Insane Rdir': '#REDIRECT [[Two Words]]',
		'The Once and Future King': 'The Once and Future King',
		'User_talk:Test': 'User_talk:Test',
		'Rose Trellis Faberge Egg': articleText( 'rose_trellis_faberge_egg.txt' )
	}
} ) );

BeforeOnce( { tags: '@highlighting or @references' }, runBatchFn( {
	edit: {
		'References Highlight Test': articleText( 'references_highlight_test.txt' )
	}
} ) );

BeforeOnce( { tags: '@more_like_this' }, runBatchFn( {
	edit: {
		'More Like Me 1': 'morelikesetone morelikesetone',
		'More Like Me 2': 'morelikesetone morelikesetone morelikesetone morelikesetone',
		'More Like Me 3': 'morelikesetone morelikesetone morelikesetone morelikesetone',
		'More Like Me 4': 'morelikesetone morelikesetone morelikesetone morelikesetone',
		'More Like Me 5': 'morelikesetone morelikesetone morelikesetone morelikesetone',
		'More Like Me Rdir': '#REDIRECT [[More Like Me 1]]',
		'More Like Me Set 2 Page 1': 'morelikesettwo morelikesettwo morelikesettwo',
		'More Like Me Set 2 Page 2': 'morelikesettwo morelikesettwo morelikesettwo',
		'More Like Me Set 2 Page 3': 'morelikesettwo morelikesettwo morelikesettwo',
		'More Like Me Set 2 Page 4': 'morelikesettwo morelikesettwo morelikesettwo',
		'More Like Me Set 2 Page 5': 'morelikesettwo morelikesettwo morelikesettwo',
		'More Like Me Set 3 Page 1': 'morelikesetthree morelikesetthree',
		'More Like Me Set 3 Page 2': 'morelikesetthree morelikesetthree',
		'More Like Me Set 3 Page 3': 'morelikesetthree morelikesetthree',
		'More Like Me Set 3 Page 4': 'morelikesetthree morelikesetthree',
		'More Like Me Set 3 Page 5': 'morelikesetthree morelikesetthree',
		'This is Me': 'this is me'
	}
} ) );

BeforeOnce( { tags: '@setup_phrase_rescore' }, runBatchFn( {
	edit: {
		'Rescore Test Words Chaff': 'Words Test Rescore Chaff',
		'Test Words Rescore Rescore Test Words': 'Test Words Rescore Rescore Test Words',
		'Rescore Test TextContent': 'Chaff',
		'Rescore Test HasTextContent': 'Rescore Test TextContent'
	}
} ) );

BeforeOnce( { tags: '@programmer_friendly' }, runBatchFn( {
	edit: {
		$wgNamespaceAliases: '$wgNamespaceAliases',
		PFSC: 'snake_case',
		PascalCase: 'PascalCase',
		NumericCase7: 'NumericCase7',
		'this.getInitial': 'this.getInitial',
		'RefToolbarBase.js': 'RefToolbarBase.js',
		'PFTest Paren': 'this.isCamelCased()'
	}
} ) );

BeforeOnce( { tags: '@stemmer' }, runBatchFn( {
	edit: {
		'StemmerTest Aliases': 'StemmerTest Aliases',
		'StemmerTest Alias': 'StemmerTest Alias',
		'StemmerTest Used': 'StemmerTest Used',
		'StemmerTest Guidelines': 'StemmerTest Guidelines'
	}
} ) );

BeforeOnce( { tags: '@prefix_filter' }, runBatchFn( {
	edit: {
		'Prefix Test': 'Prefix Test',
		'Prefix Test Redirect': '#REDIRECT [[Prefix Test]]',
		'Foo Prefix Test': '[[Prefix Test]]',
		'Prefix Test/AAAA': '[[Prefix Test]]',
		'Prefix Test AAAA': '[[Prefix Test]]',
		'Talk:Prefix Test': '[[Prefix Test]]',
		'User_talk:Prefix Test': '[[Prefix Text]]'
	}
} ) );

BeforeOnce( { tags: '@prefer_recent', timeout: 60000 }, runBatchFn( {
	// NOTE: this was originally a real test for testing recency with prefer-recent
	// it was transformed into a simple smoke test because it was too unreliable,
	// (it's why PreferRecent Third is created in the same batch).
	edit: {
		'PreferRecent First': 'PreferRecent random text for field norm ' + ( new Date() / 1 ),
		'PreferRecent Second': 'PreferRecent ' + ( new Date() / 1 ),
		'PreferRecent Third': 'PreferRecent random text for field norm ' + ( new Date() / 1 )
	}
} ) );

BeforeOnce( { tags: '@hastemplate' }, runBatchFn( {
	edit: {
		MainNamespaceTemplate: 'MainNamespaceTemplate',
		HasMainNSTemplate: '{{:MainNamespaceTemplate}}',
		CaseCheckTemplate: 'CaseCheckTemplate',
		HasCaseCheckTemplate: '{{Template:CaseCheckTemplate}}',
		casechecktemplate: 'casechecktemplate',
		Hascasechecktemplate: '{{Template:casechecktemplate}}',
		'Talk:TalkTemplate': 'Talk:TalkTemplate',
		HasTTemplate: '{{Talk:TalkTemplate}}'
	}
} ) );

BeforeOnce( { tags: '@go' }, runBatchFn( {
	edit: {
		MixedCapsAndLowerCase: 'MixedCapsAndLowerCase'
	}
} ) );

BeforeOnce( { tags: '@go or @options', timeout: 120000 }, runBatchFn( {
	edit: {
		'son Nearmatchflattentest': 'son Nearmatchflattentest',
		'Son Nearmatchflattentest': 'Son Nearmatchflattentest',
		'SON Nearmatchflattentest': 'SON Nearmatchflattentest',
		'soñ Nearmatchflattentest': 'soñ Nearmatchflattentest',
		'Son Nolower Nearmatchflattentest': 'Son Nolower Nearmatchflattentest',
		'SON Nolower Nearmatchflattentest': 'SON Nolower Nearmatchflattentest',
		'Soñ Nolower Nearmatchflattentest': 'Soñ Nolower Nearmatchflattentest',
		'Son Titlecase Nearmatchflattentest': 'Son Titlecase Nearmatchflattentest',
		'Soñ Titlecase Nearmatchflattentest': 'Soñ Titlecase Nearmatchflattentest',
		'Soñ Onlyaccent Nearmatchflattentest': 'Soñ Onlyaccent Nearmatchflattentest',
		'Soñ Twoaccents Nearmatchflattentest': 'Soñ Twoaccents Nearmatchflattentest',
		'Són Twoaccents Nearmatchflattentest': 'Són Twoaccents Nearmatchflattentest',
		'son Double Nearmatchflattentest': 'son Double Nearmatchflattentest',
		'SON Double Nearmatchflattentest': 'SON Double Nearmatchflattentest',
		'Bach Nearmatchflattentest': '#REDIRECT [[Johann Sebastian Bach Nearmatchflattentest]]',
		'Bạch Nearmatchflattentest': 'Notice the dot under the a.',
		'Johann Sebastian Bach Nearmatchflattentest': 'Johann Sebastian Bach Nearmatchflattentest',
		'KOAN Nearmatchflattentest': 'KOAN Nearmatchflattentest',
		'Kōan Nearmatchflattentest': 'Kōan Nearmatchflattentest',
		'Koan Nearmatchflattentest': '#REDIRECT [[Kōan Nearmatchflattentest]]',
		'Soñ Redirect Nearmatchflattentest': 'Soñ Redirect Nearmatchflattentest',
		'Són Redirect Nearmatchflattentest': 'Són Redirect Nearmatchflattentest',
		'Son Redirect Nearmatchflattentest': '#REDIRECT [[Soñ Redirect Nearmatchflattentest]]',
		'Són Redirectnotbetter Nearmatchflattentest': 'Són Redirectnotbetter Nearmatchflattentest',
		'Soñ Redirectnotbetter Nearmatchflattentest': '#REDIRECT [[Són Redirectnotbetter Nearmatchflattentest]]',
		'Són Redirecttoomany Nearmatchflattentest': 'Són Redirecttoomany Nearmatchflattentest',
		'Soñ Redirecttoomany Nearmatchflattentest': '#REDIRECT [[Són Redirecttoomany Nearmatchflattentest]]',
		'Søn Redirecttoomany Nearmatchflattentest': 'Søn Redirecttoomany Nearmatchflattentest',
		'Blah Redirectnoncompete Nearmatchflattentest': 'Blah Redirectnoncompete Nearmatchflattentest',
		'Soñ Redirectnoncompete Nearmatchflattentest': '#REDIRECT [[Blah Redirectnoncompete Nearmatchflattentest]]',
		'Søn Redirectnoncompete Nearmatchflattentest': '#REDIRECT [[Blah Redirectnoncompete Nearmatchflattentest]]'
	}
} ) );

BeforeOnce( { tags: '@file_text or @filesearch' }, Promise.coroutine( function* () {
	// TODO: this one is really unclear to me, figure out why we need such hack
	// This file is available on commons.wikimedia.org and because $wgUseInstantCommons is set to true
	// mwbot may think it's a dup and won't upload it. Use uploadOverwrite to avoid that.
	// But to use uploadOverwrite we first make sure that the file is not here otherwise mwbot
	// will complain about perfect duplicate...
	yield runBatch( this, false, {
		delete: [
			'File:Linux_Distribution_Timeline_text_version.pdf'
		]
	} );
	yield runBatch( this, false, [
		job.uploadOverwrite( 'Linux_Distribution_Timeline_text_version.pdf', 'Linux distribution timeline.' )
	] );
} ) );

BeforeOnce( { tags: '@match_stopwords' }, runBatchFn( {
	edit: {
		To: 'To'
	}
} ) );

BeforeOnce( { tags: '@many_redirects' }, runBatchFn( {
	edit: {
		Manyredirectstarget: '[[Category:ManyRedirectsTest]]',
		Fewredirectstarget: '[[Category:ManyRedirectsTest]]',
		'Many Redirects Test 1': '#REDIRECT [[Manyredirectstarget]]',
		'Many Redirects Test 2': '#REDIRECT [[Manyredirectstarget]]',
		'Useless redirect to target 1': '#REDIRECT [[Manyredirectstarget]]',
		'Useless redirect to target 2': '#REDIRECT [[Manyredirectstarget]]',
		'Useless redirect to target 3': '#REDIRECT [[Manyredirectstarget]]',
		'Useless redirect to target 4': '#REDIRECT [[Manyredirectstarget]]',
		'Useless redirect to target 5': '#REDIRECT [[Manyredirectstarget]]',
		'Many Redirects Test ToFew': '#REDIRECT [[Fewredirectstarget]]'
	}
} ) );

BeforeOnce( { tags: '@relevancy', timeout: 160000 }, runBatchFn( {
	edit: {
		'Relevancyredirecttest Smaller': 'Relevancyredirecttest A text text text text text text text text text text text text text',
		'Relevancyredirecttest Smaller/A': '[[Relevancyredirecttest Smaller]]',
		'Relevancyredirecttest Smaller/B': '[[Relevancyredirecttest Smaller]]',
		'Relevancyredirecttest Larger': 'Relevancyredirecttest B text text text text text text text text text text text text text',
		'Relevancyredirecttest Larger/Redirect': '#REDIRECT [[Relevancyredirecttest Larger]]',
		'Relevancyredirecttest Larger/A': '[[Relevancyredirecttest Larger]]',
		'Relevancyredirecttest Larger/B': '[[Relevancyredirecttest Larger/Redirect]]',
		'Relevancyredirecttest Larger/C': '[[Relevancyredirecttest Larger/Redirect]]',
		'Relevancylinktest Smaller': 'Relevancylinktest Smaller',
		'Relevancylinktest Larger Extraword': 'Relevancylinktest needs 5 extra words',
		'Relevancylinktest Larger/Link A': '[[Relevancylinktest Larger Extraword]]',
		'Relevancylinktest Larger/Link B': '[[Relevancylinktest Larger Extraword]]',
		'Relevancylinktest Larger/Link C': '[[Relevancylinktest Larger Extraword]]',
		'Relevancylinktest Larger/Link D': '[[Relevancylinktest Larger Extraword]]',
		Relevancytest: 'it is not relevant',
		Relevancytestviaredirect: 'not relevant',
		'Relevancytest Redirect': '#REDIRECT [[Relevancytestviaredirect]]',
		Relevancytestviacategory: 'Some opening text. [[Category:Relevancytest]]',
		Relevancytestviaheading: '==Relevancytest==',
		Relevancytestviaopening: articleText( 'Relevancytestviaopening.txt' ),
		Relevancytestviatext: '[[Relevancytest]]',
		Relevancytestviaauxtext: articleText( 'Relevancytestviaauxtext.txt' ),
		'Relevancytestphrase phrase': 'not relevant',
		Relevancytestphraseviaredirect: 'not relevant',
		'Relevancytestphrase Phrase Redirect': '#REDIRECT [[Relevancytestphraseviaredirect]]',
		Relevancytestphraseviacategory: 'not relevant [[Category:Relevancytestphrase phrase category]]',
		Relevancytestphraseviaheading: '==Relevancytestphrase phrase heading==',
		Relevancytestphraseviaopening: articleText( 'Relevancytestphraseviaopening.txt' ),
		Relevancytestphraseviatext: '[[Relevancytestphrase phrase]] text',
		Relevancytestphraseviaauxtext: articleText( 'Relevancytestphraseviaauxtext.txt' ),
		'Relevancytwo Wordtest': 'relevance is bliss',
		'Wordtest Relevancytwo': 'relevance is cool',
		Relevancynamespacetest: 'Relevancynamespacetest',
		'Talk:Relevancynamespacetest': 'Talk:Relevancynamespacetest',
		'File:Relevancynamespacetest': 'File:Relevancynamespacetest',
		'Help:Relevancynamespacetest': 'Help:Relevancynamespacetest',
		'File talk:Relevancynamespacetest': 'File talk:Relevancynamespacetest',
		'User talk:Relevancynamespacetest': 'User talk:Relevancynamespacetest',
		'Template:Relevancynamespacetest': 'Template:Relevancynamespacetest',
		'Relevancylanguagetest/ja': 'Relevancylanguagetest/ja',
		'Relevancylanguagetest/en': 'Relevancylanguagetest/en',
		'Relevancylanguagetest/ar': 'Relevancylanguagetest/ar',
		'Relevancyclosetest Foô': 'Relevancyclosetest Foô',
		'Relevancyclosetest Foo': 'Relevancyclosetest Foo',
		'Foo Relevancyclosetest': 'Foo Relevancyclosetest',
		'William Shakespeare': 'William Shakespeare',
		'William Shakespeare Works': 'To be or not to be is a famous quote from Hamlet'
	}
} ) );

BeforeOnce( { tags: '@fallback_finder' }, runBatchFn( {
	edit: {
		$US: '$US',
		US: 'US',
		Uslink: '[[US]]',
		'Cent (currency)': 'Cent (currency)',
		'¢': '#REDIRECT [[Cent (currency)]]'
	}
} ) );

BeforeOnce( { tags: '@js_and_css' }, runBatchFn( {
	edit: {
		'User:Admin/Some.js': articleText( 'some.js' ),
		'User:Admin/Some.css': articleText( 'some.css' )
	}
} ) );

BeforeOnce( { tags: '@special_random' }, runBatchFn( {
	edit: {
		'User:Random Test': 'User:Random Test',
		'User_talk:Random Test': 'User_talk:Random Test'
	}
} ) );

BeforeOnce( { tags: '@regex' }, runBatchFn( {
	edit: {
		RegexEscapedForwardSlash: 'a/b',
		RegexEscapedBackslash: 'a\\b',
		RegexEscapedDot: 'a.b',
		RegexSpaces: 'a b c',
		RegexComplexResult: 'aaabacccccccccccccccdcccccccccccccccccccccccccccccdcccc'
	}
} ) );

BeforeOnce( { tags: '@linksto' }, Promise.coroutine( function* () {
	yield runBatch( this, false, {
		edit: {
			'LinksToTest Target': 'LinksToTest Target',
			'LinksToTest Plain': '[[LinksToTest Target]]',
			'LinksToTest OtherText': '[[LinksToTest Target]] and more text',
			'LinksToTest No Link': 'LinksToTest Target',
			'Template:LinksToTest Template': '[[LinksToTest Target]]',
			'LinksToTest LinksToTemplate': '[[Template:LinksToTest Template]]'
		}
	} );
	// We need to guarantee the template exists before this edit goes through.
	yield runBatch( this, false, {
		edit: {
			'LinksToTest Using Template': '{{LinksToTest Template}}'
		}
	} );
} ) );

BeforeOnce( { tags: '@filenames' }, runBatchFn( [
	job.upload( 'No_SVG.svg', '[[Category:Red circle with left slash]]' ),
	job.upload( 'Somethingelse_svg_SVG.svg', '[[Category:Red circle with left slash]]' )
] ) );

BeforeOnce( { tags: '@removed_text' }, runBatchFn( {
	edit: {
		'Autocollapse Example': '<div class="autocollapse">inside autocollapse</div>'
	}
} ) );

BeforeOnce( { tags: '@setup_main or @commons' }, Promise.coroutine( function* () {
	yield runBatch( this, 'commons', {
		delete: [
			'File:OnCommons.svg',
			'File:DuplicatedLocally.svg'
		]
	} );
	yield runBatch( this, false, {
		delete: [ 'File:DuplicatedLocally.svg' ]
	} );

	yield runBatch( this, 'commons', [
		// TODO: Why is overwrite necessary here? Otherwise the upload is rejected
		// with was-deleted or some such?
		job.uploadOverwrite( 'OnCommons.svg', 'File stored on commons for test purposes' ),
		job.uploadOverwrite( 'DuplicatedLocally.svg', 'File stored on commons and duplicated locally' )
	] );
	// For duplications to track correctly commons has to be uploaded first. This is a bug
	// in cirrus, but no current plans to fix.
	yield runBatch( this, false, [
		job.uploadOverwrite( 'DuplicatedLocally.svg', 'Locally stored file duplicated on commons' )
	] );
} ) );

BeforeOnce( { tags: '@ru' }, runBatchFn( 'ru', {
	edit: {
		'Черная дыра': 'Черная дыра́ — область пространства-времени',
		'Саша Чёрный': 'настоящее имя Алекса́ндр Миха́йлович Гли́кберг',
		Бразер: 'белорусский советский скульптор'
	}
} ) );

BeforeOnce( { tags: '@geo' }, runBatchFn( {
	edit: {
		'San Jose': 'San Jose is a nice city located at {{#coordinates:primary|37.333333|-121.9}}.',
		'Santa Clara': 'Santa Clara is a nice city located at {{#coordinates:primary|37.354444|-121.969167}}.',
		Cupertino: 'Cupertino is a nice city located at {{#coordinates:primary|37.3175|-122.041944}}.'
	}
} ) );

After( { tags: '@frozen' }, Promise.coroutine( function* () {
	const client = yield this.onWiki();
	yield client.request( {
		action: 'cirrus-freeze-writes',
		thaw: 1
	} );
} ) );

// This needs to be the *last* hook added. That gives us some hope that everything
// else is inside elasticsearch by the time cirrus-suggest-index runs and builds
// the completion suggester
BeforeOnce( { tags: '@suggest', timeout: 120000 }, Promise.coroutine( function* () {
	yield runBatch( this, false, {
		edit: {
			Venom: 'Venom: or the Venom Symbiote: is a fictional supervillain appearing in American comic books published by Marvel Comics. The character is a sentient alien Symbiote with an amorphous, liquid-like form, who requires a host, usually human, to bond with for its survival.',
			'X-Men': 'The X-Men are a fictional team of superheroes',
			'Xavier: Charles': 'Professor Charles Francis Xavier (also known as Professor X) is the founder of [[X-Men]]',
			'X-Force': 'X-Force is a fictional team of of [[X-Men]]',
			Magneto: 'Magneto is a fictional character appearing in American comic books',
			'Help:Magneto': 'Help:Magneto',
			'Max Eisenhardt': '#REDIRECT [[Magneto]]',
			'Eisenhardt, Max': '#REDIRECT [[Magneto]]',
			Magnetu: '#REDIRECT [[Magneto]]',
			Ice: "It's cold.",
			Iceman: 'Iceman (Robert "Bobby" Drake) is a fictional superhero appearing in American comic books published by Marvel Comics and is...',
			'Ice Man (Marvel Comics)': '#REDIRECT [[Iceman]]',
			'Ice-Man (comics books)': '#REDIRECT [[Iceman]]',
			'Ultimate Iceman': '#REDIRECT [[Iceman]]',
			Électricité: 'This is electicity in french.',
			Elektra: 'Elektra is a fictional character appearing in American comic books published by Marvel Comics.',
			'Help:Navigation': 'When viewing any page on MediaWiki...',
			'V:N': '#REDIRECT [[Help:Navigation]]',
			'Z:Navigation': '#REDIRECT [[Help:Navigation]]',
			'Zam Wilson': '#REDIRECT [[Sam Wilson]]',
			'The Doors': 'The Doors were an American rock band formed in 1965 in Los Angeles.',
			'Hyperion Cantos/Endymion': 'Endymion is the third science fiction novel by Dan Simmons.',
			はーい: 'makes sure we do not fail to index empty tokens (T156234)',
			'Sam Wilson': 'Warren Kenneth Worthington III: originally known as Angel and later as Archangel: ... Marvel Comics like [[Venom]]. {{DEFAULTSORTKEY:Wilson: Sam}}'
		}
	} );

	const client = yield this.onWiki();
	yield client.request( {
		action: 'cirrus-suggest-index'
	} );
} ) );
