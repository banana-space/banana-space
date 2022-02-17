<?php

namespace CirrusSearch;

use IBufferingStatsdDataFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;
use NullStatsdDataFactory;
use PoolCounterWorkViaCallback;
use Status;
use Title;
use UIDGenerator;
use WebRequest;
use Wikimedia\Assert\Assert;

/**
 * Random utility functions that don't have a better home
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */
class Util {
	/**
	 * Cache getDefaultBoostTemplates()
	 *
	 * @var array|null boost templates
	 */
	private static $defaultBoostTemplates = null;

	/**
	 * @var string|null Id identifying this php execution
	 */
	private static $executionId;

	/**
	 * Get the textual representation of a namespace with underscores stripped, varying
	 * by gender if need be (using Title::getNsText()).
	 *
	 * @param Title $title The page title to use
	 * @return string
	 */
	public static function getNamespaceText( Title $title ) {
		return strtr( $title->getNsText(), '_', ' ' );
	}

	/**
	 * Check if too arrays are recursively the same.  Values are compared with != and arrays
	 * are descended into.
	 *
	 * @param array $lhs one array
	 * @param array $rhs the other array
	 * @return bool are they equal
	 */
	public static function recursiveSame( $lhs, $rhs ) {
		if ( array_keys( $lhs ) != array_keys( $rhs ) ) {
			return false;
		}
		foreach ( $lhs as $key => $value ) {
			if ( !isset( $rhs[ $key ] ) ) {
				return false;
			}
			if ( is_array( $value ) ) {
				if ( !is_array( $rhs[ $key ] ) ) {
					return false;
				}
				if ( !self::recursiveSame( $value, $rhs[ $key ] ) ) {
					return false;
				}
			} else {
				if ( $value != $rhs[ $key ] ) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * @param string $type The pool counter type, such as CirrusSearch-Search
	 * @param bool $isSuccess If the pool counter gave a success, or failed the request
	 * @return string The key used for collecting timing stats about this pool counter request
	 */
	private static function getPoolStatsKey( $type, $isSuccess ) {
		$pos = strpos( $type, '-' );
		if ( $pos !== false ) {
			$type = substr( $type, $pos + 1 );
		}
		$postfix = $isSuccess ? 'successMs' : 'failureMs';
		return "CirrusSearch.poolCounter.$type.$postfix";
	}

	/**
	 * @param float $startPoolWork The time this pool request started, from microtime( true )
	 * @param string $type The pool counter type, such as CirrusSearch-Search
	 * @param bool $isSuccess If the pool counter gave a success, or failed the request
	 * @param callable $callback The function to wrap
	 * @return callable The original callback wrapped to collect pool counter stats
	 */
	private static function wrapWithPoolStats( $startPoolWork,
		$type,
		$isSuccess,
		callable $callback
	) {
		return function () use ( $type, $isSuccess, $callback, $startPoolWork ) {
			MediaWikiServices::getInstance()->getStatsdDataFactory()->timing(
				self::getPoolStatsKey( $type, $isSuccess ),
				intval( 1000 * ( microtime( true ) - $startPoolWork ) )
			);

			return $callback( ...func_get_args() );
		};
	}

	/**
	 * Wraps the complex pool counter interface to force the single call pattern
	 * that Cirrus always uses.
	 *
	 * @param string $type same as type parameter on PoolCounter::factory
	 * @param UserIdentity|null $user the user
	 * @param callable $workCallback callback when pool counter is acquired.  Called with
	 *  no parameters.
	 * @param string|null $busyErrorMsg The i18n key to return when the queue
	 *  is full, or null to use the default.
	 * @return mixed
	 */
	public static function doPoolCounterWork( $type, $user, $workCallback, $busyErrorMsg = null ) {
		global $wgCirrusSearchPoolCounterKey;

		// By default the pool counter allows you to lock the same key with
		// multiple types.  That might be useful but it isn't how Cirrus thinks.
		// Instead, all keys are scoped to their type.

		if ( !$user ) {
			// We don't want to even use the pool counter if there isn't a user.
			// Note that anonymous users are still users, this is most likely
			// maintenance scripts.
			// @todo Maintenenace scripts and jobs should already override
			// poolcounters as necessary, can this be removed?
			return $workCallback();
		}

		$key = "$type:$wgCirrusSearchPoolCounterKey";

		$errorCallback = function ( Status $status ) use ( $key, $busyErrorMsg ) {
			/** @todo No good replacements for getErrorsArray */
			$errors = $status->getErrorsArray();
			$error = $errors[0][0];

			LoggerFactory::getInstance( 'CirrusSearch' )->warning(
				"Pool error on {key}:  {error}",
				[ 'key' => $key, 'error' => $error ]
			);
			if ( $error === 'pool-queuefull' ) {
				return Status::newFatal( $busyErrorMsg ?: 'cirrussearch-too-busy-error' );
			}
			return Status::newFatal( 'cirrussearch-backend-error' );
		};

		// wrap some stats collection on the success/failure handlers
		$startPoolWork = microtime( true );
		$workCallback = self::wrapWithPoolStats( $startPoolWork, $type, true, $workCallback );
		$errorCallback = self::wrapWithPoolStats( $startPoolWork, $type, false, $errorCallback );

		$work = new PoolCounterWorkViaCallback( $type, $key, [
			'doWork' => $workCallback,
			'error' => $errorCallback,
		] );
		return $work->execute();
	}

	/**
	 * @param string $str
	 * @return float
	 */
	public static function parsePotentialPercent( $str ) {
		$result = floatval( $str );
		if ( strpos( $str, '%' ) === false ) {
			return (float)$result;
		}
		return $result / 100;
	}

	/**
	 * Parse a message content into an array. This function is generally used to
	 * parse settings stored as i18n messages (see cirrussearch-boost-templates).
	 *
	 * @param string $message
	 * @return string[]
	 */
	public static function parseSettingsInMessage( $message ) {
		$lines = explode( "\n", $message );
		$lines = preg_replace( '/#.*$/', '', $lines ); // Remove comments
		$lines = array_map( 'trim', $lines );          // Remove extra spaces
		$lines = array_filter( $lines );               // Remove empty lines
		return $lines;
	}

	/**
	 * Test if $string ends with $suffix
	 *
	 * @param string $string string to test
	 * @param string $suffix
	 * @return bool true if $string ends with $suffix
	 */
	public static function endsWith( $string, $suffix ) {
		$strlen = strlen( $string );
		$suffixlen = strlen( $suffix );
		if ( $suffixlen > $strlen ) {
			return false;
		}
		return substr_compare( $string, $suffix, $strlen - $suffixlen, $suffixlen ) === 0;
	}

	/**
	 * Set $dest to the true/false from $request->getVal( $name ) if yes/no.
	 *
	 * @param mixed &$dest
	 * @param WebRequest $request
	 * @param string $name
	 */
	public static function overrideYesNo( &$dest, $request, $name ) {
		$val = $request->getVal( $name );
		if ( $val !== null ) {
			$dest = wfStringToBool( $val );
		}
	}

	/**
	 * Set $dest to the numeric value from $request->getVal( $name ) if it is <= $limit
	 * or => $limit if upperLimit is false.
	 *
	 * @param mixed &$dest
	 * @param WebRequest $request
	 * @param string $name
	 * @param int|null $limit
	 * @param bool $upperLimit
	 */
	public static function overrideNumeric( &$dest, $request, $name, $limit = null, $upperLimit = true ) {
		$val = $request->getVal( $name );
		if ( $val !== null && is_numeric( $val ) ) {
			if ( !isset( $limit ) ) {
				$dest = $val;
			} elseif ( $upperLimit && $val <= $limit ) {
				$dest = $val;
			} elseif ( !$upperLimit && $val >= $limit ) {
				$dest = $val;
			}
		}
	}

	/**
	 * Get boost templates configured in messages.
	 * @param SearchConfig|null $config Search config requesting the templates
	 * @return float[]
	 */
	public static function getDefaultBoostTemplates( SearchConfig $config = null ) {
		if ( $config === null ) {
			$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'CirrusSearch' );
		}

		$fromConfig = $config->get( 'CirrusSearchBoostTemplates' );
		if ( $config->get( 'CirrusSearchIgnoreOnWikiBoostTemplates' ) ) {
			// on wiki messages disabled, we can return this config
			// directly
			return $fromConfig;
		}

		$fromMessage = self::getOnWikiBoostTemplates( $config );
		if ( empty( $fromMessage ) ) {
			// the onwiki config is empty (or unknown for non-local
			// config), we can fallback to templates from config
			return $fromConfig;
		}
		return $fromMessage;
	}

	/**
	 * Load and cache boost templates configured on wiki via the system
	 * message 'cirrussearch-boost-templates'.
	 * If called from the local wiki the message will be cached.
	 * If called from a non local wiki an attempt to fetch this data from the cache is made.
	 * If an empty array is returned it means that no config is available on wiki
	 * or the value possibly unknown if run from a non local wiki.
	 *
	 * @param SearchConfig $config
	 * @return float[] indexed by template name
	 */
	private static function getOnWikiBoostTemplates( SearchConfig $config ) {
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$cacheKey = $cache->makeGlobalKey( 'cirrussearch-boost-templates', $config->getWikiId() );
		if ( $config->getWikiId() == wfWikiID() ) {
			// Local wiki we can fetch boost templates from system
			// message
			if ( self::$defaultBoostTemplates !== null ) {
				// This static cache is never set with non-local
				// wiki data.
				return self::$defaultBoostTemplates;
			}

			$templates = $cache->getWithSetCallback(
				$cacheKey,
				600,
				function () {
					$source = wfMessage( 'cirrussearch-boost-templates' )->inContentLanguage();
					if ( !$source->isDisabled() ) {
						$lines = Util::parseSettingsInMessage( $source->plain() );
						// Now parse the templates
						return Query\BoostTemplatesFeature::parseBoostTemplates( implode( ' ', $lines ) );
					}
					return [];
				}
			);
			self::$defaultBoostTemplates = $templates;
			return $templates;
		}
		// Here we're dealing with boost template from other wiki, try to fetch it if it exists
		// otherwise, don't bother.
		$nonLocalCache = $cache->get( $cacheKey );
		if ( !is_array( $nonLocalCache ) ) {
			// not yet in cache, value is unknown
			// return empty array
			return [];
		}
		return $nonLocalCache;
	}

	/**
	 * Strip question marks from queries, according to the defined stripping
	 * level, defined by $wgCirrusSearchStripQuestionMarks. Strip all ?s, those
	 * at word breaks, or only string-final. Ignore queries that are all
	 * punctuation or use insource. Don't remove escaped \?s, but unescape them.
	 * ¿ is not :punct:, hence $more_punct.
	 *
	 * @param string $term
	 * @param string $strippingLevel
	 * @return string modified term, based on strippingLevel
	 */
	public static function stripQuestionMarks( $term, $strippingLevel ) {
		// strip question marks
		$more_punct = "[¿]";
		if ( strpos( $term, 'insource:/' ) === false &&
			 strpos( $term, 'intitle:/' ) === false &&
			preg_match( "/^([[:punct:]]|\s|$more_punct)+$/", $term ) === 0
		) {
			// FIXME: get rid of negative lookbehinds on (?<!\\\\)
			// it may improperly transform \\? into \? instead of \\ and destroy properly escaped \
			if ( $strippingLevel === 'final' ) {
				// strip only query-final question marks that are not escaped
				$term = preg_replace( "/((?<!\\\\)\?|\s)+$/", '', $term );
				$term = preg_replace( '/\\\\\?/', '?', $term );
			} elseif ( $strippingLevel === 'break' ) {
				// strip question marks at word boundaries
				$term = preg_replace( '/(?<!\\\\)(\?)+(\PL|$)/', '$2', $term );
				$term = preg_replace( '/\\\\\?/', '?', $term );
			} elseif ( $strippingLevel === 'all' ) {
				// strip all unescaped question marks
				$term = preg_replace( '/(?<!\\\\)(\?)+/', ' ', $term );
				$term = preg_replace( '/\\\\\?/', '?', $term );
			}
		}
		return $term;
	}

	/**
	 * Identifies a specific execution of php. That might be one web
	 * request, or multiple jobs run in the same executor. An execution id
	 * is valid over a brief timespan, perhaps a minute or two for some jobs.
	 *
	 * @return string unique identifier
	 */
	public static function getExecutionId() {
		if ( self::$executionId === null ) {
			self::$executionId = (string)mt_rand();
		}
		return self::$executionId;
	}

	/**
	 * Unit tests only
	 */
	public static function resetExecutionId() {
		self::$executionId = null;
	}

	/**
	 * Get a token that (hopefully) uniquely identifies this search. It will be
	 * added to the search result page js config vars, and put into the url with
	 * history.replaceState(). This means click through's from supported browsers
	 * will record this token as part of the referrer.
	 *
	 * @return string
	 */
	public static function getRequestSetToken() {
		static $token;
		if ( $token === null ) {
			// random UID, 70B tokens have a collision probability of 4*10^-16
			// so should work for marking unique queries.
			$uuid = UIDGenerator::newUUIDv4();
			// make it a little shorter by using straight base36
			$hex = substr( $uuid, 0, 8 ) . substr( $uuid, 9, 4 ) .
				substr( $uuid, 14, 4 ) . substr( $uuid, 19, 4 ) .
				substr( $uuid, 24 );
			$token = \Wikimedia\base_convert( $hex, 16, 36 );
		}
		return $token;
	}

	/**
	 * @param string $extraData Extra information to mix into the hash
	 * @return string A token that identifies the source of the request
	 */
	public static function generateIdentToken( $extraData = '' ) {
		$request = \RequestContext::getMain()->getRequest();
		try {
			$ip = $request->getIP();
		} catch ( \MWException $e ) {
			// No ip, probably running cli?
			$ip = 'unknown';
		}
		return md5( implode( ':', [
			$extraData,
			$ip,
			$request->getHeader( 'X-Forwarded-For' ),
			$request->getHeader( 'User-Agent' ),
		] ) );
	}

	/**
	 * @return string The context the request is in. Either cli, api, web or misc.
	 */
	public static function getExecutionContext() {
		if ( PHP_SAPI === 'cli' ) {
			return 'cli';
		} elseif ( MW_ENTRY_POINT == 'api' ) {
			return 'api';
		} elseif ( MW_ENTRY_POINT == 'index' ) {
			return 'web';
		} else {
			return 'misc';
		}
	}

	/**
	 * Identify a namespace by attempting some unicode folding techniques.
	 * 2 methods supported:
	 * - naive: case folding + naive accents removal (only some combined accents are removed)
	 * - utr30: (slow to load) case folding + strong accent squashing based on the withdrawn UTR30 specs
	 * all methods will apply something similar to near space flattener.
	 * @param string $namespace name of the namespace to identify
	 * @param string $method either naive or utr30
	 * @param \Language|null $language
	 * @return bool|int
	 */
	public static function identifyNamespace( $namespace, $method = 'naive', \Language $language = null ) {
		static $naive = null;
		static $utr30 = null;

		$normalizer = null;
		if ( $method === 'naive' ) {
			if ( $naive === null ) {
				$naive = \Transliterator::createFromRules(
					'::NFD;::Upper;::Lower;::[:Nonspacing Mark:] Remove;::NFC;[\_\-\'\u2019\u02BC]>\u0020;'
				);
			}
			$normalizer = $naive;
		} elseif ( $method === 'utr30' ) {
			if ( $utr30 === null ) {
				$utr30 =
				$normalizer = \Transliterator::createFromRules( file_get_contents( __DIR__ . '/../data/utr30.txt' ) );
			}
			$normalizer = $utr30;
		}

		Assert::postcondition( $normalizer !== null,
			'Failed to load Transliterator with method ' . $method );
		if ( $language === null ) {
			$language = MediaWikiServices::getInstance()->getContentLanguage();
		}
		$namespace = $normalizer->transliterate( $namespace );
		if ( $namespace === '' ) {
			return false;
		}
		foreach ( $language->getNamespaceIds() as $candidate => $nsId ) {
			if ( $normalizer->transliterate( $candidate ) === $namespace ) {
				return $nsId;
			}
		}

		return false;
	}

	/**
	 * Helper for PHP's annoying emptiness check.
	 * empty(0) should not be true!
	 * empty(false) should not be true!
	 * Empty arrays, strings, and nulls/undefined count as empty.
	 *
	 * False otherwise.
	 * @param mixed $v
	 * @return bool
	 */
	public static function isEmpty( $v ) {
		return ( is_array( $v ) && count( $v ) === 0 ) ||
			( is_object( $v ) && count( (array)$v ) === 0 ) ||
			( is_string( $v ) && strlen( $v ) === 0 ) ||
			( $v === null );
	}

	/**
	 * Helper function to conditionally set a key in a dest array only if it
	 * is defined in a source array.  This is just to help DRY up what would
	 * otherwise could be a long series of
	 * if ( isset($sourceArray[$key] )) { $destArray[$key] = $sourceArray[$key] }
	 * statements.  This also supports using a different key in the dest array,
	 * as well as mapping the value when assigning to $sourceArray.
	 *
	 * Usage:
	 * $arr1 = ['KEY1' => '123'];
	 * $arr2 = [];
	 *
	 * setIfDefined($arr1, 'KEY1', $arr2, 'key1', 'intval');
	 * // $arr2['key1'] is now set to 123 (integer value)
	 *
	 * setIfDefined($arr1, 'KEY2', $arr2);
	 * // $arr2 stays the same, because $arr1 does not have 'KEY2' defined.
	 *
	 * @param array $sourceArray the array from which to look for $sourceKey
	 * @param string $sourceKey the key to look for in $sourceArray
	 * @param array &$destArray by reference destination array in which to set value if defined
	 * @param string|null $destKey optional, key to use instead of $sourceKey in $destArray.
	 * @param callable|null $mapFn optional, If set, this will be called on the value before setting it.
	 * @param bool $checkEmpty If false, emptyiness of result after $mapFn is called will not be
	 * 				checked before setting on $destArray.  If true, it will, using Util::isEmpty.
	 * 				Default: true
	 * @return array $destArray
	 */
	public static function setIfDefined(
		array $sourceArray,
		$sourceKey,
		array &$destArray,
		$destKey = null,
		$mapFn = null,
		$checkEmpty = true
	) {
		if ( array_key_exists( $sourceKey, $sourceArray ) ) {
			$val = $sourceArray[$sourceKey];
			if ( $mapFn !== null ) {
				$val = $mapFn( $val );
			}
			// Only set in $destArray if we are not checking emptiness,
			// or if we are and the $val is not empty.
			if ( !$checkEmpty || !self::isEmpty( $val ) ) {
				$key = $destKey ?: $sourceKey;
				$destArray[$key] = $val;
			}
		}
		return $destArray;
	}

	/**
	 * @return IBufferingStatsdDataFactory
	 */
	public static function getStatsDataFactory(): IBufferingStatsdDataFactory {
		if ( defined( 'MW_PHPUNIT_TEST' ) ) {
			return new NullStatsdDataFactory();
		}
		return MediaWikiServices::getInstance()->getStatsdDataFactory();
	}
}
