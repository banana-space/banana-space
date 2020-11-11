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

( function ( mw, $, oo ) {
	var PL;

	/**
	 * Measures the network performance
	 * See <https://meta.wikimedia.org/wiki/Schema:MultimediaViewerNetworkPerformance>
	 *
	 * @class mw.mmv.logging.PerformanceLogger
	 * @extends mw.mmv.logging.Logger
	 * @constructor
	 */
	function PerformanceLogger() {}

	oo.inheritClass( PerformanceLogger, mw.mmv.logging.Logger );

	PL = PerformanceLogger.prototype;

	/**
	 * @override
	 * @inheritdoc
	 */
	PL.samplingFactor = mw.config.get( 'wgMultimediaViewer' ).networkPerformanceSamplingFactor;

	/**
	 * @override
	 * @inheritdoc
	 */
	PL.schema = 'MultimediaViewerNetworkPerformance';

	/**
	 * Global setup that should be done while the page loads
	 */
	PL.init = function () {
		var performance = this.getWindowPerformance();

		// by default logging is cut off after 150 resources, which is not enough in debug mode
		// only supported by IE
		if ( mw.config.get( 'debug' ) && performance && performance.setResourceTimingBufferSize ) {
			performance.setResourceTimingBufferSize( 500 );
		}
	};

	/**
	 * Gather network performance for a given URL
	 * Will only run on a sample of users/requests. Avoid using this on URLs that aren't
	 * cached by the browser, as it will consume unnecessary bandwidth for the user.
	 *
	 * @param {string} type the type of request to be measured
	 * @param {string} url URL to be measured
	 * @param {jQuery.Deferred.<string>} [extraStatsDeferred] A promise which resolves to the extra stats.
	 * @return {jQuery.Promise} A promise that resolves when the contents of the URL have been fetched
	 */
	PL.record = function ( type, url, extraStatsDeferred ) {
		var deferred = $.Deferred(),
			request,
			perf = this,
			start;

		try {
			request = this.newXHR();

			request.onprogress = function ( e ) {
				var percent;

				if ( e.lengthComputable ) {
					percent = ( e.loaded / e.total ) * 100;
				}

				deferred.notify( request.response, percent );
			};

			request.onreadystatechange = function () {
				var total = $.now() - start;

				if ( request.readyState === 4 ) {
					deferred.notify( request.response, 100 );
					deferred.resolve( request.response );
					perf.recordEntryDelayed( type, total, url, request, extraStatsDeferred );
				}
			};

			start = $.now();
			request.open( 'GET', url, true );
			request.send();
		} catch ( e ) {
			// old browser not supporting XMLHttpRequest or CORS, or CORS is not permitted
			return deferred.reject();
		}

		return deferred;
	};

	/**
	 * Records network performance results for a given url
	 * Will record if enough data is present and it's not a local cache hit
	 *
	 * @param {string} type the type of request to be measured
	 * @param {number} total the total load time tracked with a basic technique
	 * @param {string} url URL of that was measured
	 * @param {XMLHttpRequest} request HTTP request that just completed
	 * @param {jQuery.Deferred.<string>} [extraStatsDeferred] A promise which resolves to extra stats to be included.
	 * @return {jQuery.Promise}
	 */
	PL.recordEntry = function ( type, total, url, request, extraStatsDeferred ) {
		var matches,
			logger = this,
			stats = { type: type,
				contentHost: window.location.host,
				isHttps: window.location.protocol === 'https:',
				total: total },
			connection = this.getNavigatorConnection();

		if ( !this.performanceChecked ) {
			this.performanceChecked = {};
		}

		if ( url && url.length ) {
			// There is no need to measure the same url more than once
			if ( url in this.performanceChecked ) {
				return $.Deferred().reject();
			}

			this.performanceChecked[ url ] = true;

			matches = url.match( /^https?:\/\/([^/?#]+)(?:[/?#]|$)/i );
			stats.isHttps = url.indexOf( 'https' ) === 0;
		}

		if ( !matches || matches.length !== 2 ) {
			stats.urlHost = stats.contentHost;
		} else {
			stats.urlHost = matches[ 1 ];
		}

		this.populateStatsFromXhr( stats, request );
		this.populateStatsFromPerformance( stats, url );

		// Add connection information if there's any
		if ( connection ) {
			if ( connection.bandwidth ) {
				if ( connection.bandwidth === Infinity ) {
					stats.bandwidth = -1;
				} else {
					stats.bandwidth = Math.round( connection.bandwidth );
				}
			}

			if ( connection.metered ) {
				stats.metered = connection.metered;
			}
		}

		return ( extraStatsDeferred || $.Deferred().reject() ).done( function ( extraStats ) {
			stats = $.extend( stats, extraStats );
		} ).always( function () {
			logger.log( stats );
		} );
	};

	/**
	 * Processes an XMLHttpRequest (or jqXHR) object
	 *
	 * @param {Object} stats stats object to extend with additional statistics fields
	 * @param {XMLHttpRequest} request
	 */
	PL.populateStatsFromXhr = function ( stats, request ) {
		var age,
			contentLength,
			xcache,
			xvarnish,
			varnishXCache,
			lastModified;

		if ( !request ) {
			return;
		}

		stats.status = request.status;

		// Chrome disallows header access for CORS image requests, even if the responose has the
		// proper header :-/
		contentLength = request.getResponseHeader( 'Content-Length' );
		if ( contentLength === null ) {
			return;
		}

		xcache = request.getResponseHeader( 'X-Cache' );
		if ( xcache ) {
			stats.XCache = xcache;
			varnishXCache = this.parseVarnishXCacheHeader( xcache );

			$.each( varnishXCache, function ( key, value ) {
				stats[ key ] = value;
			} );
		}

		xvarnish = request.getResponseHeader( 'X-Varnish' );
		if ( xvarnish ) {
			stats.XVarnish = xvarnish;
		}

		stats.contentLength = parseInt( contentLength, 10 );

		age = parseInt( request.getResponseHeader( 'Age' ), 10 );
		if ( !isNaN( age ) ) {
			stats.age = age;
		}

		stats.timestamp = new Date( request.getResponseHeader( 'Date' ) ).getTime() / 1000;

		lastModified = request.getResponseHeader( 'Last-Modified' );
		if ( lastModified ) {
			stats.lastModified = new Date( lastModified ).getTime() / 1000;
		}
	};

	/**
	 * Populates statistics based on the Request Timing API
	 *
	 * @param {Object} stats
	 * @param {string} url
	 */
	PL.populateStatsFromPerformance = function ( stats, url ) {
		var performance = this.getWindowPerformance(),
			timingEntries, timingEntry;

		// If we're given an xhr and we have access to the Navigation Timing API, use it
		if ( performance && performance.getEntriesByName ) {
			// This could be tricky as we need to match encoding (the Request Timing API uses
			// percent-encoded UTF-8). The main use case we are interested in is thumbnails and
			// jQuery AJAX. jQuery uses encodeURIComponent to construct URL parameters, and
			// thumbnail URLs come from MediaWiki API which also encodes them, so both should be
			// all right.
			timingEntries = performance.getEntriesByName( url );

			if ( timingEntries.length ) {
				// Let's hope it's the first request for the given URL we are interested in.
				// This could fail in exotic cases (e.g. we send an AJAX request for a thumbnail,
				// but it exists on the page as a normal thumbnail with the exact same size),
				// but it's unlikely.
				timingEntry = timingEntries[ 0 ];

				stats.total = Math.round( timingEntry.duration );
				stats.redirect = Math.round( timingEntry.redirectEnd - timingEntry.redirectStart );
				stats.dns = Math.round( timingEntry.domainLookupEnd - timingEntry.domainLookupStart );
				stats.tcp = Math.round( timingEntry.connectEnd - timingEntry.connectStart );
				stats.request = Math.round( timingEntry.responseStart - timingEntry.requestStart );
				stats.response = Math.round( timingEntry.responseEnd - timingEntry.responseStart );
				stats.cache = Math.round( timingEntry.domainLookupStart - timingEntry.fetchStart );
			} else if ( performance.getEntriesByType( 'resource' ).length === 150 && this.isEnabled() ) {
				// browser stops logging after 150 entries
				mw.log( 'performance buffer full, results are probably incorrect' );
			}
		}
	};

	/**
	 * Like recordEntry, but takes a jqXHR argument instead of a normal XHR one.
	 * Due to the way some parameters are retrieved, this will work best if the context option
	 * for the ajax request was not used.
	 *
	 * @param {string} type the type of request to be measured
	 * @param {number} total the total load time tracked with a basic technique
	 * @param {jqXHR} jqxhr
	 */
	PL.recordJQueryEntry = function ( type, total, jqxhr ) {
		var perf = this;

		// We take advantage of the fact that the context of the jqXHR deferred is the AJAX
		// settings object. The deferred has already resolved so chaining to it does not influence
		// the timing.
		jqxhr.done( function () {
			var url;

			if ( !this.url ) {
				mw.log.warn( 'Cannot find URL - did you use context option?' );
			} else {
				url = this.url;
				// The performance API returns absolute URLs, but the one in the settings object is
				// usually relative.
				if ( !url.match( /^(\w+:)?\/\// ) ) {
					url = location.protocol + '//' + location.host + url;
				}
			}

			if ( this.crossDomain && this.dataType === 'jsonp' ) {
				// Cross-domain jQuery requests return a fake jqXHR object which is useless and
				// would only cause logging errors.
				jqxhr = undefined;
			}

			// jQuery does not expose the original XHR object, but the jqXHR wrapper is similar
			// enogh that we will probably get away by passing it instead.
			perf.recordEntry( type, total, url, jqxhr );
		} );
	};

	/**
	 * Records network performance results for a given url
	 * Will record if enough data is present and it's not a local cache hit
	 * Will run after a delay to make sure the window.performance entry is present
	 *
	 * @param {string} type the type of request to be measured
	 * @param {number} total the total load time tracked with a basic technique
	 * @param {string} url URL of that was measured
	 * @param {XMLHttpRequest} request HTTP request that just completed
	 * @param {jQuery.Promise.<string>} extraStatsDeferred A promise which resolves to extra stats.
	 */
	PL.recordEntryDelayed = function ( type, total, url, request, extraStatsDeferred ) {
		var perf = this;

		// The timeout is necessary because if there's an entry in window.performance,
		// it hasn't been added yet at this point
		setTimeout( function () {
			perf.recordEntry( type, total, url, request, extraStatsDeferred );
		}, 0 );
	};

	/**
	 * Like recordEntryDelayed, but for jQuery AJAX requests.
	 *
	 * @param {string} type the type of request to be measured
	 * @param {number} total the total load time tracked with a basic technique
	 * @param {jqXHR} jqxhr
	 */
	PL.recordJQueryEntryDelayed = function ( type, total, jqxhr ) {
		var perf = this;

		// The timeout is necessary because if there's an entry in window.performance,
		// it hasn't been added yet at this point
		setTimeout( function () {
			perf.recordJQueryEntry( type, total, jqxhr );
		}, 0 );
	};

	/**
	 * Parses an X-Cache header from Varnish and extracts varnish information
	 *
	 * @param {string} header The X-Cache header from the request
	 * @return {Object} The parsed X-Cache data
	 */
	PL.parseVarnishXCacheHeader = function ( header ) {
		var parts,
			part,
			subparts,
			i,
			results = {},
			matches;

		if ( !header || !header.length ) {
			return results;
		}

		parts = header.split( ',' );

		for ( i = 0; i < parts.length; i++ ) {
			part = parts[ i ];
			subparts = part.trim().split( ' ' );

			// If the subparts aren't space-separated, it's an unknown format, skip
			if ( subparts.length < 2 ) {
				continue;
			}

			matches = part.match( /\(([0-9]+)\)/ );

			// If there is no number between parenthesis for a given server
			// it's an unknown format, skip
			if ( !matches || matches.length !== 2 ) {
				continue;
			}

			results[ 'varnish' + ( i + 1 ) ] = subparts[ 0 ];
			results[ 'varnish' + ( i + 1 ) + 'hits' ] = parseInt( matches[ 1 ], 10 );
		}

		return results;
	};

	/**
	 * Returns the window's Performance object
	 * Allows us to override for unit tests
	 *
	 * @return {Object} The window's Performance object
	 */
	PL.getWindowPerformance = function () {
		return window.performance;
	};

	/**
	 * Returns the navigator's Connection object
	 * Allows us to override for unit tests
	 *
	 * @return {Object} The navigator's Connection object
	 */
	PL.getNavigatorConnection = function () {
		return navigator.connection || navigator.mozConnection || navigator.webkitConnection;
	};

	/**
	 * Returns a new XMLHttpRequest object
	 * Allows us to override for unit tests
	 *
	 * @return {XMLHttpRequest} New XMLHttpRequest
	 */
	PL.newXHR = function () {
		return new XMLHttpRequest();
	};

	/**
	 * @override
	 * @inheritdoc
	 */
	PL.log = function ( data ) {
		var trackedWidths = mw.mmv.ThumbnailWidthCalculator.prototype.defaultOptions.widthBuckets.slice( 0 );
		trackedWidths.push( 600 ); // Most common non-bucket size

		// Track thumbnail load time with statsv, sampled
		if ( this.isInSample() &&
			data.type === 'image' &&
			data.imageWidth > 0 &&
			data.total > 20 &&
			$.inArray( data.imageWidth, trackedWidths ) !== -1
		) {
			mw.track( 'timing.media.thumbnail.client.' + data.imageWidth, data.total );
		}

		if ( this.isEnabled() ) {
			mw.log( 'mw.mmv.logging.PerformanceLogger', data );
		}
		return mw.mmv.logging.Logger.prototype.log.call( this, data );
	};

	new PerformanceLogger().init();

	mw.mmv.logging.PerformanceLogger = PerformanceLogger;

}( mediaWiki, jQuery, OO ) );
