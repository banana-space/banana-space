<?php
/**
 * Implements Special:GadgetUsage
 *
 * Copyright © 2015 Niharika Kohli
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
 * @ingroup SpecialPage
 * @author Niharika Kohli <niharika@wikimedia.org>
 */

use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IResultWrapper;

/**
 * Special:GadgetUsage - Lists all the gadgets on the wiki along with number of users.
 * @ingroup SpecialPage
 */
class SpecialGadgetUsage extends QueryPage {
	public function __construct( $name = 'GadgetUsage' ) {
		parent::__construct( $name );
		$this->limit = 1000; // Show all gadgets
		$this->shownavigation = false;
		$this->activeUsers = $this->getConfig()->get( 'SpecialGadgetUsageActiveUsers' );
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		parent::execute( $par );
		$this->addHelpLink( 'Extension:Gadgets' );
	}

	/**
	 * Flag for holding the value of config variable SpecialGadgetUsageActiveUsers
	 *
	 * @var bool $activeUsers
	 */
	public $activeUsers;

	public function isExpensive() {
		return true;
	}

	/**
	 * Define the database query that is used to generate the stats table.
	 * This uses 1 of 2 possible queries, depending on $wgSpecialGadgetUsageActiveUsers.
	 *
	 * The simple query is essentially:
	 * SELECT up_property, SUM(up_value)
	 * FROM user_properties
	 * WHERE up_property LIKE 'gadget-%'
	 * GROUP BY up_property;
	 *
	 * The more expensive query is:
	 * SELECT up_property, SUM(up_value), count(qcc_title)
	 * FROM user_properties
	 * LEFT JOIN user ON up_user = user_id
	 * LEFT JOIN querycachetwo ON user_name = qcc_title AND qcc_type = 'activeusers' AND up_value = 1
	 * WHERE up_property LIKE 'gadget-%'
	 * GROUP BY up_property;
	 * @return array
	 */
	public function getQueryInfo() {
		$dbr = wfGetDB( DB_REPLICA );
		if ( !$this->activeUsers ) {
			return [
				'tables' => [ 'user_properties' ],
				'fields' => [
					'title' => 'up_property',
					'value' => 'SUM( up_value )',
					'namespace' => NS_GADGET
				],
				'conds' => [
					'up_property' . $dbr->buildLike( 'gadget-', $dbr->anyString() )
				],
				'options' => [
					'GROUP BY' => [ 'up_property' ]
				]
			];
		} else {
			return [
				'tables' => [ 'user_properties', 'user', 'querycachetwo' ],
				'fields' => [
					'title' => 'up_property',
					'value' => 'SUM( up_value )',
					// Need to pick fields existing in the querycache table so that the results are cachable
					'namespace' => 'COUNT( qcc_title )'
				],
				'conds' => [
					'up_property' . $dbr->buildLike( 'gadget-', $dbr->anyString() )
				],
				'options' => [
					'GROUP BY' => [ 'up_property' ]
				],
				'join_conds' => [
					'user' => [
						'LEFT JOIN', [
							'up_user = user_id'
						]
					],
					'querycachetwo' => [
						'LEFT JOIN', [
							'user_name = qcc_title',
							'qcc_type = "activeusers"',
							'up_value = 1'
						]
					]
				]
			];
		}
	}

	public function getOrderFields() {
		return [ 'value' ];
	}

	/**
	 * Output the start of the table
	 * Including opening <table>, the thead element with column headers
	 * and the opening <tbody>.
	 */
	protected function outputTableStart() {
		$html = Html::openElement( 'table', [ 'class' => [ 'sortable', 'wikitable' ] ] );
		$html .= Html::openElement( 'thead', [] );
		$html .= Html::openElement( 'tr', [] );
		$headers = [ 'gadgetusage-gadget', 'gadgetusage-usercount' ];
		if ( $this->activeUsers ) {
			$headers[] = 'gadgetusage-activeusers';
		}
		foreach ( $headers as $h ) {
			if ( $h == 'gadgetusage-gadget' ) {
				$html .= Html::element( 'th', [], $this->msg( $h )->text() );
			} else {
				$html .= Html::element( 'th', [ 'data-sort-type' => 'number' ],
					$this->msg( $h )->text() );
			}
		}
		$html .= Html::closeElement( 'tr' );
		$html .= Html::closeElement( 'thead' );
		$html .= Html::openElement( 'tbody', [] );
		$this->getOutput()->addHTML( $html );
		$this->getOutput()->addModuleStyles( 'jquery.tablesorter.styles' );
		$this->getOutput()->addModules( 'jquery.tablesorter' );
	}

	/**
	 * Output the end of the table
	 * </tbody></table>
	 */
	protected function outputTableEnd() {
		$this->getOutput()->addHTML(
			Html::closeElement( 'tbody' ) .
			Html::closeElement( 'table' )
		);
	}

	/**
	 * @param Skin $skin
	 * @param object $result Result row
	 * @return string|bool String of HTML
	 */
	public function formatResult( $skin, $result ) {
		$gadgetTitle = substr( $result->title, 7 );
		$gadgetUserCount = $this->getLanguage()->formatNum( $result->value );
		if ( $gadgetTitle ) {
			$html = Html::openElement( 'tr', [] );
			$html .= Html::element( 'td', [], $gadgetTitle );
			$html .= Html::element( 'td', [], $gadgetUserCount );
			if ( $this->activeUsers == true ) {
				$activeUserCount = $this->getLanguage()->formatNum( $result->namespace );
				$html .= Html::element( 'td', [], $activeUserCount );
			}
			$html .= Html::closeElement( 'tr' );
			return $html;
		}
		return false;
	}

	/**
	 * Get a list of default gadgets
	 * @param GadgetRepo $gadgetRepo
	 * @param array $gadgetIds list of gagdet ids registered in the wiki
	 * @return array
	 */
	protected function getDefaultGadgets( $gadgetRepo, $gadgetIds ) {
		$gadgetsList = [];
		foreach ( $gadgetIds as $g ) {
			$gadget = $gadgetRepo->getGadget( $g );
			if ( $gadget->isOnByDefault() ) {
				$gadgetsList[] = $gadget->getName();
			}
		}
		asort( $gadgetsList, SORT_STRING | SORT_FLAG_CASE );
		return $gadgetsList;
	}

	/**
	 * Format and output report results using the given information plus
	 * OutputPage
	 *
	 * @param OutputPage $out OutputPage to print to
	 * @param Skin $skin User skin to use
	 * @param IDatabase $dbr Database (read) connection to use
	 * @param IResultWrapper $res Result pointer
	 * @param int $num Number of available result rows
	 * @param int $offset Paging offset
	 */
	protected function outputResults( $out, $skin, $dbr, $res, $num, $offset ) {
		$gadgetRepo = GadgetRepo::singleton();
		$gadgetIds = $gadgetRepo->getGadgetIds();
		$defaultGadgets = $this->getDefaultGadgets( $gadgetRepo, $gadgetIds );
		if ( $this->activeUsers ) {
			$out->addHtml(
				$this->msg( 'gadgetusage-intro' )
					->numParams( $this->getConfig()->get( 'ActiveUserDays' ) )->parseAsBlock()
			);
		} else {
			$out->addHtml(
				$this->msg( 'gadgetusage-intro-noactive' )->parseAsBlock()
			);
		}
		if ( $num > 0 ) {
			$this->outputTableStart();
			// Append default gadgets to the table with 'default' in the total and active user fields
			foreach ( $defaultGadgets as $default ) {
				$html = Html::openElement( 'tr', [] );
				$html .= Html::element( 'td', [], $default );
				$html .= Html::element( 'td', [ 'data-sort-value' => 'Infinity' ],
					$this->msg( 'gadgetusage-default' )->text() );
				if ( $this->activeUsers ) {
					$html .= Html::element( 'td', [ 'data-sort-value' => 'Infinity' ],
						$this->msg( 'gadgetusage-default' )->text() );
				}
				$html .= Html::closeElement( 'tr' );
				$out->addHTML( $html );
			}
			foreach ( $res as $row ) {
				// Remove the 'gadget-' part of the result string and compare if it's present
				// in $defaultGadgets, if not we format it and add it to the output
				if ( !in_array( substr( $row->title, 7 ), $defaultGadgets ) ) {
					// Only pick gadgets which are in the list $gadgetIds to make sure they exist
					if ( in_array( substr( $row->title, 7 ), $gadgetIds ) ) {
						$line = $this->formatResult( $skin, $row );
						if ( $line ) {
							$out->addHTML( $line );
						}
					}
				}
			}
			// Close table element
			$this->outputTableEnd();
		} else {
			$out->addHtml(
				$this->msg( 'gadgetusage-noresults' )->parseAsBlock()
			);
		}
	}

	protected function getGroupName() {
		return 'wiki';
	}
}
