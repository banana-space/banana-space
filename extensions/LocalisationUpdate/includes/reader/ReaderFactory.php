<?php
/**
 * @file
 * @author Niklas Laxström
 * @license GPL-2.0-or-later
 */

namespace LocalisationUpdate;

/**
 * Constructs readers for files based on the names.
 */
class ReaderFactory {
	/**
	 * Constructs a suitable reader for a given path.
	 * @param string $filename Usually a relative path to the file name.
	 * @return Reader
	 * @throws Exception
	 */
	public function getReader( $filename ) {
		if ( preg_match( '/i18n\.php$/', $filename ) ) {
			return new PHPReader();
		}

		// Ugly hack for core i18n files
		if ( preg_match( '/Messages(.*)\.php$/', $filename ) ) {
			$code = \Language::getCodeFromFileName( basename( $filename ), 'Messages' );
			return new PHPReader( $code );
		}

		if ( preg_match( '/\.json/', $filename ) ) {
			$code = basename( $filename, '.json' );
			return new JSONReader( $code );
		}

		throw new \Exception( "Unknown file format: " . $filename );
	}
}
