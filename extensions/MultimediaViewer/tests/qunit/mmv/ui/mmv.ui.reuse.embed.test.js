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

( function () {
	var $qf = $( '#qunit-fixture' );

	QUnit.module( 'mmv.ui.reuse.Embed', QUnit.newMwEnvironment() );

	QUnit.test( 'Sanity test, object creation and UI construction', function ( assert ) {
		var embed = new mw.mmv.ui.reuse.Embed( $qf );

		assert.ok( embed, 'Embed UI element is created.' );
		assert.strictEqual( embed.$pane.length, 1, 'Pane div is created.' );
		assert.ok( embed.embedTextHtml, 'Html snipped text area created.' );
		assert.ok( embed.embedTextWikitext, 'Wikitext snipped text area created.' );
		assert.ok( embed.embedSwitch, 'Snipped selection buttons created.' );
		assert.ok( embed.embedSizeSwitchWikitext, 'Size selection menu for wikitext created.' );
		assert.ok( embed.embedSizeSwitchHtml, 'Size selection menu for html created.' );
		assert.ok( embed.currentMainEmbedText, 'Current text area created.' );
		assert.strictEqual( embed.isSizeMenuDefaultReset, false, 'Reset flag intialized correctly.' );
		assert.ok( embed.defaultHtmlItem, 'Default item for html size selection intialized.' );
		assert.ok( embed.defaultWikitextItem, 'Default item for wikitext size selection intialized.' );
		assert.ok( embed.currentSizeMenu, 'Current size menu intialized.' );
		assert.ok( embed.currentDefaultItem, 'Current default item intialized.' );
	} );

	QUnit.test( 'changeSize(): Skip if no item selected.', function ( assert ) {
		var embed = new mw.mmv.ui.reuse.Embed( $qf ),
			width = 10,
			height = 20;

		assert.expect( 0 );

		// deselect items
		embed.embedSwitch.selectItem();

		embed.updateEmbedHtml = function () {
			assert.ok( false, 'No item selected, this should not have been called.' );
		};
		embed.updateEmbedWikitext = function () {
			assert.ok( false, 'No item selected, this should not have been called.' );
		};

		embed.changeSize( width, height );
	} );

	QUnit.test( 'changeSize(): HTML size menu item selected.', function ( assert ) {
		var embed = new mw.mmv.ui.reuse.Embed( $qf ),
			width = 10,
			height = 20;

		embed.embedSwitch.findSelectedItem = function () {
			return { getData: function () { return 'html'; } };
		};
		embed.updateEmbedHtml = function ( thumb, w, h ) {
			assert.strictEqual( thumb.url, undefined, 'Empty thumbnail passed.' );
			assert.strictEqual( w, width, 'Correct width passed.' );
			assert.strictEqual( h, height, 'Correct height passed.' );
		};
		embed.updateEmbedWikitext = function () {
			assert.ok( false, 'Dealing with HTML menu, this should not have been called.' );
		};
		embed.select = function () {
			assert.ok( true, 'Item selected after update.' );
		};

		embed.changeSize( width, height );
	} );

	QUnit.test( 'changeSize(): Wikitext size menu item selected.', function ( assert ) {
		var embed = new mw.mmv.ui.reuse.Embed( $qf ),
			width = 10,
			height = 20;

		embed.embedSwitch.findSelectedItem = function () {
			return { getData: function () { return 'wikitext'; } };
		};
		embed.updateEmbedHtml = function () {
			assert.ok( false, 'Dealing with wikitext menu, this should not have been called.' );
		};
		embed.updateEmbedWikitext = function ( w ) {
			assert.strictEqual( w, width, 'Correct width passed.' );
		};
		embed.select = function () {
			assert.ok( true, 'Item selected after update.' );
		};

		embed.changeSize( width, height );
	} );

	QUnit.test( 'updateEmbedHtml(): Do nothing if set() not called before.', function ( assert ) {
		var embed = new mw.mmv.ui.reuse.Embed( $qf ),
			width = 10,
			height = 20;

		assert.expect( 0 );

		embed.formatter.getThumbnailHtml = function () {
			assert.ok( false, 'formatter.getThumbnailHtml() should not have been called.' );
		};
		embed.updateEmbedHtml( {}, width, height );
	} );

	QUnit.test( 'updateEmbedHtml():', function ( assert ) {
		var embed = new mw.mmv.ui.reuse.Embed( $qf ),
			url = 'https://upload.wikimedia.org/wikipedia/commons/3/3a/Foobar.jpg',
			thumbUrl = 'https://upload.wikimedia.org/wikipedia/thumb/Foobar.jpg',
			imageInfo = { url: url },
			repoInfo = {},
			caption = '-',
			info = {
				imageInfo: imageInfo,
				repoInfo: repoInfo,
				caption: caption
			},
			width = 10,
			height = 20;

		embed.set( imageInfo, repoInfo, caption );

		// Small image, no thumbnail info is passed
		embed.formatter.getThumbnailHtml = function ( i, u, w, h ) {
			assert.deepEqual( i, info, 'Info passed correctly.' );
			assert.strictEqual( u, url, 'Image URL passed correctly.' );
			assert.strictEqual( w, width, 'Correct width passed.' );
			assert.strictEqual( h, height, 'Correct height passed.' );
		};
		embed.updateEmbedHtml( {}, width, height );

		// Small image, thumbnail info present
		embed.formatter.getThumbnailHtml = function ( i, u ) {
			assert.strictEqual( u, thumbUrl, 'Image src passed correctly.' );
		};
		embed.updateEmbedHtml( { url: thumbUrl }, width, height );

		// Big image, thumbnail info present
		embed.formatter.getThumbnailHtml = function ( i, u ) {
			assert.strictEqual( u, url, 'Image src passed correctly.' );
		};
		width = 1300;
		embed.updateEmbedHtml( { url: thumbUrl }, width, height );
	} );

	QUnit.test( 'updateEmbedWikitext(): Do nothing if set() not called before.', function ( assert ) {
		var embed = new mw.mmv.ui.reuse.Embed( $qf ),
			width = 10;

		assert.expect( 0 );

		embed.formatter.getThumbnailWikitext = function () {
			assert.ok( false, 'formatter.getThumbnailWikitext() should not have been called.' );
		};
		embed.updateEmbedWikitext( width );
	} );

	QUnit.test( 'updateEmbedWikitext():', function ( assert ) {
		var embed = new mw.mmv.ui.reuse.Embed( $qf ),
			imageInfo = {},
			repoInfo = {},
			caption = '-',
			info = {
				imageInfo: imageInfo,
				repoInfo: repoInfo,
				caption: caption
			},
			width = 10;

		embed.set( imageInfo, repoInfo, caption );

		embed.formatter.getThumbnailWikitextFromEmbedFileInfo = function ( i, w ) {
			assert.deepEqual( i, info, 'EmbedFileInfo passed correctly.' );
			assert.strictEqual( w, width, 'Width passed correctly.' );
		};
		embed.updateEmbedWikitext( width );
	} );

	QUnit.test( 'getPossibleImageSizesForWikitext()', function ( assert ) {
		var embed = new mw.mmv.ui.reuse.Embed( $qf ),
			exampleSizes = [
				// Big wide image
				{
					width: 2048, height: 1536,
					expected: {
						small: { width: 300, height: 225 },
						medium: { width: 400, height: 300 },
						large: { width: 500, height: 375 },
						default: { width: null, height: null }
					}
				},

				// Big tall image
				{
					width: 201, height: 1536,
					expected: {
						default: { width: null, height: null }
					}
				},

				// Very small image
				{
					width: 15, height: 20,
					expected: {
						default: { width: null, height: null }
					}
				}
			],
			i, cursize, opts;
		for ( i = 0; i < exampleSizes.length; i++ ) {
			cursize = exampleSizes[ i ];
			opts = embed.getPossibleImageSizesForWikitext( cursize.width, cursize.height );
			assert.deepEqual( opts, cursize.expected, 'We got the expected results out of the size calculation function.' );
		}
	} );

	QUnit.test( 'set():', function ( assert ) {
		var embed = new mw.mmv.ui.reuse.Embed( $qf ),
			title = mw.Title.newFromText( 'File:Foobar.jpg' ),
			src = 'https://upload.wikimedia.org/wikipedia/commons/3/3a/Foobar.jpg',
			url = 'https://commons.wikimedia.org/wiki/File:Foobar.jpg',
			embedFileInfo = {
				imageInfo: title,
				repoInfo: src,
				caption: url
			},
			calledSelect = false,
			width = 15,
			height = 20;

		embed.utils.updateMenuOptions = function ( sizes, options ) {
			assert.strictEqual( options.length, 4, 'Options passed correctly.' );
		};
		embed.resetCurrentSizeMenuToDefault = function () {
			assert.ok( true, 'resetCurrentSizeMenuToDefault() is called.' );
		};
		embed.utils.getThumbnailUrlPromise = function () {
			return $.Deferred().resolve().promise();
		};
		embed.updateEmbedHtml = function () {
			assert.ok( true, 'updateEmbedHtml() is called after data is collected.' );
		};
		embed.select = function () {
			calledSelect = true;
		};

		assert.notOk( embed.embedFileInfo, 'embedFileInfo not set yet.' );

		embed.set( { width: width, height: height }, embedFileInfo );

		assert.ok( embed.embedFileInfo, 'embedFileInfo set.' );
		assert.strictEqual( embed.isSizeMenuDefaultReset, false, 'Reset flag cleared.' );
		assert.strictEqual( calledSelect, true, 'select() is called' );
	} );

	QUnit.test( 'empty():', function ( assert ) {
		var embed = new mw.mmv.ui.reuse.Embed( $qf ),
			width = 15,
			height = 20;

		embed.formatter = {
			getThumbnailWikitextFromEmbedFileInfo: function () { return 'wikitext'; },
			getThumbnailHtml: function () { return 'html'; }
		};

		embed.set( {}, {} );
		embed.updateEmbedHtml( { url: 'x' }, width, height );
		embed.updateEmbedWikitext( width );

		assert.notStrictEqual( embed.embedTextHtml.textInput.getValue(), '', 'embedTextHtml is not empty.' );
		assert.notStrictEqual( embed.embedTextWikitext.textInput.getValue(), '', 'embedTextWikitext is not empty.' );

		embed.empty();

		assert.strictEqual( embed.embedTextHtml.textInput.getValue(), '', 'embedTextHtml is empty.' );
		assert.strictEqual( embed.embedTextWikitext.textInput.getValue(), '', 'embedTextWikitext is empty.' );
		assert.strictEqual( embed.embedSizeSwitchHtml.getMenu().isVisible(), false, 'Html size menu should be hidden.' );
		assert.strictEqual( embed.embedSizeSwitchWikitext.getMenu().isVisible(), false, 'Wikitext size menu should be hidden.' );
	} );

	QUnit.test( 'attach()/unattach():', function ( assert ) {
		var embed = new mw.mmv.ui.reuse.Embed( $qf ),
			title = mw.Title.newFromText( 'File:Foobar.jpg' ),
			src = 'https://upload.wikimedia.org/wikipedia/commons/3/3a/Foobar.jpg',
			url = 'https://commons.wikimedia.org/wiki/File:Foobar.jpg',
			embedFileInfo = {
				imageInfo: title,
				repoInfo: src,
				caption: url
			},
			width = 15,
			height = 20;

		embed.set( { width: width, height: height }, embedFileInfo );

		embed.handleTypeSwitch = function () {
			assert.ok( false, 'handleTypeSwitch should not have been called.' );
		};
		embed.handleSizeSwitch = function () {
			assert.ok( false, 'handleTypeSwitch should not have been called.' );
		};

		// Triggering action events before attaching should do nothing
		embed.embedSwitch.emit( 'select' );
		embed.embedSizeSwitchHtml.getMenu().emit(
			'choose', embed.embedSizeSwitchHtml.getMenu().findSelectedItem() );
		embed.embedSizeSwitchWikitext.getMenu().emit(
			'choose', embed.embedSizeSwitchWikitext.getMenu().findSelectedItem() );

		embed.handleTypeSwitch = function () {
			assert.ok( true, 'handleTypeSwitch was called.' );
		};
		embed.handleSizeSwitch = function () {
			assert.ok( true, 'handleTypeSwitch was called.' );
		};

		embed.attach();

		// Action events should be handled now
		embed.embedSwitch.emit( 'select' );
		embed.embedSizeSwitchHtml.getMenu().emit(
			'choose', embed.embedSizeSwitchHtml.getMenu().findSelectedItem() );
		embed.embedSizeSwitchWikitext.getMenu().emit(
			'choose', embed.embedSizeSwitchWikitext.getMenu().findSelectedItem() );

		// Test the unattach part
		embed.handleTypeSwitch = function () {
			assert.ok( false, 'handleTypeSwitch should not have been called.' );
		};
		embed.handleSizeSwitch = function () {
			assert.ok( false, 'handleTypeSwitch should not have been called.' );
		};

		embed.unattach();

		// Triggering action events now that we are unattached should do nothing
		embed.embedSwitch.emit( 'select' );
		embed.embedSizeSwitchHtml.getMenu().emit(
			'choose', embed.embedSizeSwitchHtml.getMenu().findSelectedItem() );
		embed.embedSizeSwitchWikitext.getMenu().emit(
			'choose', embed.embedSizeSwitchWikitext.getMenu().findSelectedItem() );
	} );

	QUnit.test( 'handleTypeSwitch():', function ( assert ) {
		var embed = new mw.mmv.ui.reuse.Embed( $qf );

		assert.strictEqual( embed.isSizeMenuDefaultReset, false, 'Reset flag intialized correctly.' );

		embed.resetCurrentSizeMenuToDefault = function () {
			assert.ok( true, 'resetCurrentSizeMenuToDefault() called.' );
		};

		// HTML selected
		embed.handleTypeSwitch( { getData: function () { return 'html'; } } );

		assert.strictEqual( embed.isSizeMenuDefaultReset, true, 'Reset flag updated correctly.' );
		assert.strictEqual( embed.embedSizeSwitchWikitext.getMenu().isVisible(), false, 'Wikitext size menu should be hidden.' );

		embed.resetCurrentSizeMenuToDefault = function () {
			assert.ok( false, 'resetCurrentSizeMenuToDefault() should not have been called.' );
		};

		// Wikitext selected, we are done resetting defaults
		embed.handleTypeSwitch( { getData: function () { return 'wikitext'; } } );

		assert.strictEqual( embed.isSizeMenuDefaultReset, true, 'Reset flag updated correctly.' );
		assert.strictEqual( embed.embedSizeSwitchHtml.getMenu().isVisible(), false, 'HTML size menu should be hidden.' );
	} );

	QUnit.test( 'Logged out', function ( assert ) {
		var embed,
			oldUserIsAnon = mw.user.isAnon;

		mw.user.isAnon = function () { return true; };

		embed = new mw.mmv.ui.reuse.Embed( $qf );

		embed.attach();

		assert.strictEqual( embed.embedSizeSwitchWikitextLayout.isVisible(), false, 'Wikitext widget should be hidden.' );
		assert.strictEqual( embed.embedSizeSwitchHtmlLayout.isVisible(), true, 'HTML widget should be visible.' );
		assert.strictEqual( embed.embedTextWikitext.isVisible(), false, 'Wikitext input should be hidden.' );
		assert.strictEqual( embed.embedTextHtml.isVisible(), true, 'HTML input should be visible.' );

		mw.user.isAnon = oldUserIsAnon;
	} );

}() );
