( function ( mw ) {
	QUnit.module( 'mmv.ThumbnailWidthCalculator', QUnit.newMwEnvironment() );

	QUnit.test( 'ThumbnailWidthCalculator constructor sanity check', function ( assert ) {
		var badWidthBuckets = [],
			goodWidthBuckets = [ 1 ],
			thumbnailWidthCalculator;

		thumbnailWidthCalculator = new mw.mmv.ThumbnailWidthCalculator();
		assert.ok( thumbnailWidthCalculator, 'constructor with no argument works' );

		thumbnailWidthCalculator = new mw.mmv.ThumbnailWidthCalculator( {} );
		assert.ok( thumbnailWidthCalculator, 'constructor with empty option argument works' );

		thumbnailWidthCalculator = new mw.mmv.ThumbnailWidthCalculator( {
			widthBuckets: goodWidthBuckets
		} );
		assert.ok( thumbnailWidthCalculator, 'constructor with non-default buckets works' );

		try {
			thumbnailWidthCalculator = new mw.mmv.ThumbnailWidthCalculator( {
				widthBuckets: badWidthBuckets
			} );
		} catch ( e ) {
			assert.ok( e, 'constructor with empty bucket list throws exception' );
		}
	} );

	QUnit.test( 'findNextBucket() test', function ( assert ) {
		var thumbnailWidthCalculator = new mw.mmv.ThumbnailWidthCalculator( {
			widthBuckets: [ 100, 200 ]
		} );

		assert.strictEqual( thumbnailWidthCalculator.findNextBucket( 50 ), 100,
			'return first bucket for value smaller than all buckets' );

		assert.strictEqual( thumbnailWidthCalculator.findNextBucket( 300 ), 200,
			'return last bucket for value larger than all buckets' );

		assert.strictEqual( thumbnailWidthCalculator.findNextBucket( 150 ), 200,
			'return next bucket for value between two buckets' );

		assert.strictEqual( thumbnailWidthCalculator.findNextBucket( 100 ), 100,
			'return bucket for value equal to that bucket' );
	} );

	// Old tests for the default bucket sizes. Preserved because why not.
	QUnit.test( 'We get sane image sizes when we ask for them', function ( assert ) {
		var twc = new mw.mmv.ThumbnailWidthCalculator();

		assert.strictEqual( twc.findNextBucket( 200 ), 320, 'Low target size gives us lowest possible size bucket' );
		assert.strictEqual( twc.findNextBucket( 320 ), 320, 'Asking for a bucket size gives us exactly that bucket size' );
		assert.strictEqual( twc.findNextBucket( 320.00001 ), 800, 'Asking for greater than an image bucket definitely gives us the next size up' );
		assert.strictEqual( twc.findNextBucket( 2000 ), 2560, 'The image bucketing also works on big screens' );
		assert.strictEqual( twc.findNextBucket( 3000 ), 2880, 'The image bucketing also works on REALLY big screens' );
	} );

	QUnit.test( 'findNextBucket() test with unordered bucket list', function ( assert ) {
		var thumbnailWidthCalculator = new mw.mmv.ThumbnailWidthCalculator( {
			widthBuckets: [ 200, 100 ]
		} );

		assert.strictEqual( thumbnailWidthCalculator.findNextBucket( 50 ), 100,
			'return first bucket for value smaller than all buckets' );

		assert.strictEqual( thumbnailWidthCalculator.findNextBucket( 300 ), 200,
			'return last bucket for value larger than all buckets' );

		assert.strictEqual( thumbnailWidthCalculator.findNextBucket( 150 ), 200,
			'return next bucket for value between two buckets' );
	} );

	QUnit.test( 'calculateFittingWidth() test', function ( assert ) {
		var boundingWidth = 100,
			boundingHeight = 200,
			thumbnailWidthCalculator = new mw.mmv.ThumbnailWidthCalculator( { widthBuckets: [ 1 ] } );

		// 50x10 image in 100x200 box - need to scale up 2x
		assert.strictEqual(
			thumbnailWidthCalculator.calculateFittingWidth( boundingWidth, boundingHeight, 50, 10 ),
			100, 'fit calculation correct when limited by width' );

		// 10x100 image in 100x200 box - need to scale up 2x
		assert.strictEqual(
			thumbnailWidthCalculator.calculateFittingWidth( boundingWidth, boundingHeight, 10, 100 ),
			20, 'fit calculation correct when limited by height' );

		// 10x20 image in 100x200 box - need to scale up 10x
		assert.strictEqual(
			thumbnailWidthCalculator.calculateFittingWidth( boundingWidth, boundingHeight, 10, 20 ),
			100, 'fit calculation correct when same aspect ratio' );
	} );

	QUnit.test( 'calculateWidths() test', function ( assert ) {
		var boundingWidth = 100,
			boundingHeight = 200,
			thumbnailWidthCalculator = new mw.mmv.ThumbnailWidthCalculator( {
				widthBuckets: [ 8, 16, 32, 64, 128, 256, 512 ],
				devicePixelRatio: 1
			} ),
			widths;

		// 50x10 image in 100x200 box - image size should be 100x20, thumbnail should be 128x25.6
		widths = thumbnailWidthCalculator.calculateWidths( boundingWidth, boundingHeight, 50, 10 );
		assert.strictEqual( widths.cssWidth, 100, 'css width is correct when limited by width' );
		assert.strictEqual( widths.cssHeight, 20, 'css height is correct when limited by width' );
		assert.strictEqual( widths.real, 128, 'real width is correct when limited by width' );

		// 10x100 image in 100x200 box - image size should be 20x200, thumbnail should be 32x320
		widths = thumbnailWidthCalculator.calculateWidths( boundingWidth, boundingHeight, 10, 100 );
		assert.strictEqual( widths.cssWidth, 20, 'css width is correct when limited by height' );
		assert.strictEqual( widths.cssHeight, 200, 'css height is correct when limited by width' );
		assert.strictEqual( widths.real, 32, 'real width is correct when limited by height' );

		// 10x20 image in 100x200 box - image size should be 100x200, thumbnail should be 128x256
		widths = thumbnailWidthCalculator.calculateWidths( boundingWidth, boundingHeight, 10, 20 );
		assert.strictEqual( widths.cssWidth, 100, 'css width is correct when same aspect ratio' );
		assert.strictEqual( widths.cssHeight, 200, 'css height is correct when limited by width' );
		assert.strictEqual( widths.real, 128, 'real width is correct when same aspect ratio' );
	} );

	QUnit.test( 'calculateWidths() test with non-standard device pixel ratio', function ( assert ) {
		var boundingWidth = 100,
			boundingHeight = 200,
			thumbnailWidthCalculator = new mw.mmv.ThumbnailWidthCalculator( {
				widthBuckets: [ 8, 16, 32, 64, 128, 256, 512 ],
				devicePixelRatio: 2
			} ),
			widths;

		// 50x10 image in 100x200 box - image size should be 100x20, thumbnail should be 256x51.2
		widths = thumbnailWidthCalculator.calculateWidths( boundingWidth, boundingHeight, 50, 10 );
		assert.strictEqual( widths.cssWidth, 100, 'css width is correct when limited by width' );
		assert.strictEqual( widths.cssHeight, 20, 'css height is correct when limited by width' );
		assert.strictEqual( widths.real, 256, 'real width is correct when limited by width' );

		// 10x100 image in 100x200 box - image size should be 20x200, thumbnail should be 64x640
		widths = thumbnailWidthCalculator.calculateWidths( boundingWidth, boundingHeight, 10, 100 );
		assert.strictEqual( widths.cssWidth, 20, 'css width is correct when limited by height' );
		assert.strictEqual( widths.cssHeight, 200, 'css height is correct when limited by width' );
		assert.strictEqual( widths.real, 64, 'real width is correct when limited by height' );

		// 10x20 image in 100x200 box - image size should be 100x200, thumbnail should be 256x512
		widths = thumbnailWidthCalculator.calculateWidths( boundingWidth, boundingHeight, 10, 20 );
		assert.strictEqual( widths.cssWidth, 100, 'css width is correct when same aspect ratio' );
		assert.strictEqual( widths.cssHeight, 200, 'css height is correct when limited by width' );
		assert.strictEqual( widths.real, 256, 'real width is correct when same aspect ratio' );
	} );
}( mediaWiki ) );
