<?php

namespace Flow\Api;

use ApiBase;

class ApiFlowUndoEditPost extends ApiFlowBaseGet {
	public function __construct( $api, $modName ) {
		parent::__construct( $api, $modName, 'uep' );
	}

	protected function getAction() {
		return 'undo-edit-post';
	}

	protected function getBlockParams() {
		return [ 'topic' => $this->extractRequestParams() ];
	}

	public function getAllowedParams() {
		return [
			'startId' => [
				ApiBase::PARAM_REQUIRED => true,
			],
			'endId' => [
				ApiBase::PARAM_REQUIRED => true,
			],
		] + parent::getAllowedParams();
	}

	/**
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			'action=flow&submodule=undo-edit-post&page=Topic:S2tycnas4hcucw8w&uaepostId=???&uaestartId=???&uaeendId=???'
				=> 'apihelp-flow+undo-edit-post-example-1',
		];
	}
}
