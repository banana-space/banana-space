<?php

namespace Flow\Api;

use ApiBase;

class ApiFlowEditTitle extends ApiFlowBasePost {

	public function __construct( $api, $modName ) {
		parent::__construct( $api, $modName, 'et' );
	}

	/**
	 * Taken from ext.flow.base.js
	 * @return array
	 */
	protected function getBlockParams() {
		return [ 'topic' => $this->extractRequestParams() ];
	}

	protected function getAction() {
		return 'edit-title';
	}

	public function getAllowedParams() {
		return [
			'prev_revision' => [
				ApiBase::PARAM_REQUIRED => true,
			],
			'content' => [
				ApiBase::PARAM_REQUIRED => true,
			],
		] + parent::getAllowedParams();
	}

	/**
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			'action=flow&submodule=edit-title&page=Topic:S2tycnas4hcucw8w&etprev_revision=???&ehtcontent=Nice%20to&20meet%20you'
				=> 'apihelp-flow+edit-title-example-1',
		];
	}
}
