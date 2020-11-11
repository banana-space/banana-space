<?php
/**
 * ResourceLoader module for print styles.
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
 *
 * @file
 */

namespace Vector;

use CSSMin;
use MediaWiki\MediaWikiServices;
use ConfigException;
use ResourceLoaderContext;
use ResourceLoaderFileModule;

/**
 * ResourceLoader module for print styles.
 *
 * This class is also used when rendering styles for the MediaWiki installer.
 * Do not rely on any of the normal global state, services, etc., and make sure
 * to test the installer after making any changes here.
 */
class ResourceLoaderLessModule extends ResourceLoaderFileModule {
	/**
	 * Get language-specific LESS variables for this module.
	 *
	 * @param ResourceLoaderContext $context
	 * @return array
	 */
	protected function getLessVars( ResourceLoaderContext $context ) {
		$lessVars = parent::getLessVars( $context );
		try {
			$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'vector' );
			$printLogo = $config->get( 'VectorPrintLogo' );
		} catch ( ConfigException $e ) {
			// Config is not available when running in the context of the MediaWiki installer. (T183640)
			$printLogo = false;
		}
		if ( $printLogo ) {
			$lessVars[ 'printLogo' ] = true;
			$lessVars[ 'printLogoUrl' ] = CSSMin::buildUrlValue( $printLogo['url'] );
			$lessVars[ 'printLogoWidth' ] = intval( $printLogo['width'] );
			$lessVars[ 'printLogoHeight' ] = intval( $printLogo['height'] );
		} else {
			$lessVars[ 'printLogo' ] = false;
		}
		return $lessVars;
	}
}
