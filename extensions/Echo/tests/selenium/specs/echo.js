'use strict';

const assert = require( 'assert' ),
	EchoPage = require( '../pageobjects/echo.page' ),
	UserLoginPage = require( 'wdio-mediawiki/LoginPage' ),
	Util = require( 'wdio-mediawiki/Util' ),
	Api = require( 'wdio-mediawiki/Api' );

describe( 'Echo', function () {
	let bot;

	before( async () => {
		bot = await Api.bot();
	} );

	it( 'alerts and notices are visible after logging in @daily', function () {

		UserLoginPage.login( browser.config.mwUser, browser.config.mwPwd );

		assert( EchoPage.alerts.isExisting() );
		assert( EchoPage.notices.isExisting() );

	} );

	it( 'flyout for alert appears when clicked @daily', function () {

		UserLoginPage.login( browser.config.mwUser, browser.config.mwPwd );
		EchoPage.alerts.click();
		EchoPage.alertsFlyout.waitForDisplayed();

		assert( EchoPage.alertsFlyout.isExisting() );

	} );

	it( 'flyout for notices appears when clicked @daily', function () {

		UserLoginPage.login( browser.config.mwUser, browser.config.mwPwd );
		EchoPage.notices.click();
		EchoPage.noticesFlyout.waitForDisplayed();

		assert( EchoPage.noticesFlyout.isExisting() );

	} );

	it( 'checks for welcome message after signup', function () {

		const username = Util.getTestString( 'NewUser-' );
		const password = Util.getTestString();
		browser.call( async () => {
			await Api.createAccount( bot, username, password );
		} );
		UserLoginPage.login( username, password );

		EchoPage.notices.click();

		EchoPage.alertMessage.waitForDisplayed();
		const regexp = /Welcome to .*, .*‬! We're glad you're here./;
		assert( regexp.test( EchoPage.alertMessage.getText() ) );

	} );

} );
