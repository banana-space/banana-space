'use strict';

const assert = require( 'assert' ),
	EchoPage = require( '../pageobjects/echo.page' ),
	UserLoginPage = require( 'wdio-mediawiki/LoginPage' ),
	Util = require( 'wdio-mediawiki/Util' ),
	Api = require( 'wdio-mediawiki/Api' );

describe( 'Mention test for Echo', function () {
	let bot;

	before( async () => {
		bot = await Api.bot();
	} );
	it.skip( 'checks if admin gets alert when mentioned', function () {

		const username = Util.getTestString( 'NewUser-' );
		const password = Util.getTestString();
		browser.call( async () => {
			await Api.createAccount( bot, username, password );
			await bot.edit( `User:${username}`, `Hello [[User:${browser.config.mwUser}]] ~~~~`, username, password );
		} );
		UserLoginPage.login( browser.config.mwUser, browser.config.mwPwd );

		EchoPage.alerts.click();

		EchoPage.alertMessage.waitForDisplayed();
		const regexp = /‪.*‬ mentioned you on ‪User:.*./;
		assert( regexp.test( EchoPage.alertMessage.getText() ) );
	} );

} );
