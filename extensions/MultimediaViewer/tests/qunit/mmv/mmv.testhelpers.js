( function ( mw, $ ) {
	var MTH = {};

	MTH.enterFullscreenMock = function () {
		this.first().addClass( 'jq-fullscreened' ).data( 'isFullscreened', true );

		$( document ).trigger( $.Event( 'jq-fullscreen-change', { element: this, fullscreen: true } ) );
	};

	MTH.exitFullscreenMock = function () {
		this.first().removeClass( 'jq-fullscreened' ).data( 'isFullscreened', false );

		$( document ).trigger( $.Event( 'jq-fullscreen-change', { element: this, fullscreen: false } ) );
	};

	/**
	 * Returns the exception thrown by callback, or undefined if no exception was thrown.
	 *
	 * @param {Function} callback
	 * @return {Error}
	 */
	MTH.getException = function ( callback ) {
		var ex;
		try {
			callback();
		} catch ( e ) {
			ex = e;
		}
		return ex;
	};

	/**
	 * Creates an mw.storage-like object.
	 *
	 * @param {Object} storage localStorage stub with getItem, setItem, removeItem methods
	 * @return {mw.storage} Local storage-like object
	 */
	MTH.createLocalStorage = function ( storage ) {
		return new ( Object.getPrototypeOf( mw.storage ) ).constructor( storage );
	};

	/**
	 * Returns an mw.storage that mimicks lack of localStorage support.
	 *
	 * @return {mw.storage} Local storage-like object
	 */
	MTH.getUnsupportedLocalStorage = function () {
		return this.createLocalStorage( undefined );
	};

	/**
	 * Returns an mw.storage that mimicks localStorage being disabled in browser.
	 *
	 * @return {mw.storage} Local storage-like object
	 */
	MTH.getDisabledLocalStorage = function () {
		var e = function () {
			throw new Error( 'Error' );
		};

		return this.createLocalStorage( {
			getItem: e,
			setItem: e,
			removeItem: e
		} );
	};

	/**
	 * Returns a fake local storage which is not saved between reloads.
	 *
	 * @param {Object} [initialData]
	 * @return {mw.storage} Local storage-like object
	 */
	MTH.getFakeLocalStorage = function ( initialData ) {
		var bag = new mw.Map();
		bag.set( initialData );

		return this.createLocalStorage( {
			getItem: function ( key ) { return bag.get( key ); },
			setItem: function ( key, value ) { bag.set( key, value ); },
			removeItem: function ( key ) { bag.set( key, null ); }
		} );
	};

	/**
	 * Returns a viewer object with all the appropriate placeholder functions.
	 *
	 * @return {mv.mmv.MultiMediaViewer} [description]
	 */
	MTH.getMultimediaViewer = function () {
		return new mw.mmv.MultimediaViewer( {
			imageQueryParameter: $.noop,
			language: $.noop,
			recordVirtualViewBeaconURI: $.noop,
			extensions: function () {
				return { jpg: 'default' };
			}
		} );
	};

	MTH.asyncPromises = [];

	/**
	 * Given a method/function that returns a promise, this'll return a function
	 * that just wraps the original & returns the original result, but also
	 * executes an assert.async() right before it's called, and resolves that
	 * async after that promise has completed.
	 *
	 * Example usage: given a method `bootstrap.openImage` that returns a
	 * promise, just call it like this to wrap this functionality around it:
	 * `bootstrap.openImage = asyncMethod( bootstrap.openImage, bootstrap );`
	 *
	 * Now, every time some part of the code calls this function, it'll just
	 * execute as it normally would, but your tests won't finish until these
	 * functions (and any .then tacked on to them) have completed.
	 *
	 * This method will make sure your tests don't end prematurely (before the
	 * promises have been resolved), but that's it. If you need to run
	 * additional code after all promises have resolved, you can call the
	 * complementary `waitForAsync`, which will return a promise that doesn't
	 * resolve until all of these promises have.
	 *
	 * @param {Object} object
	 * @param {string} method
	 * @param {QUnit.assert} [assert]
	 * @return {Function}
	 */
	MTH.asyncMethod = function ( object, method, assert ) {
		return function () {
			// apply arguments to original promise
			var promise = object[ method ].apply( object, arguments ),
				done;

			this.asyncPromises.push( promise );

			if ( assert ) {
				done = assert.async();
				// use setTimeout to ensure `done` is not the first callback handler
				// to execute (possibly ending the test's wait right before
				// the result of the promise is executed)
				setTimeout( promise.then.bind( null, done, done ) );
			}

			return promise;
		}.bind( this );
	};

	/**
	 * Returns a promise that will not resolve until all of the promises that
	 * were created in functions upon which `asyncMethod` was called have
	 * resolved.
	 *
	 * @return {$.Promise}
	 */
	MTH.waitForAsync = function () {
		var deferred = $.Deferred();

		// it's possible that, before this function call, some code was executed
		// that triggers async code that will eventually end up `asyncPromises`
		// in order to give that code a chance to run, we'll add another promise
		// to the array, that will only resolve at the end of the current call
		// stack (using setTimeout)
		this.asyncPromises.push( deferred.promise() );
		setTimeout( deferred.resolve );

		return QUnit.whenPromisesComplete.apply( null, this.asyncPromises ).then(
			function () {
				this.asyncPromises = [];
			}.bind( this )
		);
	};

	mw.mmv.testHelpers = MTH;
}( mediaWiki, jQuery ) );
