<?php

namespace Flow\Api;

use ApiBase;

class ApiFlowModeratePost extends ApiFlowBasePost {

	public function __construct( $api, $modName ) {
		parent::__construct( $api, $modName, 'mp' );
	}

	protected function getBlockParams() {
		return [ 'topic' => $this->extractRequestParams() ];
	}

	protected function getAction() {
		return 'moderate-post';
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
			'postId' => [
				ApiBase::PARAM_REQUIRED => true,
			],
		] + parent::getAllowedParams();
	}

	/**
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			'action=flow&submodule=moderate-post&page=Topic:S2tycnas4hcucw8w' .
				'&mppostId=050f30e34c87beebcd54080027630f57&mpmoderationState=delete&mpreason=Ahhhh'
				=> 'apihelp-flow+moderate-post-example-1',
		];
	}
}
