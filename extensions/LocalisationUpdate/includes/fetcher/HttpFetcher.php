<?php
/**
 * @file
 * @author Niklas Laxström
 * @license GPL-2.0-or-later
 */

namespace LocalisationUpdate;

/**
 * Fetches files over HTTP(s).
 */
class HttpFetcher implements Fetcher {
	/**
	 * @param string $url
	 *
	 * @return bool|string
	 */
	public function fetchFile( $url ) {
		return \Http::get( $url );
	}

	/**
	 * This is horribly inefficient. Subclasses have more efficient
	 * implementation of this.
	 * @param string $pattern
	 * @return array
	 */
	public function fetchDirectory( $pattern ) {
		$files = [];

		$languages = \Language::fetchLanguageNames( null, 'mwfile' );

		foreach ( array_keys( $languages ) as $code ) {
			// Hack for core
			if ( strpos( $pattern, 'Messages*.php' ) !== false ) {
				$code = ucfirst( strtr( $code, '-', '_' ) );
			}

			$url = str_replace( '*', $code, $pattern );
			$file = $this->fetchFile( $url );
			if ( $file ) {
				$files[$url] = $file;
			}
		}

		return $files;
	}
}
