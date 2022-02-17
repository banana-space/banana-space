/**
 * The World is a container for global state shared across test steps.
 * The World is instanciated after every scenario, so state cannot be
 * carried over between scenarios.
 *
 * Note: the `StepHelpers` are bound to the World object so that they have access
 * to the same apiClient instance as `World` (useful because the apiClient
 * keeps a user/login state).
 */

'use strict';

const { setWorldConstructor } = require( 'cucumber' ),
	request = require( 'request-promise-native' ),
	log = require( 'semlog' ).log,
	Bot = require( 'mwbot' ),
	StepHelpers = require( '../step_definitions/page_step_helpers' ),
	Page = require( './pages/page' ),
	Promise = require( 'bluebird' );

// Client for the Server implemented in lib/tracker.js. The server
// tracks what tags have already been initialized so we don't have
// to do it for every feature file.
class TagClient {
	constructor( options ) {
		this.tags = {};
		this.unixSocketPath = options.trackerPath;
		this.silentLog = options.logLevel !== 'verbose';
	}

	check( tag ) {
		return Promise.coroutine( function* () {
			if ( this.tags[ tag ] ) {
				return this.tags[ tag ];
			}
			log( `[D] TAG >> ${tag}`, this.silentLog );
			const response = yield this.post( { check: tag } );
			log( `[D] TAG << ${tag}`, this.silentLog );
			if ( response.status === 'complete' || response.status === 'reject' ) {
				this.tags[ tag ] = response.status;
			}
			return response.status;
		} ).call( this );
	}

	reject( tag ) {
		this.tags[ tag ] = 'reject';
		return this.post( { reject: tag } );
	}

	complete( tag ) {
		this.tags[ tag ] = 'complete';
		return this.post( { complete: tag } );
	}

	post( data ) {
		return request.post( {
			uri: `http://unix:${this.unixSocketPath}:/tracker`,
			json: data
		} );
	}
}

const tagClient = new TagClient( browser.config );
// world gets re-created all the time. Try and save some time logging
// in by sharing api clients
const apiClients = {};

function World( { attach, parameters } ) {
	// default properties
	this.attach = attach;
	this.parameters = parameters;

	// Since you can't pass values between step definitions directly,
	// the last Api response is stored here so it can be accessed between steps.
	// (I have a feeling this is prone to race conditions).
	// By suggestion of this stack overflow question.
	// https://stackoverflow.com/questions/26372724/pass-variables-between-step-definitions-in-cucumber-groovy
	this.apiResponse = undefined;
	this.apiError = undefined;

	this.setApiResponse = function ( value ) {
		this.apiResponse = value;
		this.apiError = undefined;
	};
	this.setApiError = function ( error ) {
		this.apiResponse = undefined;
		this.apiError = error;
	};

	// Shortcut to environment configs
	this.config = browser.config;

	// Extra process tracking what tags have been initialized
	this.tags = tagClient;

	// Per-wiki api clients
	this.onWiki = function ( wiki = this.config.wikis.default ) {
		if ( apiClients[ wiki ] ) {
			return apiClients[ wiki ];
		}

		const w = this.config.wikis[ wiki ];
		const client = new Bot();
		client.setOptions( {
			verbose: true,
			silent: false,
			defaultSummary: 'MWBot',
			concurrency: 1,
			apiUrl: w.apiUrl
		} );

		// Add a generic method to get access to the request that triggered a response, so we
		// can add generic error reporting that includes the requested api url
		const origRawRequest = client.rawRequest;
		client.rawRequest = function ( requestOptions ) {
			return origRawRequest.call( client, requestOptions ).then( ( response ) => {
				// TODO: What conditions cause this to be a string?
				if ( typeof response !== 'string' ) {
					response.__request = requestOptions;
				}
				return response;
			} );
		};

		apiClients[ wiki ] = client.loginGetEditToken( {
			username: w.username,
			password: w.botPassword,
			apiUrl: w.apiUrl
		} ).then( () => client );

		// Catch anything trying to re-login and break everything
		client.loginGetEditToken = undefined;

		return apiClients[ wiki ];
	};

	// Binding step helpers to this World.
	// Step helpers are just step functions that are abstracted
	// for the purpose of using them outside of the steps themselves (like in hooks).
	this.stepHelpers = new StepHelpers( this );

	// Shortcut for browser.url(), accepts a Page object
	// as well as a string, assumes the Page object
	// has a url property
	this.visit = function ( page, wiki = this.config.wikis.default ) {
		let tmpUrl;
		if ( page instanceof Page && page.url ) {
			tmpUrl = page.url;
		}
		if ( typeof page === 'string' && page ) {
			tmpUrl = page;
		}
		if ( !tmpUrl ) {
			throw Error( `In "World.visit(page)" page is falsy: page=${page}` );
		}
		tmpUrl = this.config.wikis[ wiki ].baseUrl + tmpUrl;
		log( `[D] Visiting page: ${tmpUrl}`, this.tags.silentLog );
		browser.url( tmpUrl );
		// logs full URL in case of typos, misplaced backslashes.
		log( `[D] Visited page: ${browser.getUrl()}`, this.tags.silentLog );
	};
}

setWorldConstructor( World );
