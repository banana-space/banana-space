'use strict';

const restify = require( 'restify' ),
	Promise = require( 'bluebird' );

class Server {
	constructor( options ) {
		this.server = restify.createServer( {
			name: 'tracker',
			version: '1.0.0'
		} );

		this.unixSocketPath = options.trackerPath;

		this.server.use( restify.plugins.acceptParser( this.server.acceptable ) );
		this.server.use( restify.plugins.queryParser() );
		this.server.use( restify.plugins.bodyParser() );

		const globals = {
			tags: {},
			pending: {},
			resolvers: {}
		};

		this.server.post( '/tracker', function ( req, res, next ) {
			const data = req.body;

			if ( globals.resolvers[ data.complete ] ) {
				// tag completed, resolve pending
				globals.resolvers[ data.complete ]( {
					tag: data.complete,
					status: 'complete'
				} );
				res.send( data );
				return next();
			}

			if ( globals.resolvers[ data.reject ] ) {
				globals.resolvers[ data.reject ]( {
					tag: data.reject,
					status: 'reject'
				} );
				res.send( data );
				return next();
			}

			if ( globals.pending[ data.check ] ) {
				globals.pending[ data.check ].then( function ( data ) {
					res.send( data );
					next();
				} );
			} else if ( data.check ) {
				globals.pending[ data.check ] = new Promise( ( resolve ) => {
					globals.resolvers[ data.check ] = resolve;
				} );
				res.send( {
					tag: data.check,
					status: 'new'
				} );
				return next();
			} else {
				return next( new Error( 'Unrecognized tag server request: ' + JSON.stringify( data ) ) );
			}
		} );
	}

	close() {
		this.server.close();
	}

	start( success ) {
		this.server.listen( this.unixSocketPath, success );
	}
}

( () => {
	let server;
	process.on( 'message', ( msg ) => {
		if ( msg.config ) {
			if ( server ) {
				process.send( { error: 'Already initialized' } );
			} else {
				server = new Server( msg.config );
				server.server.on( 'error', ( err ) => {
					process.send( { error: err.message } );
					server = undefined;
				} );

				server.start(
					() => {
						console.log( 'Server initialized' );
						process.send( { initialized: true } );
					}
				);
			}
		}
		// TODO: figure out why the process channel is closed when cucumber tries to send
		// the exit signal...
		if ( msg.exit && server ) {
			server.close();
		}
	} );
} )();
