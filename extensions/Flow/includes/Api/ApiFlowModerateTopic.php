<?php

namespace Flow\Api;

use ApiBase;

class ApiFlowModerateTopic extends ApiFlowBasePost {

	public function __construct( $api, $modName ) {
		parent::__construct( $api, $modName, 'mt' );
	}

	protected function getBlockParams() {
		return [ 'topic' => $this->extractRequestParams() ];
	}

	protected function getAction() {
		return 'moderate-topic';
	}

	public function getAllowedParams() {
		return [
			'moderationState' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => $this->getModerationStates(),
			],
			'reason' => [
				ApiBase::PARAM_REQUIRED => true,
			],
		] + parent::getAllowedParams();
	}

	/**
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			'action=flow&submodule=moderate-topic&page=Topic:S2tycnas4hcucw8w&mtmoderationState=delete&mtreason=Ahhhh'
				=> 'apihelp-flow+moderate-topic-example-1',
		];
	}
}
