( function () {
	QUnit.module( 'mmv.bootstrap', QUnit.newMwEnvironment( {
		setup: function () {
			mw.config.set( 'wgMediaViewer', true );
			mw.config.set( 'wgMediaViewerOnClick', true );
			this.sandbox.stub( mw.user, 'isAnon' ).returns( false );
		}
	} ) );

	function createGallery( imageSrc, caption ) {
		var $div = $( '<div>' ).addClass( 'gallery' ).appendTo( '#qunit-fixture' ),
			$galleryBox = $( '<div>' ).addClass( 'gallerybox' ).appendTo( $div ),
			$thumbwrap = $( '<div>' ).addClass( 'thumb' ).appendTo( $galleryBox ),
			$link = $( '<a>' ).addClass( 'image' ).appendTo( $thumbwrap );

		$( '<img>' ).attr( 'src', ( imageSrc || 'thumb.jpg' ) ).appendTo( $link );
		$( '<div>' ).addClass( 'gallerytext' ).text( caption || 'Foobar' ).appendTo( $galleryBox );

		return $div;
	}

	function createThumb( imageSrc, caption, alt ) {
		var $div = $( '<div>' ).addClass( 'thumb' ).appendTo( '#qunit-fixture' ),
			$link = $( '<a>' ).addClass( 'image' ).appendTo( $div );

		$( '<div>' ).addClass( 'thumbcaption' ).appendTo( $div ).text( caption );
		$( '<img>' ).attr( 'src', ( imageSrc || 'thumb.jpg' ) ).attr( 'alt', alt ).appendTo( $link );

		return $div;
	}

	function createNormal( imageSrc, caption ) {
		var $link = $( '<a>' ).prop( 'title', caption ).addClass( 'image' ).appendTo( '#qunit-fixture' );
		$( '<img>' ).prop( 'src', ( imageSrc || 'thumb.jpg' ) ).appendTo( $link );
		return $link;
	}

	function createMultipleImage( images ) {
		var i, $div, $thumbimage, $link,
			$contain = $( '<div>' ).addClass( 'thumb' ),
			$thumbinner = $( '<div>' ).addClass( 'thumbinner' ).appendTo( $contain );
		for ( i = 0; i < images.length; ++i ) {
			$div = $( '<div>' ).appendTo( $thumbinner );
			$thumbimage = $( '<div>' ).addClass( 'thumbimage' ).appendTo( $div );
			$link = $( '<a>' ).addClass( 'image' ).appendTo( $thumbimage );
			$( '<img>' ).prop( 'src', images[ i ][ 0 ] ).appendTo( $link );
			$( '<div>' ).addClass( 'thumbcaption' ).text( images[ i ][ 1 ] ).appendTo( $div );
		}
		return $contain;
	}

	function createBootstrap( viewer ) {
		var bootstrap = new mw.mmv.MultimediaViewerBootstrap();

		bootstrap.processThumbs( $( '#qunit-fixture' ) );

		// MultimediaViewerBootstrap.ensureEventHandlersAreSetUp() is a weird workaround for gadget bugs.
		// MediaViewer should work without it, and so should the tests.
		bootstrap.ensureEventHandlersAreSetUp = function () {};

		bootstrap.getViewer = function () {
			return viewer || { initWithThumbs: function () {}, hash: function () {} };
		};

		return bootstrap;
	}

	function hashTest( prefix, bootstrap, assert ) {
		var hash = prefix + '/foo',
			callCount = 0;

		bootstrap.loadViewer = function () {
			callCount++;
			return $.Deferred().reject();
		};

		// Hijack loadViewer, which will return a promise that we'll have to
		// wait for if we want to see these tests through
		mw.mmv.testHelpers.asyncMethod( bootstrap, 'loadViewer' );

		// invalid hash, should not trigger MMV load
		location.hash = 'Foo';

		// actual hash we want to test for, should trigger MMV load
		// use setTimeout to add new hash change to end of the call stack,
		// ensuring that event handlers for our previous change can execute
		// without us interfering with another immediate change
		setTimeout( function () {
			location.hash = hash;
			bootstrap.hash();
		} );

		return mw.mmv.testHelpers.waitForAsync().then( function () {
			assert.strictEqual( callCount, 1, 'Viewer should be loaded once' );
			bootstrap.cleanupEventHandlers();
			location.hash = '';
		} );
	}

	QUnit.test( 'Promise does not hang on ResourceLoader errors', function ( assert ) {
		var bootstrap,
			errorMessage = 'loading failed',
			done = assert.async();

		this.sandbox.stub( mw.loader, 'using' )
			.callsArgWith( 2, new Error( errorMessage, [ 'mmv' ] ) )
			.withArgs( 'mediawiki.notification' ).returns( $.Deferred().reject() ); // needed for mw.notify

		bootstrap = createBootstrap();
		this.sandbox.stub( bootstrap, 'setupOverlay' );
		this.sandbox.stub( bootstrap, 'cleanupOverlay' );

		bootstrap.loadViewer( true ).fail( function ( message ) {
			assert.strictEqual( bootstrap.setupOverlay.called, true, 'Overlay was set up' );
			assert.strictEqual( bootstrap.cleanupOverlay.called, true, 'Overlay was cleaned up' );
			assert.strictEqual( message, errorMessage, 'promise is rejected with the error message when loading fails' );
			done();
		} );
	} );

	QUnit.test( 'Clicks are not captured once the loading fails', function ( assert ) {
		var event, returnValue,
			bootstrap = new mw.mmv.MultimediaViewerBootstrap(),
			clock = this.sandbox.useFakeTimers();

		this.sandbox.stub( mw.loader, 'using' )
			.callsArgWith( 2, new Error( 'loading failed', [ 'mmv' ] ) )
			.withArgs( 'mediawiki.notification' ).returns( $.Deferred().reject() ); // needed for mw.notify
		bootstrap.ensureEventHandlersAreSetUp = function () {};

		// trigger first click, which will cause MMV to be loaded (which we've
		// set up to fail)
		event = new $.Event( 'click', { button: 0, which: 1 } );
		returnValue = bootstrap.click( {}, event, mw.Title.newFromText( 'Foo' ) );
		clock.tick( 10 );
		assert.ok( event.isDefaultPrevented(), 'First click is caught' );
		assert.strictEqual( returnValue, false, 'First click is caught' );

		// wait until MMW is loaded (or failed to load, in this case) before we
		// trigger another click - which should then not be caught
		event = new $.Event( 'click', { button: 0, which: 1 } );
		returnValue = bootstrap.click( {}, event, mw.Title.newFromText( 'Foo' ) );
		clock.tick( 10 );
		assert.strictEqual( event.isDefaultPrevented(), false, 'Click after loading failure is not caught' );
		assert.notStrictEqual( returnValue, false, 'Click after loading failure is not caught' );

		clock.restore();
	} );

	// FIXME: Tests suspended as they do not pass in QUnit 2.x+ – T192932
	QUnit.skip( 'Check viewer invoked when clicking on valid image links', function ( assert ) {
		// TODO: Is <div class="gallery"><span class="image"><img/></span></div> valid ???
		var div, $link, $link2, $link3, $link4, $link5, bootstrap,
			viewer = { initWithThumbs: function () {}, loadImageByTitle: this.sandbox.stub() },
			clock = this.sandbox.useFakeTimers();

		// Create gallery with valid link image
		div = createGallery();
		$link = div.find( 'a.image' );

		// Valid isolated thumbnail
		$link2 = $( '<a>' ).addClass( 'image' ).appendTo( '#qunit-fixture' );
		$( '<img>' ).attr( 'src', 'thumb2.jpg' ).appendTo( $link2 );

		// Non-valid fragment
		$link3 = $( '<a>' ).addClass( 'noImage' ).appendTo( div );
		$( '<img>' ).attr( 'src', 'thumb3.jpg' ).appendTo( $link3 );

		mw.config.set( 'wgTitle', 'Thumb4.jpg' );
		mw.config.set( 'wgNamespaceNumber', 6 );
		$( '<div>' ).addClass( 'fullMedia' ).appendTo( div );
		$( '<img>' ).attr( 'src', 'thumb4.jpg' ).appendTo(
			$( '<a>' )
				.appendTo(
					$( '<div>' )
						.attr( 'id', 'file' )
						.appendTo( '#qunit-fixture' )
				)
		);

		// Create a new bootstrap object to trigger the DOM scan, etc.
		bootstrap = createBootstrap( viewer );
		this.sandbox.stub( bootstrap, 'setupOverlay' );

		$link4 = $( '.fullMedia .mw-mmv-view-expanded' );
		assert.ok( $link4.length, 'Link for viewing expanded file was set up.' );

		$link5 = $( '.fullMedia .mw-mmv-view-config' );
		assert.ok( $link5.length, 'Link for opening enable/disable configuration was set up.' );

		// Click on valid link
		$link.trigger( { type: 'click', which: 1 } );
		clock.tick( 10 );
		// FIXME: Actual bootstrap.setupOverlay.callCount: 2
		assert.equal( bootstrap.setupOverlay.callCount, 1, 'setupOverlay called (1st click)' );
		assert.equal( viewer.loadImageByTitle.callCount, 1, 'loadImageByTitle called (1st click)' );
		this.sandbox.reset();

		// Click on valid link
		$link2.trigger( { type: 'click', which: 1 } );
		clock.tick( 10 );
		assert.equal( bootstrap.setupOverlay.callCount, 1, 'setupOverlay called (2nd click)' );
		assert.equal( viewer.loadImageByTitle.callCount, 1, 'loadImageByTitle called (2nd click)' );
		this.sandbox.reset();

		// Click on valid link
		$link4.trigger( { type: 'click', which: 1 } );
		clock.tick( 10 );
		assert.equal( bootstrap.setupOverlay.callCount, 1, 'setupOverlay called (3rd click)' );
		assert.equal( viewer.loadImageByTitle.callCount, 1, 'loadImageByTitle called (3rd click)' );
		this.sandbox.reset();

		// Click on valid link even when preference says not to
		mw.config.set( 'wgMediaViewerOnClick', false );
		$link4.trigger( { type: 'click', which: 1 } );
		clock.tick( 10 );
		mw.config.set( 'wgMediaViewerOnClick', true );
		assert.equal( bootstrap.setupOverlay.callCount, 1, 'setupOverlay called on-click with pref off' );
		assert.equal( viewer.loadImageByTitle.callCount, 1, 'loadImageByTitle called on-click with pref off' );
		this.sandbox.reset();

		// @todo comment that above clicks should result in call, below clicks should not

		// Click on non-valid link
		$link3.trigger( { type: 'click', which: 1 } );
		clock.tick( 10 );
		assert.equal( bootstrap.setupOverlay.callCount, 0, 'setupOverlay not called on non-valid link click' );
		assert.equal( viewer.loadImageByTitle.callCount, 0, 'loadImageByTitle not called on non-valid link click' );
		this.sandbox.reset();

		// Click on valid links with preference off
		mw.config.set( 'wgMediaViewerOnClick', false );
		$link.trigger( { type: 'click', which: 1 } );
		$link2.trigger( { type: 'click', which: 1 } );
		clock.tick( 10 );
		assert.equal( bootstrap.setupOverlay.callCount, 0, 'setupOverlay not called on non-valid link click with pref off' );
		assert.equal( viewer.loadImageByTitle.callCount, 0, 'loadImageByTitle not called on non-valid link click with pref off' );

		clock.restore();
	} );

	QUnit.test( 'Skip images with invalid extensions', function ( assert ) {
		var div, link,
			viewer = { initWithThumbs: function () {}, loadImageByTitle: this.sandbox.stub() },
			clock = this.sandbox.useFakeTimers();

		// Create gallery with image that has invalid name extension
		div = createGallery( 'thumb.badext' );
		link = div.find( 'a.image' );

		// Create a new bootstrap object to trigger the DOM scan, etc.
		createBootstrap( viewer );

		// Click on valid link with wrong image extension
		link.trigger( { type: 'click', which: 1 } );
		clock.tick( 10 );

		assert.strictEqual( viewer.loadImageByTitle.called, false, 'Image should not be loaded' );

		clock.restore();
	} );

	// FIXME: Tests suspended as they do not pass in QUnit 2.x+ – T192932
	QUnit.skip( 'Accept only left clicks without modifier keys, skip the rest', function ( assert ) {
		var $div, $link, bootstrap,
			viewer = { initWithThumbs: function () {}, loadImageByTitle: this.sandbox.stub() },
			clock = this.sandbox.useFakeTimers();

		// Create gallery with image that has valid name extension
		$div = createGallery();

		// Create a new bootstrap object to trigger the DOM scan, etc.
		bootstrap = createBootstrap( viewer );
		this.sandbox.stub( bootstrap, 'setupOverlay' );

		$link = $div.find( 'a.image' );

		// Handle valid left click, it should try to load the image
		$link.trigger( { type: 'click', which: 1 } );
		clock.tick( 10 );

		// FIXME: Actual bootstrap.setupOverlay.callCount: 2
		assert.equal( bootstrap.setupOverlay.callCount, 1, 'Left-click: Set up overlay' );
		assert.equal( viewer.loadImageByTitle.callCount, 1, 'Left-click: Load image' );
		this.sandbox.reset();

		// Skip Ctrl-left-click, no image is loaded
		$link.trigger( { type: 'click', which: 1, ctrlKey: true } );
		clock.tick( 10 );
		assert.equal( bootstrap.setupOverlay.callCount, 0, 'Ctrl-left-click: No overlay' );
		assert.equal( viewer.loadImageByTitle.callCount, 0, 'Ctrl-left-click: No image load' );
		this.sandbox.reset();

		// Skip invalid right click, no image is loaded
		$link.trigger( { type: 'click', which: 2 } );
		clock.tick( 10 );
		assert.equal( bootstrap.setupOverlay.callCount, 0, 'Right-click: No overlay' );
		assert.equal( viewer.loadImageByTitle.callCount, 0, 'Right-click: Image was not loaded' );

		clock.restore();
	} );

	QUnit.test( 'Ensure that the correct title is loaded when clicking', function ( assert ) {
		var bootstrap,
			viewer = { initWithThumbs: function () {}, loadImageByTitle: this.sandbox.stub() },
			$div = createGallery( 'foo.jpg' ),
			$link = $div.find( 'a.image' ),
			clock = this.sandbox.useFakeTimers();

		// Create a new bootstrap object to trigger the DOM scan, etc.
		bootstrap = createBootstrap( viewer );
		this.sandbox.stub( bootstrap, 'setupOverlay' );

		$link.trigger( { type: 'click', which: 1 } );
		clock.tick( 10 );
		assert.ok( bootstrap.setupOverlay.called, 'Overlay was set up' );
		assert.strictEqual( viewer.loadImageByTitle.firstCall.args[ 0 ].getPrefixedDb(), 'File:Foo.jpg', 'Titles are identical' );

		clock.restore();
	} );

	// FIXME: Tests suspended as they do not pass in QUnit 2.x+ – T192932
	QUnit.skip( 'Validate new LightboxImage object has sane constructor parameters', function ( assert ) {
		var bootstrap,
			$div,
			$link,
			viewer = mw.mmv.testHelpers.getMultimediaViewer(),
			fname = 'valid',
			imgSrc = '/' + fname + '.jpg/300px-' + fname + '.jpg',
			imgRegex = new RegExp( imgSrc + '$' ),
			clock = this.sandbox.useFakeTimers();

		$div = createThumb( imgSrc, 'Blah blah', 'meow' );
		$link = $div.find( 'a.image' );

		// Create a new bootstrap object to trigger the DOM scan, etc.
		bootstrap = createBootstrap( viewer );
		this.sandbox.stub( bootstrap, 'setupOverlay' );
		this.sandbox.stub( viewer, 'createNewImage' );
		viewer.loadImage = function () {};
		viewer.createNewImage = function ( fileLink, filePageLink, fileTitle, index, thumb, caption, alt ) {
			var html = thumb.outerHTML;

			// FIXME: fileLink doesn't match imgRegex (null)
			assert.ok( fileLink.match( imgRegex ), 'Thumbnail URL used in creating new image object' );
			assert.strictEqual( filePageLink, '', 'File page link is sane when creating new image object' );
			assert.strictEqual( fileTitle.title, fname, 'Filename is correct when passed into new image constructor' );
			assert.strictEqual( index, 0, 'The only image we created in the gallery is set at index 0 in the images array' );
			assert.ok( html.indexOf( ' src="' + imgSrc + '"' ) > 0, 'The image element passed in contains the src=... we want.' );
			assert.ok( html.indexOf( ' alt="meow"' ) > 0, 'The image element passed in contains the alt=... we want.' );
			assert.strictEqual( caption, 'Blah blah', 'The caption passed in is correct' );
			assert.strictEqual( alt, 'meow', 'The alt text passed in is correct' );
		};

		$link.trigger( { type: 'click', which: 1 } );
		clock.tick( 10 );
		assert.equal( bootstrap.setupOverlay.callCount, 1, 'Overlay was set up' );

		clock.reset();
	} );

	QUnit.test( 'Only load the viewer on a valid hash (modern browsers)', function ( assert ) {
		var bootstrap;

		location.hash = '';

		bootstrap = createBootstrap();

		return hashTest( '/media', bootstrap, assert );
	} );

	QUnit.test( 'Only load the viewer on a valid hash (old browsers)', function ( assert ) {
		var bootstrap;

		location.hash = '';

		bootstrap = createBootstrap();
		bootstrap.browserHistory = undefined;

		return hashTest( '/media', bootstrap, assert );
	} );

	QUnit.test( 'Load the viewer on a legacy hash (modern browsers)', function ( assert ) {
		var bootstrap;

		location.hash = '';

		bootstrap = createBootstrap();

		return hashTest( 'mediaviewer', bootstrap, assert );
	} );

	QUnit.test( 'Load the viewer on a legacy hash (old browsers)', function ( assert ) {
		var bootstrap;

		location.hash = '';

		bootstrap = createBootstrap();
		bootstrap.browserHistory = undefined;

		return hashTest( 'mediaviewer', bootstrap, assert );
	} );

	QUnit.test( 'Overlay is set up on hash change', function ( assert ) {
		var bootstrap;

		location.hash = '#/media/foo';

		bootstrap = createBootstrap();
		this.sandbox.stub( bootstrap, 'setupOverlay' );

		bootstrap.hash();

		assert.ok( bootstrap.setupOverlay.called, 'Overlay is set up' );
	} );

	QUnit.test( 'Overlay is not set up on an irrelevant hash change', function ( assert ) {
		var bootstrap;

		location.hash = '#foo';

		bootstrap = createBootstrap();
		this.sandbox.stub( bootstrap, 'setupOverlay' );
		bootstrap.loadViewer();
		bootstrap.setupOverlay.reset();

		bootstrap.hash();

		assert.strictEqual( bootstrap.setupOverlay.called, false, 'Overlay is not set up' );
	} );

	QUnit.test( 'Restoring article scroll position', function ( assert ) {
		var stubbedScrollTop,
			bootstrap = createBootstrap(),
			$window = $( window ),
			done = assert.async();

		this.sandbox.stub( $.fn, 'scrollTop', function ( scrollTop ) {
			if ( scrollTop !== undefined ) {
				stubbedScrollTop = scrollTop;
				return this;
			} else {
				return stubbedScrollTop;
			}
		} );

		$window.scrollTop( 50 );
		bootstrap.setupOverlay();
		// Calling this a second time because it can happen in history navigation context
		bootstrap.setupOverlay();
		// Clear scrollTop to check it is restored
		$window.scrollTop( 0 );
		bootstrap.cleanupOverlay();

		// Scroll restoration is on a setTimeout
		setTimeout( function () {
			assert.strictEqual( $( window ).scrollTop(), 50, 'Scroll is correctly reset to original top position' );
			done();
		} );
	} );

	QUnit.test( 'Preload JS/CSS dependencies on thumb hover', function ( assert ) {
		var $div, bootstrap,
			clock = this.sandbox.useFakeTimers(),
			viewer = { initWithThumbs: function () {} };

		// Create gallery with image that has valid name extension
		$div = createThumb();

		// Create a new bootstrap object to trigger the DOM scan, etc.
		bootstrap = createBootstrap( viewer );

		this.sandbox.stub( mw.loader, 'load' );

		$div.trigger( 'mouseenter' );
		clock.tick( bootstrap.hoverWaitDuration - 50 );
		$div.trigger( 'mouseleave' );

		assert.strictEqual( mw.loader.load.called, false, 'Dependencies should not be preloaded if the thumb is not hovered long enough' );

		$div.trigger( 'mouseenter' );
		clock.tick( bootstrap.hoverWaitDuration + 50 );
		$div.trigger( 'mouseleave' );

		assert.strictEqual( mw.loader.load.called, true, 'Dependencies should be preloaded if the thumb is hovered long enough' );

		clock.restore();
	} );

	QUnit.test( 'isAllowedThumb', function ( assert ) {
		var $container = $( '<div>' ),
			$thumb = $( '<img>' ).appendTo( $container ),
			bootstrap = createBootstrap();

		assert.strictEqual( bootstrap.isAllowedThumb( $thumb ), true, 'Normal image in a div is allowed.' );

		$container.addClass( 'metadata' );
		assert.strictEqual( bootstrap.isAllowedThumb( $thumb ), false, 'Image in a metadata container is disallowed.' );

		$container.removeClass().addClass( 'noviewer' );
		assert.strictEqual( bootstrap.isAllowedThumb( $thumb ), false, 'Image in a noviewer container is disallowed.' );

		$container.removeClass().addClass( 'noarticletext' );
		assert.strictEqual( bootstrap.isAllowedThumb( $thumb ), false, 'Image in an empty article is disallowed.' );

		$container.removeClass().addClass( 'noviewer' );
		assert.strictEqual( bootstrap.isAllowedThumb( $thumb ), false, 'Image with a noviewer class is disallowed.' );
	} );

	QUnit.test( 'findCaption', function ( assert ) {
		var gallery = createGallery( 'foo.jpg', 'Baz' ),
			thumb = createThumb( 'foo.jpg', 'Quuuuux' ),
			link = createNormal( 'foo.jpg', 'Foobar' ),
			multiple = createMultipleImage( [ [ 'foo.jpg', 'Image #1' ], [ 'bar.jpg', 'Image #2' ],
				[ 'foobar.jpg', 'Image #3' ] ] ),
			bootstrap = createBootstrap();

		assert.strictEqual( bootstrap.findCaption( gallery.find( '.thumb' ), gallery.find( 'a.image' ) ), 'Baz', 'A gallery caption is found.' );
		assert.strictEqual( bootstrap.findCaption( thumb, thumb.find( 'a.image' ) ), 'Quuuuux', 'A thumbnail caption is found.' );
		assert.strictEqual( bootstrap.findCaption( $(), link ), 'Foobar', 'The caption is found even if the image is not a thumbnail.' );
		assert.strictEqual( bootstrap.findCaption( multiple, multiple.find( 'img[src="bar.jpg"]' ).closest( 'a' ) ), 'Image #2', 'The caption is found in {{Multiple image}}.' );
	} );
}() );
