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
	QUnit.module( 'mmv.provider.ImageInfo', QUnit.newMwEnvironment() );

	QUnit.test( 'ImageInfo constructor sanity check', function ( assert ) {
		var api = { get: function () {} },
			imageInfoProvider = new mw.mmv.provider.ImageInfo( api );

		assert.ok( imageInfoProvider );
	} );

	QUnit.test( 'ImageInfo get test', function ( assert ) {
		var apiCallCount = 0,
			api = { get: function () {
				apiCallCount++;
				return $.Deferred().resolve( {
					query: {
						pages: {
							'-1': {
								ns: 6,
								title: 'File:Stuff.jpg',
								missing: '',
								imagerepository: 'shared',
								imageinfo: [
									{
										timestamp: '2013-08-25T14:41:02Z',
										userid: '3053121',
										size: 346684,
										width: 720,
										height: 1412,
										comment: 'User created page with UploadWizard',
										url: 'https://upload.wikimedia.org/wikipedia/commons/1/19/Stuff.jpg',
										descriptionurl: 'https://commons.wikimedia.org/wiki/File:Stuff.jpg',
										sha1: 'a1ba23d471f4dad208b71c143e2e105a0e3032db',
										metadata: [],
										extmetadata: {
											ObjectName: {
												value: 'Some stuff',
												source: 'commons-templates'
											},
											License: {
												value: 'cc0',
												source: 'commons-templates',
												hidden: ''
											},
											LicenseShortName: {
												value: 'CC0',
												source: 'commons-templates'
											},
											UsageTerms: {
												value: 'Creative Commons Public Domain Dedication',
												source: 'commons-templates'
											},
											LicenseUrl: {
												value: 'http://creativecommons.org/publicdomain/zero/1.0/',
												source: 'commons-templates'
											},
											GPSLatitude: {
												value: '90.000000',
												source: 'commons-desc-page'
											},
											GPSLongitude: {
												value: ' 180.000000',
												source: 'commons-desc-page'
											},
											ImageDescription: {
												value: 'Wikis stuff',
												source: 'commons-desc-page'
											},
											DateTimeOriginal: {
												value: '<time class="dtstart" datetime="2009-02-18">18 February 2009</time>\u00a0(according to <a href="//en.wikipedia.org/wiki/Exchangeable_image_file_format" class="extiw" title="en:Exchangeable image file format">EXIF</a> data)',
												source: 'commons-desc-page'
											},
											DateTime: {
												value: '2013-08-25T14:41:02Z',
												source: 'commons-desc-page'
											},
											Credit: {
												value: 'Wikipedia',
												source: 'commons-desc-page',
												hidden: ''
											},
											Artist: {
												value: 'John Smith',
												source: 'commons-desc-page'
											},
											AuthorCount: {
												value: '2',
												source: 'commons-desc-page'
											},
											Attribution: {
												value: 'By John Smith',
												source: 'commons-desc-page'
											},
											Permission: {
												value: 'Do not use. Ever.',
												source: 'commons-desc-page'
											},
											AttributionRequired: {
												value: 'no',
												source: 'commons-desc-page'
											},
											NonFree: {
												value: 'yes',
												source: 'commons-desc-page'
											},
											Restrictions: {
												value: 'trademarked|insignia',
												source: 'commons-desc-page'
											},
											DeletionReason: {
												value: 'copyvio',
												source: 'commons-desc-page'
											}
										},
										mime: 'image/jpeg',
										mediatype: 'BITMAP'
									}
								]
							}
						}
					}
				} );
			} },
			file = new mw.Title( 'File:Stuff.jpg' ),
			imageInfoProvider = new mw.mmv.provider.ImageInfo( api );

		return imageInfoProvider.get( file ).then( function ( image ) {
			assert.strictEqual( image.title.getPrefixedDb(), 'File:Stuff.jpg', 'title is set correctly' );
			assert.strictEqual( image.name, 'Some stuff', 'name is set correctly' );
			assert.strictEqual( image.size, 346684, 'size is set correctly' );
			assert.strictEqual( image.width, 720, 'width is set correctly' );
			assert.strictEqual( image.height, 1412, 'height is set correctly' );
			assert.strictEqual( image.mimeType, 'image/jpeg', 'mimeType is set correctly' );
			assert.strictEqual( image.url, 'https://upload.wikimedia.org/wikipedia/commons/1/19/Stuff.jpg', 'url is set correctly' );
			assert.strictEqual( image.descriptionUrl, 'https://commons.wikimedia.org/wiki/File:Stuff.jpg', 'descriptionUrl is set correctly' );
			assert.strictEqual( image.repo, 'shared', 'repo is set correctly' );
			assert.strictEqual( image.uploadDateTime, '2013-08-25T14:41:02Z', 'uploadDateTime is set correctly' );
			assert.strictEqual( image.anonymizedUploadDateTime, '20130825000000', 'anonymizedUploadDateTime is set correctly' );
			assert.strictEqual( image.creationDateTime, '18 February 2009\u00a0(according to EXIF data)', 'creationDateTime is set correctly' );
			assert.strictEqual( image.description, 'Wikis stuff', 'description is set correctly' );
			assert.strictEqual( image.source, 'Wikipedia', 'source is set correctly' );
			assert.strictEqual( image.author, 'John Smith', 'author is set correctly' );
			assert.strictEqual( image.authorCount, 2, 'author count is set correctly' );
			assert.strictEqual( image.attribution, 'By John Smith', 'attribution is set correctly' );
			assert.strictEqual( image.license.shortName, 'CC0', 'license short name is set correctly' );
			assert.strictEqual( image.license.internalName, 'cc0', 'license internal name is set correctly' );
			assert.strictEqual( image.license.longName, 'Creative Commons Public Domain Dedication', 'license long name is set correctly' );
			assert.strictEqual( image.license.deedUrl, 'http://creativecommons.org/publicdomain/zero/1.0/', 'license URL is set correctly' );
			assert.strictEqual( image.license.attributionRequired, false, 'Attribution required flag is honored' );
			assert.strictEqual( image.license.nonFree, true, 'Non-free flag is honored' );
			assert.strictEqual( image.permission, 'Do not use. Ever.', 'permission is set correctly' );
			assert.strictEqual( image.deletionReason, 'copyvio', 'permission is set correctly' );
			assert.strictEqual( image.latitude, 90, 'latitude is set correctly' );
			assert.strictEqual( image.longitude, 180, 'longitude is set correctly' );
			assert.deepEqual( image.restrictions, [ 'trademarked', 'insignia' ], 'restrictions is set correctly' );
		} ).then( function () {
			// call the data provider a second time to check caching
			return imageInfoProvider.get( file );
		} ).then( function () {
			assert.strictEqual( apiCallCount, 1 );
		} );
	} );

	QUnit.test( 'ImageInfo fail test', function ( assert ) {
		var api = { get: function () {
				return $.Deferred().resolve( {} );
			} },
			file = new mw.Title( 'File:Stuff.jpg' ),
			done = assert.async(),
			imageInfoProvider = new mw.mmv.provider.ImageInfo( api );

		imageInfoProvider.get( file ).fail( function () {
			assert.ok( true, 'promise rejected when no data is returned' );
			done();
		} );
	} );

	QUnit.test( 'ImageInfo fail test 2', function ( assert ) {
		var api = { get: function () {
				return $.Deferred().resolve( {
					query: {
						pages: {
							'-1': {
								title: 'File:Stuff.jpg'
							}
						}
					}
				} );
			} },
			file = new mw.Title( 'File:Stuff.jpg' ),
			done = assert.async(),
			imageInfoProvider = new mw.mmv.provider.ImageInfo( api );

		imageInfoProvider.get( file ).fail( function () {
			assert.ok( true, 'promise rejected when imageinfo is missing' );
			done();
		} );
	} );

	QUnit.test( 'ImageInfo missing page test', function ( assert ) {
		var api = { get: function () {
				return $.Deferred().resolve( {
					query: {
						pages: {
							'-1': {
								title: 'File:Stuff.jpg',
								missing: '',
								imagerepository: ''
							}
						}
					}
				} );
			} },
			file = new mw.Title( 'File:Stuff.jpg' ),
			done = assert.async(),
			imageInfoProvider = new mw.mmv.provider.ImageInfo( api );

		imageInfoProvider.get( file ).fail( function ( errorMessage ) {
			assert.strictEqual( errorMessage, 'file does not exist: File:Stuff.jpg',
				'error message is set correctly for missing file' );
			done();
		} );
	} );
}( mediaWiki, jQuery ) );
