<?php

namespace Wikimedia\CommonPasswords;

use Pleo\BloomFilter\BloomFilter;

class CommonPasswords {

	/**
	 * @return BloomFilter
	 */
	private static function getFilter() {
		static $filter = null;
		if ( $filter === null ) {
			$filter = BloomFilter::initFromJson(
				json_decode(
					file_get_contents(
						__DIR__ . '/' . ( PHP_INT_SIZE === 8 ? 'common-x64.json' : 'common-x86.json' )
					),
					true
				)
			);
		}
		return $filter;
	}

	/**
	 * @param string $password Password to check if it's considered common
	 * @return bool
	 */
	public static function isCommon( $password ) {
		return self::getFilter()->exists( $password );
	}
}
