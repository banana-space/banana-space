<?php
/**
 * @file
 * @author Niklas Laxström
 * @license GPL-2.0-or-later
 */

namespace LocalisationUpdate\Fetcher;

/**
 * Interface for classes which fetch files over different protocols and ways.
 */
interface Fetcher {
	/**
	 * Fetches a single resource.
	 *
	 * @param string $url
	 * @return bool|string False on failure.
	 */
	public function fetchFile( $url );

	/**
	 * Fetch a list of resources. This has the benefit of being able to pick up
	 * new languages as they appear if languages are stored in separate files.
	 *
	 * @param string $pattern
	 * @return array
	 */
	public function fetchDirectory( $pattern );
}
