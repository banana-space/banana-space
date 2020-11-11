<?php
/**
 * Created on 15 April 2011
 * API for Gadgets extension
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

class ApiQueryGadgets extends ApiQueryBase {
	/**
	 * @var array
	 */
	private $props;

	/**
	 * @var array|bool
	 */
	private $categories;

	/**
	 * @var array|bool
	 */
	private $neededIds;

	/**
	 * @var bool
	 */
	private $listAllowed;

	/**
	 * @var bool
	 */
	private $listEnabled;

	public function __construct( ApiQuery $queryModule, $moduleName ) {
		parent::__construct( $queryModule, $moduleName, 'ga' );
	}

	public function execute() {
		$params = $this->extractRequestParams();
		$this->props = array_flip( $params['prop'] );
		$this->categories = isset( $params['categories'] )
			? array_flip( $params['categories'] )
			: false;
		$this->neededIds = isset( $params['ids'] )
			? array_flip( $params['ids'] )
			: false;
		$this->listAllowed = isset( $params['allowedonly'] ) && $params['allowedonly'];
		$this->listEnabled = isset( $params['enabledonly'] ) && $params['enabledonly'];

		$this->getMain()->setCacheMode( $this->listAllowed || $this->listEnabled
			? 'anon-public-user-private' : 'public' );

		$this->applyList( $this->getList() );
	}

	/**
	 * @return array
	 */
	private function getList() {
		$gadgets = GadgetRepo::singleton()->getStructuredList();

		if ( $gadgets === false ) {
			return [];
		}

		$result = [];
		foreach ( $gadgets as $category => $list ) {
			if ( $this->categories && !isset( $this->categories[$category] ) ) {
				continue;
			}

			foreach ( $list as $g ) {
				if ( $this->isNeeded( $g ) ) {
					$result[] = $g;
				}
			}
		}
		return $result;
	}

	/**
	 * @param array $gadgets
	 */
	private function applyList( $gadgets ) {
		$data = [];
		$result = $this->getResult();

		/**
		 * @var $g Gadget
		 */
		foreach ( $gadgets as $g ) {
			$row = [];
			if ( isset( $this->props['id'] ) ) {
				$row['id'] = $g->getName();
			}

			if ( isset( $this->props['metadata'] ) ) {
				$row['metadata'] = $this->fakeMetadata( $g );
				$this->setIndexedTagNameForMetadata( $row['metadata'] );
			}

			if ( isset( $this->props['desc'] ) ) {
				$row['desc'] = $g->getDescription();
			}

			$data[] = $row;
		}

		$result->setIndexedTagName( $data, 'gadget' );
		$result->addValue( 'query', $this->getModuleName(), $data );
	}

	/**
	 * @param Gadget $gadget
	 *
	 * @return bool
	 */
	private function isNeeded( Gadget $gadget ) {
		$user = $this->getUser();

		return ( $this->neededIds === false || isset( $this->neededIds[$gadget->getName()] ) )
			&& ( !$this->listAllowed || $gadget->isAllowed( $user ) )
			&& ( !$this->listEnabled || $gadget->isEnabled( $user ) );
	}

	/**
	 * @param Gadget $g
	 * @return array
	 */
	private function fakeMetadata( Gadget $g ) {
		return [
			'settings' => [
				'rights' => $g->getRequiredRights(),
				'skins' => $g->getRequiredSkins(),
				'default' => $g->isOnByDefault(),
				'hidden' => $g->isHidden(),
				'shared' => false,
				'category' => $g->getCategory(),
				'legacyscripts' => (bool)$g->getLegacyScripts(),
			],
			'module' => [
				'scripts' => $g->getScripts(),
				'styles' => $g->getStyles(),
				'dependencies' => $g->getDependencies(),
				'peers' => $g->getPeers(),
				'messages' => $g->getMessages(),
			]
		];
	}

	private function setIndexedTagNameForMetadata( &$metadata ) {
		static $tagNames = [
			'rights' => 'right',
			'skins' => 'skin',
			'scripts' => 'script',
			'styles' => 'style',
			'dependencies' => 'dependency',
			'peers' => 'peer',
			'messages' => 'message',
		];

		$result = $this->getResult();
		foreach ( $metadata as $data ) {
			foreach ( $data as $key => $value ) {
				if ( is_array( $value ) ) {
					$tag = isset( $tagNames[$key] ) ? $tagNames[$key] : $key;
					$result->setIndexedTagName( $value, $tag );
				}
			}
		}
	}

	public function getAllowedParams() {
		return [
			'prop' => [
				ApiBase::PARAM_DFLT => 'id|metadata',
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_TYPE => [
					'id',
					'metadata',
					'desc',
				],
			],
			'categories' => [
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_TYPE => 'string',
			],
			'ids' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_ISMULTI => true,
			],
			'allowedonly' => false,
			'enabledonly' => false,
		];
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 * @return array
	 */
	protected function getExamplesMessages() {
		$params = $this->getAllowedParams();
		$allProps = implode( '|', $params['prop'][ApiBase::PARAM_TYPE] );
		return [
			'action=query&list=gadgets&gaprop=id|desc'
				=> 'apihelp-query+gadgets-example-1',
			"action=query&list=gadgets&gaprop=$allProps"
				=> 'apihelp-query+gadgets-example-2',
			'action=query&list=gadgets&gacategories=foo'
				=> 'apihelp-query+gadgets-example-3',
			'action=query&list=gadgets&gaids=foo|bar&gaprop=id|desc|metadata'
				=> 'apihelp-query+gadgets-example-4',
			'action=query&list=gadgets&gaenabledonly'
				=> 'apihelp-query+gadgets-example-5',
		];
	}
}
