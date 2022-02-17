<?php

namespace CirrusSearch\Api;

use CirrusSearch\BuildDocument\BuildDocument;
use CirrusSearch\CirrusSearch;
use Mediawiki\MediaWikiServices;
use WikiPage;

/**
 * Generate CirrusSearch document for page.
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
class QueryBuildDocument extends \ApiQueryBase {
	use ApiTrait;

	public function __construct( \ApiQuery $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'cb' );
	}

	public function execute() {
		$result = $this->getResult();
		$services = MediaWikiServices::getInstance();
		$engine = $services->getSearchEngineFactory()
			->create( 'cirrus' );

		if ( $engine instanceof CirrusSearch ) {
			$pages = [];
			foreach ( $this->getPageSet()->getGoodTitles() as $pageId => $title ) {
				$pages[] = new WikiPage( $title );
			}

			$builder = new BuildDocument(
				$this->getCirrusConnection(),
				$this->getDB(),
				$services->getParserCache(),
				$services->getRevisionStore()
			);
			$docs = $builder->initialize( $pages, BuildDocument::INDEX_EVERYTHING );
			foreach ( $docs as $pageId => $doc ) {
				if ( $builder->finalize( $doc ) ) {
					$result->addValue(
						[ 'query', 'pages', $pageId ],
						'cirrusbuilddoc', $doc->getData()
					);
				}
			}
		} else {
			throw new \RuntimeException( 'Could not create cirrus engine' );
		}
	}

	public function getAllowedParams() {
		return [];
	}

	/**
	 * @see ApiBase::getExamplesMessages
	 * @return array
	 */
	protected function getExamplesMessages() {
		return [
			'action=query&prop=cirrusbuilddoc&titles=Main_Page' =>
				'apihelp-query+cirrusbuilddoc-example'
		];
	}

}
