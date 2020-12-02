( function () {
	QUnit.module( 'mediawiki.Uri', QUnit.newMwEnvironment( {
		setup: function () {
			this.mwUriOrg = mw.Uri;
			mw.Uri = mw.UriRelative( 'http://example.org/w/index.php' );
		},
		teardown: function () {
			mw.Uri = this.mwUriOrg;
			delete this.mwUriOrg;
		}
	} ) );

	[ true, false ].forEach( function ( strictMode ) {
		QUnit.test( 'Basic construction and properties (' + ( strictMode ? '' : 'non-' ) + 'strict mode)', function ( assert ) {
			var uriString, uri;
			uriString = 'http://www.ietf.org/rfc/rfc2396.txt';
			uri = new mw.Uri( uriString, {
				strictMode: strictMode
			} );

			assert.deepEqual(
				{
					protocol: uri.protocol,
					host: uri.host,
					port: uri.port,
					path: uri.path,
					query: uri.query,
					fragment: uri.fragment
				}, {
					protocol: 'http',
					host: 'www.ietf.org',
					port: undefined,
					path: '/rfc/rfc2396.txt',
					query: {},
					fragment: undefined
				},
				'basic object properties'
			);

			assert.deepEqual(
				{
					userInfo: uri.getUserInfo(),
					authority: uri.getAuthority(),
					hostPort: uri.getHostPort(),
					queryString: uri.getQueryString(),
					relativePath: uri.getRelativePath(),
					toString: uri.toString()
				},
				{
					userInfo: '',
					authority: 'www.ietf.org',
					hostPort: 'www.ietf.org',
					queryString: '',
					relativePath: '/rfc/rfc2396.txt',
					toString: uriString
				},
				'construct composite components of URI on request'
			);
		} );
	} );

	QUnit.test( 'Constructor( String[, Object ] )', function ( assert ) {
		var uri;

		uri = new mw.Uri( 'http://www.example.com/dir/?m=foo&m=bar&n=1', {
			overrideKeys: true
		} );

		// Strict comparison to assert that numerical values stay strings
		assert.strictEqual( uri.query.n, '1', 'Simple parameter with overrideKeys:true' );
		assert.strictEqual( uri.query.m, 'bar', 'Last key overrides earlier keys with overrideKeys:true' );

		uri = new mw.Uri( 'http://www.example.com/dir/?m=foo&m=bar&n=1', {
			overrideKeys: false
		} );

		assert.strictEqual( uri.query.n, '1', 'Simple parameter with overrideKeys:false' );
		assert.strictEqual( uri.query.m[ 0 ], 'foo', 'Order of multi-value parameters with overrideKeys:true' );
		assert.strictEqual( uri.query.m[ 1 ], 'bar', 'Order of multi-value parameters with overrideKeys:true' );
		assert.strictEqual( uri.query.m.length, 2, 'Number of mult-value field is correct' );

		uri = new mw.Uri( 'ftp://usr:pwd@192.0.2.16/' );

		assert.deepEqual(
			{
				protocol: uri.protocol,
				user: uri.user,
				password: uri.password,
				host: uri.host,
				port: uri.port,
				path: uri.path,
				query: uri.query,
				fragment: uri.fragment
			},
			{
				protocol: 'ftp',
				user: 'usr',
				password: 'pwd',
				host: '192.0.2.16',
				port: undefined,
				path: '/',
				query: {},
				fragment: undefined
			},
			'Parse an ftp URI correctly with user and password'
		);

		uri = new mw.Uri( 'http://example.com/?foo[1]=b&foo[0]=a&foo[]=c' );

		assert.deepEqual(
			uri.query,
			{
				'foo[1]': 'b',
				'foo[0]': 'a',
				'foo[]': 'c'
			},
			'Array query parameters parsed as normal with arrayParams:false'
		);

		assert.throws(
			function () {
				return new mw.Uri( 'glaswegian penguins' );
			},
			function ( e ) {
				return e.message === 'Bad constructor arguments';
			},
			'throw error on non-URI as argument to constructor'
		);

		assert.throws(
			function () {
				return new mw.Uri( 'example.com/bar/baz', {
					strictMode: true
				} );
			},
			function ( e ) {
				return e.message === 'Bad constructor arguments';
			},
			'throw error on URI without protocol or // or leading / in strict mode'
		);

		uri = new mw.Uri( 'example.com/bar/baz', {
			strictMode: false
		} );
		assert.strictEqual( uri.toString(), 'http://example.com/bar/baz', 'normalize URI without protocol or // in loose mode' );

		uri = new mw.Uri( 'http://example.com/index.php?key=key&hasOwnProperty=hasOwnProperty&constructor=constructor&watch=watch' );
		assert.deepEqual(
			uri.query,
			{
				key: 'key',
				constructor: 'constructor',
				hasOwnProperty: 'hasOwnProperty',
				watch: 'watch'
			},
			'Keys in query strings support names of Object prototypes (bug T114344)'
		);
	} );

	QUnit.test( 'Constructor( Object )', function ( assert ) {
		var uri = new mw.Uri( {
			protocol: 'http',
			host: 'www.foo.local',
			path: '/this'
		} );
		assert.strictEqual( uri.toString(), 'http://www.foo.local/this', 'Basic properties' );

		uri = new mw.Uri( {
			protocol: 'http',
			host: 'www.foo.local',
			path: '/this',
			query: { hi: 'there' },
			fragment: 'blah'
		} );
		assert.strictEqual( uri.toString(), 'http://www.foo.local/this?hi=there#blah', 'More complex properties' );

		assert.throws(
			function () {
				return new mw.Uri( {
					protocol: 'http',
					host: 'www.foo.local'
				} );
			},
			function ( e ) {
				return e.message === 'Bad constructor arguments';
			},
			'Construction failed when missing required properties'
		);
	} );

	QUnit.test( 'Constructor( empty[, Object ] )', function ( assert ) {
		var testuri, MyUri, uri;

		testuri = 'http://example.org/w/index.php?a=1&a=2';
		MyUri = mw.UriRelative( testuri );

		uri = new MyUri();
		assert.strictEqual( uri.toString(), testuri, 'no arguments' );

		uri = new MyUri( undefined );
		assert.strictEqual( uri.toString(), testuri, 'undefined' );

		uri = new MyUri( null );
		assert.strictEqual( uri.toString(), testuri, 'null' );

		uri = new MyUri( '' );
		assert.strictEqual( uri.toString(), testuri, 'empty string' );

		uri = new MyUri( null, { overrideKeys: true } );
		assert.deepEqual( uri.query, { a: '2' }, 'null, with options' );
	} );

	QUnit.test( 'Properties', function ( assert ) {
		var uriBase, uri;

		uriBase = new mw.Uri( 'http://en.wiki.local/w/api.php' );

		uri = uriBase.clone();
		uri.fragment = 'frag';
		assert.strictEqual( uri.toString(), 'http://en.wiki.local/w/api.php#frag', 'add a fragment' );
		uri.fragment = 'café';
		assert.strictEqual( uri.toString(), 'http://en.wiki.local/w/api.php#caf%C3%A9', 'fragment is url-encoded' );

		uri = uriBase.clone();
		uri.host = 'fr.wiki.local';
		uri.port = '8080';
		assert.strictEqual( uri.toString(), 'http://fr.wiki.local:8080/w/api.php', 'change host and port' );

		uri = uriBase.clone();
		uri.query.foo = 'bar';
		assert.strictEqual( uri.toString(), 'http://en.wiki.local/w/api.php?foo=bar', 'add query arguments' );

		delete uri.query.foo;
		assert.strictEqual( uri.toString(), 'http://en.wiki.local/w/api.php', 'delete query arguments' );

		uri = uriBase.clone();
		uri.query.foo = 'bar';
		assert.strictEqual( uri.toString(), 'http://en.wiki.local/w/api.php?foo=bar', 'extend query arguments' );
		uri.extend( {
			foo: 'quux',
			pif: 'paf'
		} );
		assert.strictEqual( uri.toString().indexOf( 'foo=quux' ) !== -1, true, 'extend query arguments' );
		assert.strictEqual( uri.toString().indexOf( 'foo=bar' ) !== -1, false, 'extend query arguments' );
		assert.strictEqual( uri.toString().indexOf( 'pif=paf' ) !== -1, true, 'extend query arguments' );
	} );

	QUnit.test( '.getQueryString()', function ( assert ) {
		var uri = new mw.Uri( 'http://search.example.com/?q=uri' );

		assert.deepEqual(
			{
				protocol: uri.protocol,
				host: uri.host,
				port: uri.port,
				path: uri.path,
				query: uri.query,
				fragment: uri.fragment,
				queryString: uri.getQueryString()
			},
			{
				protocol: 'http',
				host: 'search.example.com',
				port: undefined,
				path: '/',
				query: { q: 'uri' },
				fragment: undefined,
				queryString: 'q=uri'
			},
			'basic object properties'
		);

		uri = new mw.Uri( 'https://example.com/mw/index.php?title=Sandbox/7&other=Sandbox/7&foo' );
		assert.strictEqual(
			uri.getQueryString(),
			'title=Sandbox/7&other=Sandbox%2F7&foo',
			'title parameter is escaped the wiki-way'
		);

	} );

	QUnit.test( 'arrayParams', function ( assert ) {
		var uri1, uri2, uri3, expectedQ, expectedS,
			uriMissing, expectedMissingQ, expectedMissingS,
			uriWeird, expectedWeirdQ, expectedWeirdS;

		uri1 = new mw.Uri( 'http://example.com/?foo[]=a&foo[]=b&foo[]=c', { arrayParams: true } );
		uri2 = new mw.Uri( 'http://example.com/?foo[0]=a&foo[1]=b&foo[2]=c', { arrayParams: true } );
		uri3 = new mw.Uri( 'http://example.com/?foo[1]=b&foo[0]=a&foo[]=c', { arrayParams: true } );
		expectedQ = { foo: [ 'a', 'b', 'c' ] };
		expectedS = 'foo%5B0%5D=a&foo%5B1%5D=b&foo%5B2%5D=c';

		assert.deepEqual( uri1.query, expectedQ,
			'array query parameters are parsed (implicit indexes)' );
		assert.deepEqual( uri1.getQueryString(), expectedS,
			'array query parameters are encoded (always with explicit indexes)' );
		assert.deepEqual( uri2.query, expectedQ,
			'array query parameters are parsed (explicit indexes)' );
		assert.deepEqual( uri2.getQueryString(), expectedS,
			'array query parameters are encoded (always with explicit indexes)' );
		assert.deepEqual( uri3.query, expectedQ,
			'array query parameters are parsed (mixed indexes, out of order)' );
		assert.deepEqual( uri3.getQueryString(), expectedS,
			'array query parameters are encoded (always with explicit indexes)' );

		uriMissing = new mw.Uri( 'http://example.com/?foo[0]=a&foo[2]=c', { arrayParams: true } );
		// eslint-disable-next-line no-sparse-arrays
		expectedMissingQ = { foo: [ 'a', , 'c' ] };
		expectedMissingS = 'foo%5B0%5D=a&foo%5B2%5D=c';

		assert.deepEqual( uriMissing.query, expectedMissingQ,
			'array query parameters are parsed (missing array item)' );
		assert.deepEqual( uriMissing.getQueryString(), expectedMissingS,
			'array query parameters are encoded (missing array item)' );

		uriWeird = new mw.Uri( 'http://example.com/?foo[0]=a&foo[1][1]=b&foo[x]=c', { arrayParams: true } );
		expectedWeirdQ = { foo: [ 'a' ], 'foo[1][1]': 'b', 'foo[x]': 'c' };
		expectedWeirdS = 'foo%5B0%5D=a&foo%5B1%5D%5B1%5D=b&foo%5Bx%5D=c';

		assert.deepEqual( uriWeird.query, expectedWeirdQ,
			'array query parameters are parsed (multi-dimensional or associative arrays are ignored)' );
		assert.deepEqual( uriWeird.getQueryString(), expectedWeirdS,
			'array query parameters are encoded (multi-dimensional or associative arrays are ignored)' );
	} );

	QUnit.test( '.clone()', function ( assert ) {
		var original, clone;

		original = new mw.Uri( 'http://foo.example.org/index.php?one=1&two=2' );
		clone = original.clone();

		assert.deepEqual( clone, original, 'clone has equivalent properties' );
		assert.strictEqual( original.toString(), clone.toString(), 'toString matches original' );

		assert.notStrictEqual( clone, original, 'clone is a different object when compared by reference' );

		clone.host = 'bar.example.org';
		assert.notEqual( original.host, clone.host, 'manipulating clone did not effect original' );
		assert.notEqual( original.toString(), clone.toString(), 'Stringified url no longer matches original' );

		clone.query.three = 3;

		assert.deepEqual(
			original.query,
			{ one: '1', two: '2' },
			'Properties is deep cloned (T39708)'
		);
	} );

	QUnit.test( '.toString() after query manipulation', function ( assert ) {
		var uri;

		uri = new mw.Uri( 'http://www.example.com/dir/?m=foo&m=bar&n=1', {
			overrideKeys: true
		} );

		uri.query.n = [ 'x', 'y', 'z' ];

		// Verify parts and total length instead of entire string because order
		// of iteration can vary.
		assert.strictEqual( uri.toString().indexOf( 'm=bar' ) !== -1, true, 'toString preserves other values' );
		assert.strictEqual( uri.toString().indexOf( 'n=x&n=y&n=z' ) !== -1, true, 'toString parameter includes all values of an array query parameter' );
		assert.strictEqual( uri.toString().length, 'http://www.example.com/dir/?m=bar&n=x&n=y&n=z'.length, 'toString matches expected string' );

		uri = new mw.Uri( 'http://www.example.com/dir/?m=foo&m=bar&n=1', {
			overrideKeys: false
		} );

		// Change query values
		uri.query.n = [ 'x', 'y', 'z' ];

		// Verify parts and total length instead of entire string because order
		// of iteration can vary.
		assert.strictEqual( uri.toString().indexOf( 'm=foo&m=bar' ) !== -1, true, 'toString preserves other values' );
		assert.strictEqual( uri.toString().indexOf( 'n=x&n=y&n=z' ) !== -1, true, 'toString parameter includes all values of an array query parameter' );
		assert.strictEqual( uri.toString().length, 'http://www.example.com/dir/?m=foo&m=bar&n=x&n=y&n=z'.length, 'toString matches expected string' );

		// Remove query values
		uri.query.m.splice( 0, 1 );
		delete uri.query.n;

		assert.strictEqual( uri.toString(), 'http://www.example.com/dir/?m=bar', 'deletion properties' );

		// Remove more query values, leaving an empty array
		uri.query.m.splice( 0, 1 );
		assert.strictEqual( uri.toString(), 'http://www.example.com/dir/', 'empty array value is ommitted' );
	} );

	QUnit.test( 'Variable defaultUri', function ( assert ) {
		var uri,
			href = 'http://example.org/w/index.php#here',
			UriClass = mw.UriRelative( function () {
				return href;
			} );

		uri = new UriClass();
		assert.deepEqual(
			{
				protocol: uri.protocol,
				user: uri.user,
				password: uri.password,
				host: uri.host,
				port: uri.port,
				path: uri.path,
				query: uri.query,
				fragment: uri.fragment
			},
			{
				protocol: 'http',
				user: undefined,
				password: undefined,
				host: 'example.org',
				port: undefined,
				path: '/w/index.php',
				query: {},
				fragment: 'here'
			},
			'basic object properties'
		);

		// Default URI may change, e.g. via history.replaceState, pushState or location.hash (T74334)
		href = 'https://example.com/wiki/Foo?v=2';
		uri = new UriClass();
		assert.deepEqual(
			{
				protocol: uri.protocol,
				user: uri.user,
				password: uri.password,
				host: uri.host,
				port: uri.port,
				path: uri.path,
				query: uri.query,
				fragment: uri.fragment
			},
			{
				protocol: 'https',
				user: undefined,
				password: undefined,
				host: 'example.com',
				port: undefined,
				path: '/wiki/Foo',
				query: { v: '2' },
				fragment: undefined
			},
			'basic object properties'
		);
	} );

	QUnit.test( 'Advanced URL', function ( assert ) {
		var uri, queryString, relativePath;

		uri = new mw.Uri( 'http://auth@www.example.com:81/dir/dir.2/index.htm?q1=0&&test1&test2=value+%28escaped%29#caf%C3%A9' );

		assert.deepEqual(
			{
				protocol: uri.protocol,
				user: uri.user,
				password: uri.password,
				host: uri.host,
				port: uri.port,
				path: uri.path,
				query: uri.query,
				fragment: uri.fragment
			},
			{
				protocol: 'http',
				user: 'auth',
				password: undefined,
				host: 'www.example.com',
				port: '81',
				path: '/dir/dir.2/index.htm',
				query: { q1: '0', test1: null, test2: 'value (escaped)' },
				fragment: 'café'
			},
			'basic object properties'
		);

		assert.strictEqual( uri.getUserInfo(), 'auth', 'user info' );

		assert.strictEqual( uri.getAuthority(), 'auth@www.example.com:81', 'authority equal to auth@hostport' );

		assert.strictEqual( uri.getHostPort(), 'www.example.com:81', 'hostport equal to host:port' );

		queryString = uri.getQueryString();
		assert.strictEqual( queryString.indexOf( 'q1=0' ) !== -1, true, 'query param with numbers' );
		assert.strictEqual( queryString.indexOf( 'test1' ) !== -1, true, 'query param with null value is included' );
		assert.strictEqual( queryString.indexOf( 'test1=' ) !== -1, false, 'query param with null value does not generate equals sign' );
		assert.strictEqual( queryString.indexOf( 'test2=value+%28escaped%29' ) !== -1, true, 'query param is url escaped' );

		relativePath = uri.getRelativePath();
		assert.ok( relativePath.indexOf( uri.path ) >= 0, 'path in relative path' );
		assert.ok( relativePath.indexOf( uri.getQueryString() ) >= 0, 'query string in relative path' );
		assert.ok( relativePath.indexOf( mw.Uri.encode( uri.fragment ) ) >= 0, 'escaped fragment in relative path' );
	} );

	QUnit.test( 'Parse a uri with an @ symbol in the path and query', function ( assert ) {
		var uri = new mw.Uri( 'http://www.example.com/test@test?x=@uri&y@=uri&z@=@' );

		assert.deepEqual(
			{
				protocol: uri.protocol,
				user: uri.user,
				password: uri.password,
				host: uri.host,
				port: uri.port,
				path: uri.path,
				query: uri.query,
				fragment: uri.fragment,
				queryString: uri.getQueryString()
			},
			{
				protocol: 'http',
				user: undefined,
				password: undefined,
				host: 'www.example.com',
				port: undefined,
				path: '/test@test',
				query: { x: '@uri', 'y@': 'uri', 'z@': '@' },
				fragment: undefined,
				queryString: 'x=%40uri&y%40=uri&z%40=%40'
			},
			'basic object properties'
		);
	} );

	QUnit.test( 'Handle protocol-relative URLs', function ( assert ) {
		var UriRel, uri;

		UriRel = mw.UriRelative( 'glork://en.wiki.local/foo.php' );

		uri = new UriRel( '//en.wiki.local/w/api.php' );
		assert.strictEqual( uri.protocol, 'glork', 'create protocol-relative URLs with same protocol as document' );

		uri = new UriRel( '/foo.com' );
		assert.strictEqual( uri.toString(), 'glork://en.wiki.local/foo.com', 'handle absolute paths by supplying protocol and host from document in loose mode' );

		uri = new UriRel( 'http:/foo.com' );
		assert.strictEqual( uri.toString(), 'http://en.wiki.local/foo.com', 'handle absolute paths by supplying host from document in loose mode' );

		uri = new UriRel( '/foo.com', true );
		assert.strictEqual( uri.toString(), 'glork://en.wiki.local/foo.com', 'handle absolute paths by supplying protocol and host from document in strict mode' );

		uri = new UriRel( 'http:/foo.com', true );
		assert.strictEqual( uri.toString(), 'http://en.wiki.local/foo.com', 'handle absolute paths by supplying host from document in strict mode' );
	} );

	QUnit.test( 'T37658', function ( assert ) {
		var testProtocol, testServer, testPort, testPath, UriClass, uri, href;

		testProtocol = 'https://';
		testServer = 'foo.example.org';
		testPort = '3004';
		testPath = '/!1qy';

		UriClass = mw.UriRelative( testProtocol + testServer + '/some/path/index.html' );
		uri = new UriClass( testPath );
		href = uri.toString();
		assert.strictEqual( href, testProtocol + testServer + testPath, 'Root-relative URL gets host & protocol supplied' );

		UriClass = mw.UriRelative( testProtocol + testServer + ':' + testPort + '/some/path.php' );
		uri = new UriClass( testPath );
		href = uri.toString();
		assert.strictEqual( href, testProtocol + testServer + ':' + testPort + testPath, 'Root-relative URL gets host, protocol, and port supplied' );
	} );
}() );
