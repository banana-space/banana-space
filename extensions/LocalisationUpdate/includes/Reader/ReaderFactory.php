<?php
/**
 * @file
 * @author Niklas Laxström
 * @license GPL-2.0-or-later
 */

namespace LocalisationUpdate\Reader;

/**
 * Constructs readers for files based on the names.
 */
class ReaderFactory {
	/**
	 * Constructs a suitable reader for a given path.
	 * @param string $filename Usually a relative path to the file name.
	 * @return Reader
	 * @throws \Exception
	 */
	public function getReader( $filename ) {
		if ( preg_match( '/\.json/', $filename ) ) {
			$code = basename( $filename, '.json' );
			return new JSONReader( $code );
		}

		throw new \Exception( "Unknown file format: " . $filename );
	}
}
