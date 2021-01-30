<?php

namespace Flow\Api;

use ApiBase;

class ApiFlowUndoEditHeader extends ApiFlowBaseGet {
	public function __construct( $api, $modName ) {
		parent::__construct( $api, $modName, 'ueh' );
	}

	/**
	 * Taken from ext.flow.base.js
	 * @return array
	 */
	protected function getBlockParams() {
		return [ 'header' => $this->extractRequestParams() ];
	}

	protected function getAction() {
		return 'undo-edit-header';
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
			'action=flow&submodule=undo-edit-header&page=Talk:Sandbox&uehstartId=???&uehendId=???'
				=> 'apihelp-flow+undo-edit-header-example-1',
		];
	}
}
