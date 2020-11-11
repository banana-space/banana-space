/*
 * This file is part of the MediaWiki extension MultimediaViewer.
 *
 * MultimediaViewer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * MultimediaViewer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with MultimediaViewer.  If not, see <http://www.gnu.org/licenses/>.
 */

( function ( mw, $ ) {
	QUnit.module( 'mmv.logging.PerformanceLogger', QUnit.newMwEnvironment() );

	function createFakeXHR( response ) {
		return {
			readyState: 0,
			open: $.noop,
			send: function () {
				var xhr = this;

				setTimeout( function () {
					xhr.readyState = 4;
					xhr.response = response;
					if ( $.isFunction( xhr.onreadystatechange ) ) {
						xhr.onreadystatechange();
					}
				}, 0 );
			}
		};
	}

	QUnit.test( 'recordEntry: basic', function ( assert ) {
		var performance = new mw.mmv.logging.PerformanceLogger(),
			fakeEventLog = { logEvent: this.sandbox.stub() },
			type = 'gender',
			total = 100,
			// we'll be waiting for 4 promises to complete
			asyncs = [ assert.async(), assert.async(), assert.async(), assert.async() ];

		this.sandbox.stub( performance, 'loadDependencies' ).returns( $.Deferred().resolve() );
		this.sandbox.stub( performance, 'isInSample' );
		performance.setEventLog( fakeEventLog );

		performance.isInSample.returns( false );

		performance.recordEntry( type, total ).then( null, function () {
			assert.strictEqual( fakeEventLog.logEvent.callCount, 0, 'No stats should be logged if not in sample' );
			asyncs.pop()();
		} );

		performance.isInSample.returns( true );

		performance.recordEntry( type, total ).then( null, function () {
			assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 0 ], 'MultimediaViewerNetworkPerformance', 'EventLogging schema is correct' );
			assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 1 ].type, type, 'type is correct' );
			assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 1 ].total, total, 'total is correct' );
			assert.strictEqual( fakeEventLog.logEvent.callCount, 1, 'Stats should be logged' );
			asyncs.pop()();
		} );

		performance.recordEntry( type, total, 'URL' ).then( null, function () {
			assert.strictEqual( fakeEventLog.logEvent.callCount, 2, 'Stats should be logged' );
			asyncs.pop()();
		} );

		performance.recordEntry( type, total, 'URL' ).then( null, function () {
			assert.strictEqual( fakeEventLog.logEvent.callCount, 2, 'Stats should not be logged a second time for the same URL' );
			asyncs.pop()();
		} );
	} );

	QUnit.test( 'recordEntry: with Navigation Timing data', function ( assert ) {
		var fakeRequest,
			varnish1 = 'cp1061',
			varnish2 = 'cp3006',
			varnish3 = 'cp3005',
			varnish1hits = 0,
			varnish2hits = 2,
			varnish3hits = 1,
			xvarnish = '1754811951 1283049064, 1511828531, 1511828573 1511828528',
			xcache = varnish1 + ' miss (0), ' + varnish2 + ' miss (2), ' + varnish3 + ' frontend hit (1), malformed(5)',
			age = '12345',
			contentLength = '23456',
			urlHost = 'fail',
			date = 'Tue, 04 Feb 2014 11:11:50 GMT',
			timestamp = 1391512310,
			url = 'https://' + urlHost + '/balls.jpg',
			redirect = 500,
			dns = 2,
			tcp = 10,
			request = 25,
			response = 50,
			cache = 1,
			perfData = {
				initiatorType: 'xmlhttprequest',
				name: url,
				duration: 12345,
				redirectStart: 1000,
				redirectEnd: 1500,
				domainLookupStart: 2,
				domainLookupEnd: 4,
				connectStart: 50,
				connectEnd: 60,
				requestStart: 125,
				responseStart: 150,
				responseEnd: 200,
				fetchStart: 1
			},
			country = 'FR',
			type = 'image',
			performance = new mw.mmv.logging.PerformanceLogger(),
			status = 200,
			metered = true,
			bandwidth = 45.67,
			fakeEventLog = { logEvent: this.sandbox.stub() },
			done = assert.async();

		this.sandbox.stub( performance, 'loadDependencies' ).returns( $.Deferred().resolve() );
		performance.setEventLog( fakeEventLog );
		performance.schemaSupportsCountry = this.sandbox.stub().returns( true );

		this.sandbox.stub( performance, 'getWindowPerformance' ).returns( {
			getEntriesByName: function () {
				return [ perfData, {
					initiatorType: 'bogus',
					duration: 1234,
					name: url
				} ];
			}
		} );

		this.sandbox.stub( performance, 'getNavigatorConnection' ).returns( { metered: metered, bandwidth: bandwidth } );
		this.sandbox.stub( performance, 'isInSample' ).returns( true );

		fakeRequest = {
			getResponseHeader: function ( header ) {
				switch ( header ) {
					case 'X-Cache':
						return xcache;
					case 'X-Varnish':
						return xvarnish;
					case 'Age':
						return age;
					case 'Content-Length':
						return contentLength;
					case 'Date':
						return date;
				}
			},
			status: status
		};

		performance.setGeo( { country: country } );

		performance.recordEntry( type, 100, url, fakeRequest ).then( null, function () {
			assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 0 ], 'MultimediaViewerNetworkPerformance', 'EventLogging schema is correct' );
			assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 1 ].type, type, 'type is correct' );
			assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 1 ].varnish1, varnish1, 'varnish1 is correct' );
			assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 1 ].varnish2, varnish2, 'varnish2 is correct' );
			assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 1 ].varnish3, varnish3, 'varnish3 is correct' );
			assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 1 ].varnish4, undefined, 'varnish4 is undefined' );
			assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 1 ].varnish1hits, varnish1hits, 'varnish1hits is correct' );
			assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 1 ].varnish2hits, varnish2hits, 'varnish2hits is correct' );
			assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 1 ].varnish3hits, varnish3hits, 'varnish3hits is correct' );
			assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 1 ].varnish4hits, undefined, 'varnish4hits is undefined' );
			assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 1 ].XVarnish, xvarnish, 'XVarnish is correct' );
			assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 1 ].XCache, xcache, 'XCache is correct' );
			assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 1 ].age, parseInt( age, 10 ), 'age is correct' );
			assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 1 ].contentLength, parseInt( contentLength, 10 ), 'contentLength is correct' );
			assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 1 ].contentHost, window.location.host, 'contentHost is correct' );
			assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 1 ].urlHost, urlHost, 'urlHost is correct' );
			assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 1 ].timestamp, timestamp, 'timestamp is correct' );
			assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 1 ].total, perfData.duration, 'total is correct' );
			assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 1 ].redirect, redirect, 'redirect is correct' );
			assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 1 ].dns, dns, 'dns is correct' );
			assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 1 ].tcp, tcp, 'tcp is correct' );
			assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 1 ].request, request, 'request is correct' );
			assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 1 ].response, response, 'response is correct' );
			assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 1 ].cache, cache, 'cache is correct' );
			assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 1 ].country, country, 'country is correct' );
			assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 1 ].isHttps, true, 'isHttps is correct' );
			assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 1 ].status, status, 'status is correct' );
			assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 1 ].metered, metered, 'metered is correct' );
			assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 1 ].bandwidth, Math.round( bandwidth ), 'bandwidth is correct' );
			done();
		} );
	} );

	QUnit.test( 'recordEntry: with async extra stats', function ( assert ) {
		var performance = new mw.mmv.logging.PerformanceLogger(),
			fakeEventLog = { logEvent: this.sandbox.stub() },
			type = 'gender',
			total = 100,
			overriddenType = 'image',
			foo = 'bar',
			extraStatsPromise = $.Deferred(),
			clock = this.sandbox.useFakeTimers();

		this.sandbox.stub( performance, 'loadDependencies' ).returns( $.Deferred().resolve() );
		this.sandbox.stub( performance, 'isInSample' );
		performance.setEventLog( fakeEventLog );

		performance.isInSample.returns( true );

		performance.recordEntry( type, total, 'URL1', undefined, extraStatsPromise );

		assert.strictEqual( fakeEventLog.logEvent.callCount, 0, 'Stats should not be logged if the promise hasn\'t completed yet' );

		extraStatsPromise.reject();

		extraStatsPromise.then( null, function () {
			assert.strictEqual( fakeEventLog.logEvent.callCount, 1, 'Stats should be logged' );

			assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 0 ], 'MultimediaViewerNetworkPerformance', 'EventLogging schema is correct' );
			assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 1 ].type, type, 'type is correct' );
			assert.strictEqual( fakeEventLog.logEvent.getCall( 0 ).args[ 1 ].total, total, 'total is correct' );
		} );

		// make sure first promise is completed before recording another entry,
		// to make sure data in fakeEventLog doesn't suffer race conditions
		clock.tick( 10 );
		clock.restore();

		extraStatsPromise = $.Deferred();

		performance.recordEntry( type, total, 'URL2', undefined, extraStatsPromise );

		assert.strictEqual( fakeEventLog.logEvent.callCount, 1, 'Stats should not be logged if the promise hasn\'t been resolved yet' );

		extraStatsPromise.resolve( { type: overriddenType, foo: foo } );

		return extraStatsPromise.then( function () {
			assert.strictEqual( fakeEventLog.logEvent.callCount, 2, 'Stats should be logged' );

			assert.strictEqual( fakeEventLog.logEvent.getCall( 1 ).args[ 0 ], 'MultimediaViewerNetworkPerformance', 'EventLogging schema is correct' );
			assert.strictEqual( fakeEventLog.logEvent.getCall( 1 ).args[ 1 ].type, overriddenType, 'type is correct' );
			assert.strictEqual( fakeEventLog.logEvent.getCall( 1 ).args[ 1 ].total, total, 'total is correct' );
			assert.strictEqual( fakeEventLog.logEvent.getCall( 1 ).args[ 1 ].foo, foo, 'extra stat is correct' );
		} );
	} );

	QUnit.test( 'parseVarnishXCacheHeader', function ( assert ) {
		var varnish1 = 'cp1061',
			varnish2 = 'cp3006',
			varnish3 = 'cp3005',
			testString = varnish1 + ' miss (0), ' + varnish2 + ' miss (0), ' + varnish3 + ' frontend hit (1)',
			performance = new mw.mmv.logging.PerformanceLogger(),
			varnishXCache = performance.parseVarnishXCacheHeader( testString );

		assert.strictEqual( varnishXCache.varnish1, varnish1, 'First varnish server name extracted' );
		assert.strictEqual( varnishXCache.varnish2, varnish2, 'Second varnish server name extracted' );
		assert.strictEqual( varnishXCache.varnish3, varnish3, 'Third varnish server name extracted' );
		assert.strictEqual( varnishXCache.varnish4, undefined, 'Fourth varnish server is undefined' );
		assert.strictEqual( varnishXCache.varnish1hits, 0, 'First varnish hit count extracted' );
		assert.strictEqual( varnishXCache.varnish2hits, 0, 'Second varnish hit count extracted' );
		assert.strictEqual( varnishXCache.varnish3hits, 1, 'Third varnish hit count extracted' );
		assert.strictEqual( varnishXCache.varnish4hits, undefined, 'Fourth varnish hit count is undefined' );

		testString = varnish1 + ' miss (36), ' + varnish2 + ' miss (2)';
		varnishXCache = performance.parseVarnishXCacheHeader( testString );

		assert.strictEqual( varnishXCache.varnish1, varnish1, 'First varnish server name extracted' );
		assert.strictEqual( varnishXCache.varnish2, varnish2, 'Second varnish server name extracted' );
		assert.strictEqual( varnishXCache.varnish3, undefined, 'Third varnish server is undefined' );
		assert.strictEqual( varnishXCache.varnish1hits, 36, 'First varnish hit count extracted' );
		assert.strictEqual( varnishXCache.varnish2hits, 2, 'Second varnish hit count extracted' );
		assert.strictEqual( varnishXCache.varnish3hits, undefined, 'Third varnish hit count is undefined' );

		varnishXCache = performance.parseVarnishXCacheHeader( 'garbage' );
		assert.ok( $.isEmptyObject( varnishXCache ), 'Varnish cache results are empty' );
	} );

	QUnit.test( 'record()', function ( assert ) {
		var type = 'foo',
			url = 'http://example.com/',
			response = {},
			done = assert.async(),
			performance = new mw.mmv.logging.PerformanceLogger();

		performance.newXHR = function () { return createFakeXHR( response ); };

		performance.recordEntryDelayed = function ( recordType, _, recordUrl, recordRequest ) {
			assert.strictEqual( recordType, type, 'type is recorded correctly' );
			assert.strictEqual( recordUrl, url, 'url is recorded correctly' );
			assert.strictEqual( recordRequest.response, response, 'response is recorded correctly' );
			done();
		};

		return performance.record( type, url ).done( function ( recordResponse ) {
			assert.strictEqual( recordResponse, response, 'response is passed to callback' );
		} );
	} );

	QUnit.test( 'record() with old browser', function ( assert ) {
		var type = 'foo',
			url = 'http://example.com/',
			done = assert.async(),
			performance = new mw.mmv.logging.PerformanceLogger();

		performance.newXHR = function () { throw new Error( 'XMLHttpRequest? What\'s that?' ); };

		performance.record( type, url ).fail( function () {
			assert.ok( true, 'the promise is rejected when XMLHttpRequest is not supported' );
			done();
		} );
	} );

	QUnit.test( 'mw.mmv.logging.Api', function ( assert ) {
		var api,
			oldRecord = mw.mmv.logging.PerformanceLogger.prototype.recordJQueryEntryDelayed,
			oldAjax = mw.Api.prototype.ajax,
			ajaxCalled = false,
			fakeJqxhr = {};

		mw.Api.prototype.ajax = function () {
			ajaxCalled = true;
			return $.Deferred().resolve( {}, fakeJqxhr );
		};

		mw.mmv.logging.PerformanceLogger.prototype.recordJQueryEntryDelayed = function ( type, total, jqxhr ) {
			assert.strictEqual( type, 'foo', 'type was passed correctly' );
			assert.strictEqual( jqxhr, fakeJqxhr, 'jqXHR was passed correctly' );
		};

		api = new mw.mmv.logging.Api( 'foo' );

		api.ajax();

		assert.ok( ajaxCalled, 'parent ajax() function was called' );

		mw.mmv.logging.PerformanceLogger.prototype.recordJQueryEntryDelayed = oldRecord;
		mw.Api.prototype.ajax = oldAjax;
	} );
}( mediaWiki, jQuery ) );
