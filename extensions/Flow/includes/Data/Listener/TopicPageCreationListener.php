<?php

namespace Flow\Data\Listener;

use Flow\Model\Workflow;
use Flow\OccupationController;
use SplQueue;
use WikiPage;

class TopicPageCreationListener extends AbstractListener {
	/** @var OccupationController */
	protected $occupationController;

	/** @var SplQueue */
	protected $deferredQueue;

	/**
	 * @param OccupationController $occupationController The OccupationController to create the page with.
	 * @param SplQueue $deferredQueue Queue of callbacks to run only if commit succeeds
	 */
	public function __construct(
		OccupationController $occupationController,
		SplQueue $deferredQueue
	) {
		$this->occupationController = $occupationController;
		$this->deferredQueue = $deferredQueue;
	}

	public function onAfterLoad( $object, array $old ) {
		// Nothing
	}

	public function onAfterInsert( $object, array $new, array $metadata ) {
		if ( !$object instanceof Workflow ) {
			return;
		}

		// make sure this Topic:xyz page exists
		$controller = $this->occupationController;
		$this->deferredQueue->push( function () use ( $controller, $object ) {
			$controller->ensureFlowRevision(
				WikiPage::factory( $object->getArticleTitle() ),
				$object
			);
		} );
	}
}
