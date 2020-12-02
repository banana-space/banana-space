( function () {
	var thingsShouldBeEmptied = [
			'$license',
			'$title',
			'$location',
			'$datetime'
		],

		thingsShouldHaveEmptyClass = [
			'$licenseLi',
			'$credit',
			'$locationLi',
			'$datetimeLi'
		];

	QUnit.module( 'mmv.ui.metadataPanel', QUnit.newMwEnvironment() );

	QUnit.test( 'The panel is emptied properly when necessary', function ( assert ) {
		var i,
			$qf = $( '#qunit-fixture' ),
			panel = new mw.mmv.ui.MetadataPanel( $qf, $( '<div>' ).appendTo( $qf ), mw.storage, new mw.mmv.Config( {}, mw.config, mw.user, new mw.Api(), mw.storage ) );

		panel.empty();

		for ( i = 0; i < thingsShouldBeEmptied.length; i++ ) {
			assert.strictEqual( panel[ thingsShouldBeEmptied[ i ] ].text(), '', 'We successfully emptied the ' + thingsShouldBeEmptied[ i ] + ' element' );
		}

		for ( i = 0; i < thingsShouldHaveEmptyClass.length; i++ ) {
			assert.strictEqual( panel[ thingsShouldHaveEmptyClass[ i ] ].hasClass( 'empty' ), true, 'We successfully applied the empty class to the ' + thingsShouldHaveEmptyClass[ i ] + ' element' );
		}
	} );

	QUnit.test( 'Setting location information works as expected', function ( assert ) {
		var $qf = $( '#qunit-fixture' ),
			panel = new mw.mmv.ui.MetadataPanel( $qf, $( '<div>' ).appendTo( $qf ), mw.storage, new mw.mmv.Config( {}, mw.config, mw.user, new mw.Api(), mw.storage ) ),
			fileName = 'Foobar.jpg',
			latitude = 12.3456789,
			longitude = 98.7654321,
			imageData = {
				latitude: latitude,
				longitude: longitude,
				hasCoords: function () { return true; },
				title: mw.Title.newFromText( 'File:Foobar.jpg' )
			};

		panel.setLocationData( imageData );

		assert.strictEqual(
			panel.$location.text(),
			'(multimediaviewer-geolocation: (multimediaviewer-geoloc-coords: (multimediaviewer-geoloc-coord: 12, 20, 44.44, (multimediaviewer-geoloc-north)), (multimediaviewer-geoloc-coord: 98, 45, 55.56, (multimediaviewer-geoloc-east))))',
			'Location text is set as expected - if this fails it may be due to i18n issues.'
		);

		assert.strictEqual(
			panel.$location.prop( 'href' ),
			'http://tools.wmflabs.org/geohack/geohack.php?pagename=File:' + fileName + '&params=' + latitude + '_N_' + longitude + '_E_&language=qqx',
			'Location URL is set as expected'
		);

		latitude = -latitude;
		longitude = -longitude;
		imageData.latitude = latitude;
		imageData.longitude = longitude;
		panel.setLocationData( imageData );

		assert.strictEqual(
			panel.$location.text(),
			'(multimediaviewer-geolocation: (multimediaviewer-geoloc-coords: (multimediaviewer-geoloc-coord: 12, 20, 44.44, (multimediaviewer-geoloc-south)), (multimediaviewer-geoloc-coord: 98, 45, 55.56, (multimediaviewer-geoloc-west))))',
			'Location text is set as expected - if this fails it may be due to i18n issues.'
		);

		assert.strictEqual(
			panel.$location.prop( 'href' ),
			'http://tools.wmflabs.org/geohack/geohack.php?pagename=File:' + fileName + '&params=' + ( -latitude ) + '_S_' + ( -longitude ) + '_W_&language=qqx',
			'Location URL is set as expected'
		);

		latitude = 0;
		longitude = 0;
		imageData.latitude = latitude;
		imageData.longitude = longitude;
		panel.setLocationData( imageData );

		assert.strictEqual(
			panel.$location.text(),
			'(multimediaviewer-geolocation: (multimediaviewer-geoloc-coords: (multimediaviewer-geoloc-coord: 0, 0, 0, (multimediaviewer-geoloc-north)), (multimediaviewer-geoloc-coord: 0, 0, 0, (multimediaviewer-geoloc-east))))',
			'Location text is set as expected - if this fails it may be due to i18n issues.'
		);

		assert.strictEqual(
			panel.$location.prop( 'href' ),
			'http://tools.wmflabs.org/geohack/geohack.php?pagename=File:' + fileName + '&params=' + latitude + '_N_' + longitude + '_E_&language=qqx',
			'Location URL is set as expected'
		);
	} );

	QUnit.test( 'Setting image information works as expected', function ( assert ) {
		var creditPopupText,
			$qf = $( '#qunit-fixture' ),
			panel = new mw.mmv.ui.MetadataPanel( $qf, $( '<div>' ).appendTo( $qf ), mw.storage, new mw.mmv.Config( {}, mw.config, mw.user, new mw.Api(), mw.storage ) ),
			title = 'Foo bar',
			image = {
				filePageTitle: mw.Title.newFromText( 'File:' + title + '.jpg' )
			},
			imageData = {
				title: image.filePageTitle,
				url: 'https://upload.wikimedia.org/wikipedia/commons/3/3a/Foobar.jpg',
				descriptionUrl: 'https://commons.wikimedia.org/wiki/File:Foobar.jpg',
				hasCoords: function () { return false; }
			},
			repoData = {
				getArticlePath: function () { return 'Foo'; },
				isCommons: function () { return false; }
			},
			clock = this.sandbox.useFakeTimers();

		panel.setImageInfo( image, imageData, repoData );

		assert.strictEqual( panel.$title.text(), title, 'Title is correctly set' );
		assert.ok( panel.$credit.text(), 'Default credit is shown' );
		assert.strictEqual( panel.$license.prop( 'href' ), imageData.descriptionUrl,
			'User is directed to file page for license information' );
		assert.notOk( panel.$license.prop( 'target' ), 'License information opens in same window' );
		assert.strictEqual( panel.$datetimeLi.hasClass( 'empty' ), true, 'Date/Time is empty' );
		assert.strictEqual( panel.$locationLi.hasClass( 'empty' ), true, 'Location is empty' );

		imageData.creationDateTime = '2013-08-26T14:41:02Z';
		imageData.uploadDateTime = '2013-08-25T14:41:02Z';
		imageData.source = '<b>Lost</b><a href="foo">Bar</a>';
		imageData.author = 'Bob';
		imageData.license = new mw.mmv.model.License( 'CC-BY-2.0', 'cc-by-2.0',
			'Creative Commons Attribution - Share Alike 2.0',
			'http://creativecommons.org/licenses/by-sa/2.0/' );
		imageData.restrictions = [ 'trademarked', 'default', 'insignia' ];

		panel.setImageInfo( image, imageData, repoData );
		creditPopupText = panel.creditField.$element.attr( 'original-title' );
		clock.tick( 10 );

		assert.strictEqual( panel.$title.text(), title, 'Title is correctly set' );
		assert.strictEqual( panel.$credit.hasClass( 'empty' ), false, 'Credit is not empty' );
		assert.strictEqual( panel.$datetimeLi.hasClass( 'empty' ), false, 'Date/Time is not empty' );
		assert.strictEqual( panel.creditField.$element.find( '.mw-mmv-author' ).text(), imageData.author, 'Author text is correctly set' );
		assert.strictEqual( panel.creditField.$element.find( '.mw-mmv-source' ).html(), '<b>Lost</b><a href="foo">Bar</a>', 'Source text is correctly set' );
		// Either multimediaviewer-credit-popup-text or multimediaviewer-credit-popup-text-more.
		assert.ok( creditPopupText === '(multimediaviewer-credit-popup-text)' || creditPopupText === '(multimediaviewer-credit-popup-text-more)', 'Source tooltip is correctly set' );
		assert.strictEqual( panel.$datetime.text(), '(multimediaviewer-datetime-created: August 26, 2013)', 'Correct date is displayed' );
		assert.strictEqual( panel.$license.text(), '(multimediaviewer-license-cc-by-2.0)', 'License is correctly set' );
		assert.ok( panel.$license.prop( 'target' ), 'License information opens in new window' );
		assert.strictEqual( panel.$restrictions.children().last().children().hasClass( 'mw-mmv-restriction-default' ), true, 'Default restriction is correctly displayed last' );

		imageData.creationDateTime = undefined;
		panel.setImageInfo( image, imageData, repoData );
		clock.tick( 10 );

		assert.strictEqual( panel.$datetime.text(), '(multimediaviewer-datetime-uploaded: August 25, 2013)', 'Correct date is displayed' );

		clock.restore();
	} );

	QUnit.test( 'Setting permission information works as expected', function ( assert ) {
		var $qf = $( '#qunit-fixture' ),
			panel = new mw.mmv.ui.MetadataPanel( $qf, $( '<div>' ).appendTo( $qf ), mw.storage, new mw.mmv.Config( {}, mw.config, mw.user, new mw.Api(), mw.storage ) );

		panel.setLicense( null, 'http://example.com' ); // make sure license is visible as it contains the permission
		panel.setPermission( 'Look at me, I am a permission!' );
		assert.strictEqual( panel.$permissionLink.is( ':visible' ), true );
	} );

	QUnit.test( 'Date formatting', function ( assert ) {
		var $qf = $( '#qunit-fixture' ),
			panel = new mw.mmv.ui.MetadataPanel( $qf, $( '<div>' ).appendTo( $qf ), mw.storage, new mw.mmv.Config( {}, mw.config, mw.user, new mw.Api(), mw.storage ) ),
			date1 = 'Garbage',
			result = panel.formatDate( date1 );

		assert.strictEqual( result, date1, 'Invalid date is correctly ignored' );
	} );

	QUnit.test( 'About links', function ( assert ) {
		var $qf = $( '#qunit-fixture' );

		this.sandbox.stub( mw.user, 'isAnon' );
		// eslint-disable-next-line no-new
		new mw.mmv.ui.MetadataPanel( $qf.empty(), $( '<div>' ).appendTo( $qf ), mw.storage, new mw.mmv.Config( {}, mw.config, mw.user, new mw.Api(), mw.storage ) );

		assert.strictEqual( $qf.find( '.mw-mmv-about-link' ).length, 1, 'About link is created.' );
		assert.strictEqual( $qf.find( '.mw-mmv-discuss-link' ).length, 1, 'Discuss link is created.' );
		assert.strictEqual( $qf.find( '.mw-mmv-help-link' ).length, 1, 'Help link is created.' );
	} );
}() );
