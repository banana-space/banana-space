'use strict';

const assert = require( 'assert' ),
	NotificationsPage = require( '../pageobjects/notifications.page' ),
	UserLoginPage = require( 'wdio-mediawiki/LoginPage' );

describe( 'Notifications', function () {

	it( 'checks for Notifications Page @daily', function () {

		UserLoginPage.login( browser.config.mwUser, browser.config.mwPwd );
		NotificationsPage.open();

		assert.strictEqual( NotificationsPage.notificationHeading.getText(), 'Notifications' );

	} );

} );
