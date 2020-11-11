<?php

/**
 * A class with constants of the CAPTCHA triggers built-in in ConfirmEdit. Other extensions may
 * add more possible triggers, which are not included in this class.
 */
abstract class CaptchaTriggers {
	const EDIT = 'edit';
	const CREATE = 'create';
	const SENDEMAIL = 'sendemail';
	const ADD_URL = 'addurl';
	const CREATE_ACCOUNT = 'createaccount';
	const BAD_LOGIN = 'badlogin';
	const BAD_LOGIN_PER_USER = 'badloginperuser';

	const EXT_REG_ATTRIBUTE_NAME = 'CaptchaTriggers';
}
