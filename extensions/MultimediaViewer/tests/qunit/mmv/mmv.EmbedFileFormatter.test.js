( function ( mw ) {
	QUnit.module( 'mmv.EmbedFileFormatter', QUnit.newMwEnvironment() );

	function createEmbedFileInfo( options ) {
		var license = options.licenseShortName ? new mw.mmv.model.License( options.licenseShortName,
				options.licenseInternalName, options.licenseLongName, options.licenseUrl ) : undefined,
			imageInfo = new mw.mmv.model.Image(

				options.title,
				options.title.getNameText(),
				undefined,
				undefined,
				undefined,
				undefined,
				options.imgUrl,
				options.filePageUrl,
				options.shortFilePageUrl,
				42,
				'repo',
				undefined,
				undefined,
				undefined,
				undefined,
				options.source,
				options.author,
				options.authorCount,
				license ),
			repoInfo = { displayName: options.siteName, getSiteLink:
				function () { return options.siteUrl; } };

		return new mw.mmv.model.EmbedFileInfo( imageInfo, repoInfo, options.caption );
	}

	QUnit.test( 'EmbedFileFormatter constructor sanity check', function ( assert ) {
		var formatter = new mw.mmv.EmbedFileFormatter();
		assert.ok( formatter, 'constructor with no argument works' );
	} );

	QUnit.test( 'getByline():', function ( assert ) {
		var formatter = new mw.mmv.EmbedFileFormatter(),
			author = '<span class="mw-mmv-author">Homer</span>',
			source = '<span class="mw-mmv-source">Iliad</span>',
			attribution = '<span class="mw-mmv-attr">Cat</span>',
			byline;

		// Works with no arguments
		byline = formatter.getByline();
		assert.strictEqual( byline, undefined, 'No argument case handled correctly.' );

		// Attribution present
		byline = formatter.getByline( author, source, attribution );
		assert.ok( byline.match( /Cat/ ), 'Attribution found in bylines' );

		// Author and source present
		byline = formatter.getByline( author, source );
		assert.ok( byline.match( /Homer|Iliad/ ), 'Author and source found in bylines' );

		// Only author present
		byline = formatter.getByline( author );
		assert.ok( byline.match( /Homer/ ), 'Author found in bylines.' );

		// Only source present
		byline = formatter.getByline( undefined, source );
		assert.ok( byline.match( /Iliad/ ), 'Source found in bylines.' );
	} );

	QUnit.test( 'getSiteLink():', function ( assert ) {
		var repoInfo = new mw.mmv.model.Repo( 'Wikipedia', '//wikipedia.org/favicon.ico', true ),
			info = new mw.mmv.model.EmbedFileInfo( {}, repoInfo ),
			formatter = new mw.mmv.EmbedFileFormatter(),
			siteUrl = repoInfo.getSiteLink(),
			siteLink = formatter.getSiteLink( info );

		assert.ok( siteLink.match( 'Wikipedia' ), 'Site name is present in site link' );
		assert.ok( siteLink.indexOf( siteUrl ) !== -1, 'Site URL is present in site link' );
	} );

	QUnit.test( 'getThumbnailHtml():', function ( assert ) {
		var formatter = new mw.mmv.EmbedFileFormatter(),
			titleText = 'Music Room',
			title = mw.Title.newFromText( titleText ),
			imgUrl = 'https://upload.wikimedia.org/wikipedia/commons/3/3a/Foobar.jpg',
			filePageUrl = 'https://commons.wikimedia.org/wiki/File:Foobar.jpg',
			filePageShortUrl = 'https://commons.wikimedia.org/wiki/index.php?curid=42',
			siteName = 'Site Name',
			siteUrl = '//site.url/',
			licenseShortName = 'Public License',
			licenseInternalName = '-',
			licenseLongName = 'Public Domain, copyrights have lapsed',
			licenseUrl = '//example.com/pd',
			author = '<span class="mw-mmv-author">Homer</span>',
			source = '<span class="mw-mmv-source">Iliad</span>',
			thumbUrl = 'https://upload.wikimedia.org/wikipedia/thumb/Foobar.jpg',
			width = 700,
			height = 500,
			info,
			generatedHtml;

		// Bylines, license and site
		info = createEmbedFileInfo( { title: title, imgUrl: imgUrl, filePageUrl: filePageUrl,
			shortFilePageUrl: filePageShortUrl, siteName: siteName, siteUrl: siteUrl,
			licenseShortName: licenseShortName, licenseInternalName: licenseInternalName,
			licenseLongName: licenseLongName, licenseUrl: licenseUrl, author: author, source: source } );

		generatedHtml = formatter.getThumbnailHtml( info, thumbUrl, width, height );
		assert.ok( generatedHtml.match( titleText ), 'Title appears in generated HTML.' );
		assert.ok( generatedHtml.match( filePageUrl ), 'Page url appears in generated HTML.' );
		assert.ok( generatedHtml.match( thumbUrl ), 'Thumbnail url appears in generated HTML' );
		assert.ok( generatedHtml.match( 'Public License' ), 'License appears in generated HTML' );
		assert.ok( generatedHtml.match( 'Homer' ), 'Author appears in generated HTML' );
		assert.ok( generatedHtml.match( 'Iliad' ), 'Source appears in generated HTML' );
		assert.ok( generatedHtml.match( width ), 'Width appears in generated HTML' );
		assert.ok( generatedHtml.match( height ), 'Height appears in generated HTML' );
		// .includes() for checking the short url since it contains a ? (bad for regex). Could escape instead.
		assert.ok( generatedHtml.includes( filePageShortUrl ), 'Short URL appears in generated HTML' );

		// Bylines, no license and site
		info = createEmbedFileInfo( { title: title, imgUrl: imgUrl, filePageUrl: filePageUrl,
			shortFilePageUrl: filePageShortUrl, siteName: siteName, siteUrl: siteUrl,
			author: author, source: source } );
		generatedHtml = formatter.getThumbnailHtml( info, thumbUrl, width, height );

		assert.ok( generatedHtml.match( titleText ), 'Title appears in generated HTML.' );
		assert.ok( generatedHtml.match( filePageUrl ), 'Page url appears in generated HTML.' );
		assert.ok( generatedHtml.match( thumbUrl ), 'Thumbnail url appears in generated HTML' );
		assert.ok( !generatedHtml.match( 'Public License' ), 'License should not appear in generated HTML' );
		assert.ok( generatedHtml.match( 'Homer' ), 'Author appears in generated HTML' );
		assert.ok( generatedHtml.match( 'Iliad' ), 'Source appears in generated HTML' );
		assert.ok( generatedHtml.match( width ), 'Width appears in generated HTML' );
		assert.ok( generatedHtml.match( height ), 'Height appears in generated HTML' );
		assert.ok( generatedHtml.includes( filePageShortUrl ), 'Short URL appears in generated HTML' );

		// No bylines, license and site
		info = createEmbedFileInfo( { title: title, imgUrl: imgUrl, filePageUrl: filePageUrl,
			siteName: siteName, siteUrl: siteUrl, licenseShortName: licenseShortName,
			licenseInternalName: licenseInternalName, licenseLongName: licenseLongName,
			licenseUrl: licenseUrl, shortFilePageUrl: filePageShortUrl } );
		generatedHtml = formatter.getThumbnailHtml( info, thumbUrl, width, height );

		assert.ok( generatedHtml.match( titleText ), 'Title appears in generated HTML.' );
		assert.ok( generatedHtml.match( filePageUrl ), 'Page url appears in generated HTML.' );
		assert.ok( generatedHtml.match( thumbUrl ), 'Thumbnail url appears in generated HTML' );
		assert.ok( generatedHtml.match( 'Public License' ), 'License appears in generated HTML' );
		assert.ok( !generatedHtml.match( 'Homer' ), 'Author should not appear in generated HTML' );
		assert.ok( !generatedHtml.match( 'Iliad' ), 'Source should not appear in generated HTML' );
		assert.ok( generatedHtml.match( width ), 'Width appears in generated HTML' );
		assert.ok( generatedHtml.match( height ), 'Height appears in generated HTML' );
		assert.ok( generatedHtml.includes( filePageShortUrl ), 'Short URL appears in generated HTML' );

		// No bylines, no license and site
		info = createEmbedFileInfo( { title: title, imgUrl: imgUrl, filePageUrl: filePageUrl,
			siteName: siteName, siteUrl: siteUrl, shortFilePageUrl: filePageShortUrl } );
		generatedHtml = formatter.getThumbnailHtml( info, thumbUrl, width, height );

		assert.ok( generatedHtml.match( titleText ), 'Title appears in generated HTML.' );
		assert.ok( generatedHtml.match( filePageUrl ), 'Page url appears in generated HTML.' );
		assert.ok( generatedHtml.match( thumbUrl ), 'Thumbnail url appears in generated HTML' );
		assert.ok( !generatedHtml.match( 'Public License' ), 'License should not appear in generated HTML' );
		assert.ok( !generatedHtml.match( 'Homer' ), 'Author should not appear in generated HTML' );
		assert.ok( !generatedHtml.match( 'Iliad' ), 'Source should not appear in generated HTML' );
		assert.ok( generatedHtml.match( width ), 'Width appears in generated HTML' );
		assert.ok( generatedHtml.match( height ), 'Height appears in generated HTML' );
		assert.ok( generatedHtml.includes( filePageShortUrl ), 'Short URL appears in generated HTML' );

	} );

	QUnit.test( 'getThumbnailWikitext():', function ( assert ) {
		var formatter = new mw.mmv.EmbedFileFormatter(),
			title = mw.Title.newFromText( 'File:Foobar.jpg' ),
			imgUrl = 'https://upload.wikimedia.org/wikipedia/commons/3/3a/Foobar.jpg',
			filePageUrl = 'https://commons.wikimedia.org/wiki/File:Foobar.jpg',
			caption = 'Foobar caption.',
			width = 700,
			info,
			wikitext;

		// Title, width and caption
		info = createEmbedFileInfo( { title: title, imgUrl: imgUrl, filePageUrl: filePageUrl,
			caption: caption } );
		wikitext = formatter.getThumbnailWikitextFromEmbedFileInfo( info, width );

		assert.strictEqual(
			wikitext,
			'[[File:Foobar.jpg|700px|thumb|Foobar caption.]]',
			'Wikitext generated correctly.' );

		// Title, width and no caption
		info = createEmbedFileInfo( { title: title, imgUrl: imgUrl, filePageUrl: filePageUrl } );
		wikitext = formatter.getThumbnailWikitextFromEmbedFileInfo( info, width );

		assert.strictEqual(
			wikitext,
			'[[File:Foobar.jpg|700px|thumb|Foobar]]',
			'Wikitext generated correctly.' );

		// Title, no width and no caption
		info = createEmbedFileInfo( { title: title, imgUrl: imgUrl, filePageUrl: filePageUrl } );
		wikitext = formatter.getThumbnailWikitextFromEmbedFileInfo( info );

		assert.strictEqual(
			wikitext,
			'[[File:Foobar.jpg|thumb|Foobar]]',
			'Wikitext generated correctly.' );
	} );

	QUnit.test( 'getCreditText():', function ( assert ) {
		var txt, formatter = new mw.mmv.EmbedFileFormatter();

		txt = formatter.getCreditText( {
			repoInfo: {
				displayName: 'Localcommons'
			},

			imageInfo: {
				author: 'Author',
				source: 'Source',
				descriptionShortUrl: 'link',
				title: {
					getNameText: function () { return 'Image Title'; }
				}
			}
		} );

		assert.strictEqual( txt, 'By Author - Source, link', 'Sanity check' );

		txt = formatter.getCreditText( {
			repoInfo: {
				displayName: 'Localcommons'
			},

			imageInfo: {
				author: 'Author',
				source: 'Source',
				descriptionShortUrl: 'link',
				title: {
					getNameText: function () { return 'Image Title'; }
				},
				license: {
					getShortName: function () { return 'WTFPL v2'; },
					longName: 'Do What the Fuck You Want Public License Version 2',
					isFree: this.sandbox.stub().returns( true )
				}
			}
		} );

		assert.strictEqual( txt, 'By Author - Source, WTFPL v2, link', 'License message works' );
	} );

	QUnit.test( 'getCreditHtml():', function ( assert ) {
		var html, formatter = new mw.mmv.EmbedFileFormatter();

		html = formatter.getCreditHtml( {
			repoInfo: {
				displayName: 'Localcommons',
				getSiteLink: function () { return 'quux'; }
			},

			imageInfo: {
				author: 'Author',
				source: 'Source',
				descriptionShortUrl: 'some link',
				title: {
					getNameText: function () { return 'Image Title'; }
				}
			}
		} );

		assert.strictEqual( html, 'By Author - Source, <a href="some link">Link</a>', 'Sanity check' );

		html = formatter.getCreditHtml( {
			repoInfo: {
				displayName: 'Localcommons',
				getSiteLink: function () { return 'quux'; }
			},

			imageInfo: {
				author: 'Author',
				source: 'Source',
				descriptionShortUrl: 'some link',
				title: {
					getNameText: function () { return 'Image Title'; }
				},
				license: {
					getShortLink: function () { return '<a href="http://www.wtfpl.net/">WTFPL v2</a>'; },
					longName: 'Do What the Fuck You Want Public License Version 2',
					isFree: this.sandbox.stub().returns( true )
				}
			}
		} );

		assert.strictEqual( html, 'By Author - Source, <a href="http://www.wtfpl.net/">WTFPL v2</a>, <a href="some link">Link</a>', 'Sanity check' );
	} );
}( mediaWiki ) );
