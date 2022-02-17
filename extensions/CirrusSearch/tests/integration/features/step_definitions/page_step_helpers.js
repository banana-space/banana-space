/**
 * StepHelpers are abstracted functions that usually represent the
 * behaviour of a step. They are placed here, instead of in the actual step,
 * so that they can be used in the Hook functions as well.
 *
 * Cucumber.js considers calling steps explicitly an antipattern,
 * and therefore this ability has not been implemented in Cucumber.js even though
 * it is available in the Ruby implementation.
 * https://github.com/cucumber/cucumber-js/issues/634
 */
'use strict';

const expect = require( 'chai' ).expect,
	fs = require( 'fs' ),
	path = require( 'path' ),
	Promise = require( 'bluebird' ),
	articlePath = path.dirname( path.dirname( path.dirname( __dirname ) ) ) + '/integration/articles/';

class StepHelpers {
	constructor( world, wiki ) {
		this.world = world;
		this.wiki = wiki || world.config.wikis.default;
		this.apiPromise = world.onWiki( this.wiki );
	}

	onWiki( wiki ) {
		return new StepHelpers( this.world, wiki );
	}

	deletePage( title, options = {} ) {
		return Promise.coroutine( function* () {
			const client = yield this.apiPromise;
			try {
				yield client.delete( title, 'CirrusSearch integration test delete' );
				if ( !options.skipWaitForOperatoin ) {
					yield this.waitForOperation( 'delete', title );
				}
			} catch ( err ) {
				// still return true if page doesn't exist
				expect( err.message ).to.include( "doesn't exist" );
			}
		} ).call( this );
	}

	uploadFile( title, fileName, description ) {
		return Promise.coroutine( function* () {
			const client = yield this.apiPromise;
			const filePath = path.join( articlePath, fileName );
			yield client.batch( [
				[ 'upload', fileName, filePath, '', { text: description } ]
			] );
			yield this.waitForOperation( 'upload', fileName );
		} ).call( this );
	}

	editPage( title, text, options = {} ) {
		return Promise.coroutine( function* () {
			const client = yield this.apiPromise;

			if ( text[ 0 ] === '@' ) {
				text = fs.readFileSync( path.join( articlePath, text.substr( 1 ) ) ).toString();
			}
			const fetchedText = yield this.getWikitext( title );
			if ( options.append ) {
				text = fetchedText + text;
			}
			if ( text.trim() !== fetchedText.trim() ) {
				const editResponse = yield client.edit( title, text );
				if ( !options.skipWaitForOperation ) {
					yield this.waitForOperation( 'edit', title, null, editResponse.edit.newrevid );
				}
			}
		} ).call( this );
	}

	getWikitext( title ) {
		return Promise.coroutine( function* () {
			const client = yield this.apiPromise;
			const response = yield client.request( {
				action: 'query',
				format: 'json',
				formatversion: 2,
				prop: 'revisions',
				rvprop: 'content',
				titles: title
			} );
			if ( response.query.pages[ 0 ].missing ) {
				return '';
			}
			return response.query.pages[ 0 ].revisions[ 0 ].content;
		} ).call( this );
	}

	movePage( from, to, noRedirect = true ) {
		return Promise.coroutine( function* () {
			const client = yield this.apiPromise;
			yield client.request( {
				action: 'move',
				from: from,
				to: to,
				noredirect: noRedirect ? 1 : 0,
				token: client.editToken
			} );
			// If no redirect was left behind we have no way to check the
			// old page has been removed from elasticsearch. The page table
			// entry itself was renamed leaving nothing (except a log) for
			// the api to find. Post-processing in cirrus will remove deleted
			// pages that elastic returns though, so perhaps not a big deal
			// (except we cant test it was really deleted...).
			yield this.waitForOperation( 'edit', to );
			if ( !noRedirect ) {
				yield this.waitForOperation( 'edit', from );
			}
		} ).call( this );
	}

	suggestionSearch( query, limit = 'max' ) {
		return Promise.coroutine( function* () {
			const client = yield this.apiPromise;

			try {
				const response = yield client.request( {
					action: 'opensearch',
					search: query,
					cirrusUseCompletionSuggester: 'yes',
					limit: limit
				} );
				this.world.setApiResponse( response );
			} catch ( err ) {
				this.world.setApiError( err );
			}
		} ).call( this );
	}

	suggestionsWithProfile( query, profile, namespaces = undefined ) {
		return Promise.coroutine( function* () {
			const client = yield this.apiPromise;
			const request = {
				action: 'opensearch',
				search: query,
				profile: profile
			};
			if ( namespaces ) {
				request.namespace = namespaces.replace( /','/g, '|' );
			}
			try {
				const response = yield client.request( request );
				this.world.setApiResponse( response );
			} catch ( err ) {
				this.world.setApiError( err );
			}
		} ).call( this );
	}

	searchFor( query, options = {} ) {
		return Promise.coroutine( function* () {
			const client = yield this.apiPromise;

			try {
				const response = yield client.request( Object.assign( options, {
					action: 'query',
					list: 'search',
					srsearch: query,
					srprop: 'snippet|titlesnippet|redirectsnippet|sectionsnippet|categorysnippet|isfilematch',
					formatversion: 2
				} ) );
				this.world.setApiResponse( response );
			} catch ( err ) {
				this.world.setApiError( err );
			}
		} ).call( this );
	}

	waitForDocument( title, check ) {
		return Promise.coroutine( function* () {
			const timeoutMs = 20000;
			const start = new Date();
			let lastError;
			while ( true ) {
				const doc = yield this.getCirrusIndexedContent( title );
				if ( doc.cirrusdoc && doc.cirrusdoc.length > 0 ) {
					try {
						check( doc.cirrusdoc[ 0 ] );
						break;
					} catch ( err ) {
						lastError = err;
					}
				}
				if ( new Date() - start >= timeoutMs ) {
					throw lastError || new Error( `Timeout out waiting for ${title}` );
				}
				yield this.waitForMs( 200 );
			}
		} ).call( this );
	}

	waitForMs( ms ) {
		return new Promise( ( resolve ) => setTimeout( resolve, ms ) );
	}

	waitForOperation( operation, title, timeoutMs = null, revisionId = null ) {
		return this.waitForOperations( [ [ operation, title, revisionId ] ], null, timeoutMs );
	}

	/**
	 * Wait by scanning the cirrus indices to check if the list of operations
	 * has been done and are effective in elastic.
	 *
	 * @param {Array[]} operations List of operations to wait for.
	 *  Array elements are [ operation, title, revisionId (optional) ]
	 * @param {Function} log Log callback when an operation is done
	 * @param {number} timeoutMs Max time to wait, default to Xsec*number of operations.
	 *  Where X is 10 for simple operations and 30s for uploads.
	 * @return {Promise} that resolves when everything is done or fails otherwise.
	 */
	waitForOperations( operations, log = null, timeoutMs = null ) {
		return Promise.coroutine( function* () {
			if ( !timeoutMs ) {
				timeoutMs = operations.reduce( ( total, v ) => total + ( v[ 0 ].match( /^upload/ ) ? 30000 : 10000 ), 0 );
			}
			const start = new Date();

			const done = [];
			const failedOps = ( ops, doneOps ) => ops.filter( ( v, idx ) => doneOps.indexOf( idx ) === -1 ).map( ( v ) => `[${v[ 0 ]}:${v[ 1 ]}]` ).join();
			while ( done.length !== operations.length ) {
				let consecutiveFailures = 0;
				for ( let i = 0; i < operations.length; i++ ) {
					const operation = operations[ i ][ 0 ];
					let title = operations[ i ][ 1 ];
					const revisionId = operations[ i ][ 2 ];
					if ( done.indexOf( i ) !== -1 ) {
						continue;
					}
					if ( consecutiveFailures > 10 ) {
						// restart the loop when we fail too many times
						// next pages, let's retry from the beginning.
						// mwbot is perhaps behind so instead of continuing to check
						consecutiveFailures = 0;
						break;
					}
					if ( ( operation === 'upload' || operation === 'uploadOverwrite' ) && title.substr( 0, 5 ) !== 'File:' ) {
						title = 'File:' + title;
					}
					const expect = operation !== 'delete';
					const exists = yield this.checkExists( title, revisionId );
					if ( exists === expect ) {
						if ( log ) {
							log( title, done.length + 1 );
						}
						done.push( i );
						consecutiveFailures = 0;
					} else {
						consecutiveFailures++;
					}
					yield this.waitForMs( 10 );
				}
				if ( done.length === operations.length ) {
					break;
				}

				if ( new Date() - start >= timeoutMs ) {
					const failed_ops = failedOps( operations, done );
					throw new Error( `Timed out waiting for ${failed_ops}` );
				}
				yield this.waitForMs( 50 );
			}
		} ).call( this );
	}

	/**
	 * Call query api with cirrusdoc prop to return the docs identified
	 * by title that are indexed in elasticsearch.
	 *
	 * NOTE: Multiple docs can be returned if the doc identified by title is indexed
	 * over multiple indices (content/general).
	 *
	 * @param {string} title page title
	 * @return {Promise} resolves to an array of indexed docs or null if title not indexed
	 */
	getCirrusIndexedContent( title ) {
		return Promise.coroutine( function* () {
			const client = yield this.apiPromise;
			const response = yield client.request( {
				action: 'query',
				prop: 'cirrusdoc',
				titles: title,
				format: 'json',
				formatversion: 2
			} );
			if ( response.query.normalized ) {
				for ( const norm of response.query.normalized ) {
					if ( norm.from === title ) {
						title = norm.to;
						break;
					}
				}
			}
			for ( const page of response.query.pages ) {
				if ( page.title === title ) {
					return page;
				}
			}
			return null;
		} ).call( this );
	}

	/**
	 * Check if title is indexed
	 *
	 * @param {string} title
	 * @param {string} revisionId
	 * @return {Promise.<boolean>} resolves to a boolean
	 */
	checkExists( title, revisionId = null ) {
		return Promise.coroutine( function* () {
			const page = yield this.getCirrusIndexedContent( title );
			const content = page.cirrusdoc;
			// without boolean cast we could return undefined
			let isOk = Boolean( content && content.length > 0 );
			// Is the requested page and the returned document dont have the same
			// title that means we have a redirect. In that case the revision id
			// wont match, but the backend api ensures the redirect is now contained
			// within the document. Unfortunately if the page was just edited to
			// now be a redirect anymore this is wrong ...
			if ( isOk && revisionId && content[ 0 ].source.title === page.title ) {
				isOk = parseInt( content[ 0 ].source.version, 10 ) === revisionId;
			}
			return isOk;
		} ).call( this );
	}

	pageIdOf( title ) {
		return Promise.coroutine( function* () {
			const client = yield this.apiPromise;
			const response = yield client.request( { action: 'query', titles: title, formatversion: 2 } );
			return response.query.pages[ 0 ].pageid;
		} ).call( this );
	}
}

module.exports = StepHelpers;
