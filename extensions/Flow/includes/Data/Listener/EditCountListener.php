<?php

namespace Flow\Data\Listener;

use Flow\Exception\InvalidDataException;
use Flow\FlowActions;
use Flow\Model\AbstractRevision;

class EditCountListener extends AbstractListener {
	/**
	 * @var FlowActions
	 */
	protected $actions;

	public function __construct( FlowActions $actions ) {
		$this->actions = $actions;
	}

	public function onAfterInsert( $revision, array $new, array $metadata ) {
		if ( !$revision instanceof AbstractRevision ) {
			throw new InvalidDataException( 'EditCountListener can only attach to AbstractRevision storage' );
		}

		$action = $revision->getChangeType();
		$increase = $this->actions->getValue( $action, 'editcount' );

		if ( $increase ) {
			$revision->getUser()->incEditCount();
		}
	}
}
