<?php

namespace Flow\Api;

use Flow\Model\Anchor;
use Flow\Model\UUID;
use Message;

abstract class ApiFlowBasePost extends ApiFlowBase {
	public function execute() {
		$loader = $this->getLoader();
		$blocks = $loader->getBlocks();
		/** @var \Flow\Model\Workflow $workflow */
		$workflow = $loader->getWorkflow();
		$action = $this->getAction();

		$result = $this->getResult();
		$params = $this->getBlockParams();
		$blocksToCommit = $loader->handleSubmit(
			$this->getContext(),
			$action,
			$params
		);

		// See if any of the blocks generated an error (in which case the
		// request will terminate with an the error message)
		$this->processError( $blocks );

		// If nothing is ready to be committed, we'll consider that an error (at least some
		// block should've been able to process the POST request)
		if ( !count( $blocksToCommit ) ) {
			$this->dieWithError( 'flow-error-no-commit', 'no-commit' );
		}

		$commitMetadata = $loader->commit( $blocksToCommit );
		$savedBlocks = [];
		$result->setIndexedTagName( $savedBlocks, 'block' );

		foreach ( $blocksToCommit as $block ) {
			$savedBlocks[] = $block->getName();
		}

		$output = [ $action => [
			'status' => 'ok',
			'workflow' => $workflow->isNew() ? '' : $workflow->getId()->getAlphadecimal(),
			'committed' => $commitMetadata,
		] ];

		// required until php5.4 which has the JsonSerializable interface
		array_walk_recursive( $output, function ( &$value ) {
			if ( $value instanceof Anchor ) {
				$value = $value->toArray();
			} elseif ( $value instanceof Message ) {
				$value = $value->text();
			} elseif ( $value instanceof UUID ) {
				$value = $value->getAlphadecimal();
			}
		} );

		$this->getResult()->addValue( null, $this->apiFlow->getModuleName(), $output );
	}

	/**
	 * @inheritDoc
	 */
	public function mustBePosted() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function isWriteMode() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function needsToken() {
		return 'csrf';
	}
}
