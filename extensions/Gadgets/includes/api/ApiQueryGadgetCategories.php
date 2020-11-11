<?php
/**
 * Created on 16 April 2011
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

class ApiQueryGadgetCategories extends ApiQueryBase {
	/**
	 * @var array
	 */
	private $props;

	/**
	 * @var array|bool
	 */
	private $neededNames;

	public function __construct( ApiQuery $queryModule, $moduleName ) {
		parent::__construct( $queryModule, $moduleName, 'gc' );
	}

	public function execute() {
		$params = $this->extractRequestParams();
		$this->props = array_flip( $params['prop'] );
		$this->neededNames = isset( $params['names'] )
			? array_flip( $params['names'] )
			: false;

		$this->getMain()->setCacheMode( 'public' );

		$this->getList();
	}

	private function getList() {
		$data = [];
		$result = $this->getResult();
		$gadgets = GadgetRepo::singleton()->getStructuredList();

		if ( $gadgets ) {
			foreach ( $gadgets as $category => $list ) {
				if ( !$this->neededNames || isset( $this->neededNames[$category] ) ) {
					$row = [];
					if ( isset( $this->props['name'] ) ) {
						$row['name'] = $category;
					}

					if ( $category !== "" ) {
						if ( isset( $this->props['title'] ) ) {
							$row['desc'] = $this->msg( "gadget-section-$category" )->parse();
						}
					}

					if ( isset( $this->props['members'] ) ) {
						$row['members'] = count( $list );
					}

					$data[] = $row;
				}
			}
		}
		$result->setIndexedTagName( $data, 'category' );
		$result->addValue( 'query', $this->getModuleName(), $data );
	}

	public function getAllowedParams() {
		return [
			'prop' => [
				ApiBase::PARAM_DFLT => 'name',
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_TYPE => [
					'name',
					'title',
					'members',
				],
			],
			'names' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_ISMULTI => true,
			],
		];
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 * @return array
	 */
	protected function getExamplesMessages() {
		return [
			'action=query&list=gadgetcategories'
				=> 'apihelp-query+gadgetcategories-example-1',
			'action=query&list=gadgetcategories&gcnames=foo|bar&gcprop=name|title|members'
				=> 'apihelp-query+gadgetcategories-example-2',
		];
	}
}
