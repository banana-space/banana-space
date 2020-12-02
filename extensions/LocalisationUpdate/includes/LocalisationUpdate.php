<?php

namespace LocalisationUpdate;

use FileDependency;
use FormatJson;
use LocalisationCache;

/**
 * Class for localization update hooks and static methods.
 */
class LocalisationUpdate {
	/**
	 * Hook: LocalisationCacheRecacheFallback
	 * @param LocalisationCache $lc
	 * @param string $code
	 * @param array &$cache
	 */
	public static function onRecacheFallback( LocalisationCache $lc, $code, array &$cache ) {
		$dir = self::getDirectory();
		if ( !$dir ) {
			return;
		}

		$fileName = "$dir/" . self::getFilename( $code );
		if ( is_readable( $fileName ) ) {
			$data = FormatJson::decode( file_get_contents( $fileName ), true );
			$cache['messages'] = array_merge( $cache['messages'], $data );
		}
	}

	/**
	 * Hook: LocalisationCacheRecache
	 * @param LocalisationCache $lc
	 * @param string $code
	 * @param array &$cache
	 */
	public static function onRecache( LocalisationCache $lc, $code, array &$cache ) {
		$dir = self::getDirectory();
		if ( !$dir ) {
			return;
		}

		$codeSequence = array_merge( [ $code ], $cache['fallbackSequence'] );
		foreach ( $codeSequence as $csCode ) {
			$fileName = "$dir/" . self::getFilename( $csCode );
			$cache['deps'][] = new FileDependency( $fileName );
		}
	}

	/**
	 * Returns a directory where updated translations are stored.
	 *
	 * @return string|false False if not configured.
	 * @since 1.1
	 */
	public static function getDirectory() {
		global $wgLocalisationUpdateDirectory, $wgCacheDirectory;

		return $wgLocalisationUpdateDirectory ?: $wgCacheDirectory;
	}

	/**
	 * Returns a filename where updated translations are stored.
	 *
	 * @param string $language Language tag
	 * @return string
	 * @since 1.1
	 */
	public static function getFilename( $language ) {
		return "l10nupdate-$language.json";
	}
}
