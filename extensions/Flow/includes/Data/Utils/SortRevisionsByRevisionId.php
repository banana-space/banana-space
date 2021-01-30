<?php

namespace Flow\Data\Utils;

use Flow\Exception\InvalidParameterException;
use Flow\Model\AbstractRevision;

/**
 * Sorts AbstractRevision objects by revision ID
 */
class SortRevisionsByRevisionId {
	/**
	 * Order, either ASC or DESC.
	 *
	 * @var string
	 */
	protected $order;

	/**
	 * @param string $order ASC or DESC
	 * @throws InvalidParameterException
	 */
	public function __construct( $order ) {
		if ( $order !== 'ASC' && $order !== 'DESC' ) {
			throw new InvalidParameterException( "Must specify ASC or DESC" );
		}

		$this->order = $order;
	}

	/**
	 * Compares two revisions
	 *
	 * @param AbstractRevision $a
	 * @param AbstractRevision $b
	 * @return int
	 */
	public function __invoke( AbstractRevision $a, AbstractRevision $b ) {
		$aId = $a->getRevisionId()->getAlphadecimal();
		$bId = $b->getRevisionId()->getAlphadecimal();

		if ( $aId < $bId ) {
			$result = -1;
		} elseif ( $aId > $bId ) {
			$result = 1;
		} else {
			$result = 0;
		}

		if ( $this->order === 'ASC' ) {
			return $result;
		} else {
			return -$result;
		}
	}
}
