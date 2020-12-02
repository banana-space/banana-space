<?php

namespace Wikimedia\PasswordBlacklist;

use Wikimedia\CommonPasswords\CommonPasswords;

/**
 * @deprecated since 0.2.0, use Wikimedia\CommonPasswords\CommonPasswords
 */
class PasswordBlacklist {
	/**
	 * @deprecated since 0.2.0 use Wikimedia\CommonPasswords\CommonPasswords::isCommon()
	 * @param string $password Password to check if it's considered common
	 * @return bool
	 */
	public static function isBlacklisted( $password ) {
		return CommonPasswords::isCommon( $password );
	}
}
