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
 */

class ApiCategoryTree extends ApiBase {
	public function execute() {
		$params = $this->extractRequestParams();
		$options = [];
		if ( isset( $params['options'] ) ) {
			$options = FormatJson::decode( $params['options'] );
			if ( !is_object( $options ) ) {
				if ( is_callable( [ $this, 'dieWithError' ] ) ) {
					$this->dieWithError( 'apierror-categorytree-invalidjson', 'invalidjson' );
				} else {
					$this->dieUsage( 'Options must be valid a JSON object', 'invalidjson' );
				}
				return;
			}
			$options = get_object_vars( $options );
		}
		$depth = isset( $options['depth'] ) ? (int)$options['depth'] : 1;

		$ct = new CategoryTree( $options );
		$depth = CategoryTree::capDepth( $ct->getOption( 'mode' ), $depth );
		$title = CategoryTree::makeTitle( $params['category'] );
		$config = $this->getConfig();
		$ctConfig = ConfigFactory::getDefaultInstance()->makeConfig( 'categorytree' );
		$html = $this->getHTML( $ct, $title, $depth, $ctConfig );

		if (
			$ctConfig->get( 'CategoryTreeHTTPCache' ) &&
			$config->get( 'SquidMaxage' ) &&
			$config->get( 'UseSquid' )
		) {
			if ( $config->get( 'UseESI' ) ) {
				$this->getRequest()->response()->header(
					'Surrogate-Control: max-age=' . $config->get( 'SquidMaxage' ) . ', content="ESI/1.0"'
				);
				$this->getMain()->setCacheMaxAge( 0 );
			} else {
				$this->getMain()->setCacheMaxAge( $config->get( 'SquidMaxage' ) );
			}
			// cache for anons only
			$this->getRequest()->response()->header( 'Vary: Accept-Encoding, Cookie' );
			// TODO: purge the squid cache when a category page is invalidated
		}

		$this->getResult()->addContentValue( $this->getModuleName(), 'html', $html );
	}

	/**
	 * @param string $condition
	 *
	 * @return bool|null|string
	 */
	public function getConditionalRequestData( $condition ) {
		if ( $condition === 'last-modified' ) {
			$params = $this->extractRequestParams();
			$title = CategoryTree::makeTitle( $params['category'] );
			return wfGetDB( DB_REPLICA )->selectField( 'page', 'page_touched',
				[
					'page_namespace' => NS_CATEGORY,
					'page_title' => $title->getDBkey(),
				],
				__METHOD__
			);
		}
	}

	/**
	 * Get category tree HTML for the given tree, title, depth and config
	 *
	 * @param CategoryTree $ct
	 * @param Title $title
	 * @param int $depth
	 * @param Config $ctConfig Config for CategoryTree
	 * @return string HTML
	 */
	private function getHTML( $ct, $title, $depth, $ctConfig ) {
		global $wgContLang, $wgMemc;

		$mckey = wfMemcKey(
			'ajax-categorytree',
			md5( $title->getDBkey() ),
			md5( $ct->getOptionsAsCacheKey( $depth ) ),
			$this->getLanguage()->getCode(),
			$wgContLang->getExtraHashOptions(),
			$ctConfig->get( 'RenderHashAppend' )
		);

		$touched = $this->getConditionalRequestData( 'last-modified' );
		if ( $touched ) {
			$mcvalue = $wgMemc->get( $mckey );
			if ( $mcvalue && $touched <= $mcvalue['timestamp'] ) {
				$html = $mcvalue['value'];
			}
		}

		if ( !isset( $html ) ) {
			$html = $ct->renderChildren( $title, $depth );

			$wgMemc->set(
				$mckey,
				[
					'timestamp' => wfTimestampNow(),
					'value' => $html
				],
				86400
			);
		}
		return trim( $html );
	}

	public function getAllowedParams() {
		return [
			'category' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			],
			'options' => [
				ApiBase::PARAM_TYPE => 'string',
			],
		];
	}

	public function isInternal() {
		return true;
	}
}
