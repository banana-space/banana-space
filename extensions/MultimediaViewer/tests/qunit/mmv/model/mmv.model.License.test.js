/*
 * This file is part of the MediaWiki extension MediaViewer.
 *
 * MediaViewer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * MediaViewer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with MediaViewer.  If not, see <http://www.gnu.org/licenses/>.
 */

( function () {

	QUnit.module( 'mmv.model.License', QUnit.newMwEnvironment() );

	QUnit.test( 'License constructor sanity check', function ( assert ) {
		var license,
			shortName = 'CC-BY-SA-3.0',
			internalName = 'cc-by-sa-3.0',
			longName = 'Creative Commons Attribution--Share-Alike 3.0',
			url = 'http://creativecommons.org/licenses/by-sa/3.0/';

		license = new mw.mmv.model.License( shortName );
		assert.ok( license, 'License created successfully' );
		assert.strictEqual( license.shortName, shortName, 'License has correct short name' );
		assert.notOk( license.internalName, 'License has no internal name' );
		assert.notOk( license.longName, 'License has no long name' );
		assert.notOk( license.deedUrl, 'License has no deed URL' );

		license = new mw.mmv.model.License( shortName, internalName, longName, url );
		assert.ok( license, 'License created successfully' );
		assert.strictEqual( license.shortName, shortName, 'License has correct short name' );
		assert.strictEqual( license.internalName, internalName, 'License has correct internal name' );
		assert.strictEqual( license.longName, longName, 'License has correct long name' );
		assert.strictEqual( license.deedUrl, url, 'License has correct deed URL' );

		try {
			license = new mw.mmv.model.License();
		} catch ( e ) {
			assert.ok( e, 'License cannot be created without a short name' );
		}
	} );

	QUnit.test( 'getShortName()', function ( assert ) {
		var existingMessageKey = 'Internal name that does exist',
			nonExistingMessageKey = 'Internal name that does not exist',
			license1 = new mw.mmv.model.License( 'Shortname' ),
			license2 = new mw.mmv.model.License( 'Shortname', nonExistingMessageKey ),
			license3 = new mw.mmv.model.License( 'Shortname', existingMessageKey ),
			oldMwMessage = mw.message,
			oldMwMessagesExists = mw.messages.exists;

		mw.message = function ( name ) {
			return name === 'multimediaviewer-license-' + existingMessageKey ?
				{ text: function () { return 'Translated name'; } } :
				oldMwMessage.apply( mw, arguments );
		};
		mw.messages.exists = function ( name ) {
			return name === 'multimediaviewer-license-' + existingMessageKey ?
				true : oldMwMessagesExists.apply( mw.messages, arguments );
		};

		assert.strictEqual( license1.getShortName(), 'Shortname',
			'Short name is returned when there is no translated name' );
		assert.strictEqual( license2.getShortName(), 'Shortname',
			'Short name is returned when translated name is missing' );
		assert.strictEqual( license3.getShortName(), 'Translated name',
			'Translated name is returned when it exists' );

		mw.message = oldMwMessage;
		mw.messages.exists = oldMwMessagesExists;
	} );

	QUnit.test( 'getShortLink()', function ( assert ) {
		var $html,
			license1 = new mw.mmv.model.License( 'lorem ipsum' ),
			license2 = new mw.mmv.model.License( 'lorem ipsum', 'lipsum' ),
			license3 = new mw.mmv.model.License( 'lorem ipsum', 'lipsum', 'Lorem ipsum dolor sit amet' ),
			license4 = new mw.mmv.model.License( 'lorem ipsum', 'lipsum', 'Lorem ipsum dolor sit amet',
				'http://www.lipsum.com/' );

		assert.strictEqual( license1.getShortLink(), 'lorem ipsum',
			'Code for license without link is formatted correctly' );
		assert.strictEqual( license2.getShortLink(), 'lorem ipsum',
			'Code for license without link is formatted correctly' );
		assert.strictEqual( license3.getShortLink(), 'lorem ipsum',
			'Code for license without link is formatted correctly' );

		$html = $( license4.getShortLink() );
		assert.strictEqual( $html.text(), 'lorem ipsum',
			'Text for license with link is formatted correctly' );
		assert.strictEqual( $html.prop( 'href' ), 'http://www.lipsum.com/',
			'URL for license with link is formatted correctly' );
		assert.strictEqual( $html.prop( 'title' ), 'Lorem ipsum dolor sit amet',
			'Title for license with link is formatted correctly' );
	} );

	QUnit.test( 'isCc()', function ( assert ) {
		var license;

		license = new mw.mmv.model.License( 'CC-BY-SA-2.0', 'cc-by-sa-2.0',
			'Creative Commons Attribution - ShareAlike 2.0',
			'http://creativecommons.org/licenses/by-sa/2.0/' );
		assert.strictEqual( license.isCc(), true, 'CC license recognized' );

		license = new mw.mmv.model.License( 'Public Domain', 'pd',
			'Public Domain for lack of originality' );
		assert.strictEqual( license.isCc(), false, 'Non-CC license not recognized' );

		license = new mw.mmv.model.License( 'MIT' );
		assert.strictEqual( license.isCc(), false, 'Non-CC license with no internal name not recognized' );
	} );

	QUnit.test( 'isPd()', function ( assert ) {
		var license;

		license = new mw.mmv.model.License( 'Public Domain', 'pd',
			'Public Domain for lack of originality' );
		assert.strictEqual( license.isPd(), true, 'PD license recognized' );

		license = new mw.mmv.model.License( 'CC-BY-SA-2.0', 'cc-by-sa-2.0',
			'Creative Commons Attribution - ShareAlike 2.0',
			'http://creativecommons.org/licenses/by-sa/2.0/' );
		assert.strictEqual( license.isPd(), false, 'Non-PD license not recognized' );

		license = new mw.mmv.model.License( 'MIT' );
		assert.strictEqual( license.isPd(), false, 'Non-PD license with no internal name not recognized' );
	} );

	QUnit.test( 'isFree()', function ( assert ) {
		var license;

		license = new mw.mmv.model.License( 'CC-BY-SA-2.0', 'cc-by-sa-2.0',
			'Creative Commons Attribution - ShareAlike 2.0',
			'http://creativecommons.org/licenses/by-sa/2.0/' );
		assert.strictEqual( license.isFree(), true, 'Licenses default to free' );

		license = new mw.mmv.model.License( 'Fair use', 'fairuse',
			'Fair use', undefined, undefined, true );
		assert.strictEqual( license.isFree(), false, 'Non-free flag handled correctly' );
	} );

	QUnit.test( 'needsAttribution()', function ( assert ) {
		var license;

		license = new mw.mmv.model.License( 'CC-BY-SA-2.0', 'cc-by-sa-2.0',
			'Creative Commons Attribution - ShareAlike 2.0',
			'http://creativecommons.org/licenses/by-sa/2.0/' );
		assert.strictEqual( license.needsAttribution(), true, 'Licenses assumed to need attribution by default' );

		license = new mw.mmv.model.License( 'Public Domain', 'pd',
			'Public Domain for lack of originality', false );
		assert.strictEqual( license.needsAttribution(), false, 'Attribution required flag handled correctly' );
	} );
}() );
