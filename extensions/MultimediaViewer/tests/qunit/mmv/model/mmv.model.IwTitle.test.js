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
	QUnit.module( 'mmv.model.IwTitle', QUnit.newMwEnvironment() );

	QUnit.test( 'constructor sanity test', function ( assert ) {
		var namespace = 4,
			fullPageName = 'User_talk:John_Doe',
			domain = 'en.wikipedia.org',
			url = 'https://en.wikipedia.org/wiki/User_talk:John_Doe',
			title = new mw.mmv.model.IwTitle( namespace, fullPageName, domain, url );

		assert.ok( title );
	} );

	QUnit.test( 'getters', function ( assert ) {
		var namespace = 4,
			fullPageName = 'User_talk:John_Doe',
			domain = 'en.wikipedia.org',
			url = 'https://en.wikipedia.org/wiki/User_talk:John_Doe',
			title = new mw.mmv.model.IwTitle( namespace, fullPageName, domain, url );

		assert.strictEqual( title.getUrl(), url, 'getUrl()' );
		assert.strictEqual( title.getDomain(), domain, 'getDomain()' );
		assert.strictEqual( title.getPrefixedDb(), fullPageName, 'getPrefixedDb()' );
		assert.strictEqual( title.getPrefixedText(), 'User talk:John Doe', 'getPrefixedText()' );
	} );
}() );
