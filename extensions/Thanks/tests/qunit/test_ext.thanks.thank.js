QUnit.module( 'Thanks thank', QUnit.newMwEnvironment( {
	setup: function () {
		this.server = this.sandbox.useFakeServer();
		this.server.respondImmediately = true;
	}
} ) );

QUnit.test( 'thanked cookie', function ( assert ) {
	var $thankLink = $( '<a ' + mw.thanks.thanked.attrName + '="8" />' ),
		$thankLinkNonExisting = $( '<a ' + mw.thanks.thanked.attrName + '="13" />' );
	mw.cookie.set( mw.thanks.thanked.cookieName, escape( '17,11' ) );

	assert.deepEqual( mw.thanks.thanked.load(), [ '17', '11' ], 'gets cookie with two values' );

	// Makes the 0 100th element
	// eslint-disable-next-line no-restricted-properties
	mw.cookie.set( mw.thanks.thanked.cookieName, escape( '9,'.repeat( mw.thanks.thanked.maxHistory - 1 ) + '0' ) );

	assert.strictEqual( mw.thanks.thanked.load()[ mw.thanks.thanked.maxHistory - 1 ], '0', 'loads ' + mw.thanks.thanked.maxHistory + ' ids from a cookie' );
	mw.thanks.thanked.push( $thankLink );
	assert.strictEqual( mw.thanks.thanked.load().length, mw.thanks.thanked.maxHistory, 'cuts a cookie to ' + mw.thanks.thanked.maxHistory + ' values' );
	assert.strictEqual( mw.thanks.thanked.load()[ mw.thanks.thanked.maxHistory - 1 ], $thankLink.attr( mw.thanks.thanked.attrName ), 'adds a new value to cookie to the end' );

	assert.strictEqual( mw.thanks.thanked.contains( $thankLink ), true, 'cookie contains id and returns true' );
	assert.strictEqual( mw.thanks.thanked.contains( $thankLinkNonExisting ), false, 'cookie does not contains id and returns false' );
} );
QUnit.test( 'gets user gender', function ( assert ) {
	this.server.respond( /user1/, function ( request ) {
		request.respond( 200, { 'Content-Type': 'application/json' },
			'{"batchcomplete":"","query":{"users":[{"userid":1,"name":"user1","gender":"male"}]}}'
		);
	} );
	this.server.respond( /user2/, function ( request ) {
		request.respond( 200, { 'Content-Type': 'application/json' },
			'{"batchcomplete":"","query":{"users":[{"userid":2,"name":"user2","gender":"unknown"}]}}'
		);
	} );
	this.server.respond( /user3/, function ( request ) {
		request.respond( 200, { 'Content-Type': 'application/json' },
			'{"batchcomplete":"","query":{"users":[{"name":"user3","missing":""}]}}'
		);
	} );

	// eslint-disable-next-line vars-on-top
	var maleUser = mw.thanks.getUserGender( 'user1' ),
		unknownGenderUser = mw.thanks.getUserGender( 'user2' ),
		nonExistingUser = mw.thanks.getUserGender( 'user3' ),
		callbackDone = assert.async( 3 );

	maleUser.then( function ( recipientGender ) {
		assert.strictEqual( recipientGender, 'male', 'gets a proper gender for existing male user' );
		callbackDone();
	} );
	unknownGenderUser.then( function ( recipientGender ) {
		assert.strictEqual( recipientGender, 'unknown', 'gets a unknown gender for a existing unknown gender user' );
		callbackDone();
	} );
	nonExistingUser.then( function ( recipientGender ) {
		assert.strictEqual( recipientGender, 'unknown', 'gets a unknown gender for non-existing user' );
		callbackDone();
	} );
} );
