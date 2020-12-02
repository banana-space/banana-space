/*!
 * VisualEditor DataModel Fake socket.io-like class for testing
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

// Fake socket class, with limited API
ve.dm.FakeSocket = function VeDmFakeSocket( server, query ) {
	server.sockets.sockets.push( this );
	this.handlers = new Map();
	this.rooms = new Set();
	this.pending = [];
	this.handshake = { query: query, url: 'fakeurl' };
	this.client = { conn: { remoteAddress: 'fakeaddress' } };
	this.storedDataForDoc = {};
	this.dataForDoc = undefined;
	this.log = [];
};

OO.initClass( ve.dm.FakeSocket );

ve.dm.FakeSocket.static.makeServer = function () {
	var getRoom, reset,
		sockets = [],
		log = [];

	getRoom = function ( roomName ) {
		return { emit: function () {
			var i, socket;
			for ( i = 0; i < sockets.length; i++ ) {
				socket = sockets[ i ];
				if ( socket.rooms.has( roomName ) ) {
					socket.emit.apply( socket, arguments );
				}
			}
		} };
	};
	reset = function () {
		sockets.length = 0;
		log.length = 0;
	};
	return { sockets: { sockets: sockets, log: log, in: getRoom, reset: reset } };
};

ve.dm.FakeSocket.prototype.join = function ( roomName ) {
	this.rooms.add( roomName );
};

ve.dm.FakeSocket.prototype.emit = function ( eventName ) {
	var i,
		// eslint-disable-next-line es/no-array-from
		args = Array.from( arguments ).slice( 1 ),
		handlers = this.handlers.get( eventName ) || [];
	for ( i = 0; i < handlers.length; i++ ) {
		this.pending.push( handlers[ i ].apply( null, args ) );
	}
};

ve.dm.FakeSocket.prototype.on = function ( eventName, handler ) {
	if ( !this.handlers.has( eventName ) ) {
		this.handlers.set( eventName, [] );
	}
	this.handlers.get( eventName ).push( handler );
};

ve.dm.FakeSocket.prototype.receive = function ( eventName ) {
	var i,
		// eslint-disable-next-line es/no-array-from
		args = Array.from( arguments ).slice( 1 ),
		handlers = this.handlers.get( eventName ) || [];
	for ( i = 0; i < handlers.length; i++ ) {
		this.pending.push( handlers[ i ].apply( null, args ) );
	}
};

ve.dm.FakeSocket.prototype.wait = function () {
	return Promise.all( this.pending );
};
