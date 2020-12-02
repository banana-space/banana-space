<?php
/**
 * @file
 * @author Niklas Laxström
 * @license GPL-2.0-or-later
 */

namespace LocalisationUpdate\Fetcher;

/**
 * Accesses file system directly.
 */
class FileSystemFetcher implements Fetcher {
	/**
	 * @param string $url
	 *
	 * @return bool|string
	 */
	public function fetchFile( $url ) {
		// Remove the protocol prefix
		$url = preg_replace( '~^file://~', '', $url );

		if ( !is_readable( $url ) ) {
			return false;
		}

		return file_get_contents( $url );
	}

	/**
	 * @param string $pattern
	 *
	 * @return array
	 */
	public function fetchDirectory( $pattern ) {
		// Remove the protocol prefix
		$pattern = preg_replace( '~^file://~', '', $pattern );

		$data = [];
		foreach ( glob( $pattern ) as $file ) {
			if ( is_readable( $file ) ) {
				$data["file://$file"] = file_get_contents( $file );
			}
		}
		return $data;
	}
}
