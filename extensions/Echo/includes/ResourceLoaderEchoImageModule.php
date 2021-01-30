<?php
/**
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

/**
 * A sibling of secret special sauce.
 * @see ResourceLoaderOOUIImageModule for familial resemblence
 */
class ResourceLoaderEchoImageModule extends ResourceLoaderImageModule {
	protected function loadFromDefinition() {
		if ( $this->definition === null ) {
			return;
		}

		// Check to make sure icons are set
		if ( !isset( $this->definition['icons'] ) ) {
			throw new MWException( 'Icons must be set.' );
		}

		$images = [];
		foreach ( $this->definition['icons'] as $iconName => $definition ) {
			// FIXME: We also have a 'site' icon which is "magical"
			// and uses witchcraft and should be handled specifically
			if ( isset( $definition[ 'path' ] ) ) {
				if ( is_array( $definition[ 'path' ] ) ) {
					$paths = [];
					foreach ( $definition[ 'path' ] as $dir => $p ) {
						// Has both rtl and ltr definitions
						$paths[ $dir ] = $p;
					}
				} else {
					$paths = $definition[ 'path' ];
				}

				if ( !empty( $paths ) ) {
					$images[ $iconName ][ 'file' ] = $paths;
				}
			}
		}

		$this->definition[ 'images' ] = $images;
		$this->definition[ 'selector' ] = '.oo-ui-icon-{name}';

		parent::loadFromDefinition();
	}
}
