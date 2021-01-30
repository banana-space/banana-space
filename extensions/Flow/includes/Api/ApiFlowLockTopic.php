<?php

namespace Flow\Api;

use ApiBase;
use Flow\Model\AbstractRevision;

class ApiFlowLockTopic extends ApiFlowBasePost {

	public function __construct( $api, $modName ) {
		parent::__construct( $api, $modName, 'cot' );
	}

	protected function getBlockParams() {
		$params = $this->extractRequestParams();
		return [
			'topic' => $params,
		];
	}

	protected function getAction() {
		return 'lock-topic';
	}

	public function isDeprecated() {
		return $this->getModuleName() === 'close-open-topic';
	}

	public function getAllowedParams() {
		return [
			'moderationState' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => [
					AbstractRevision::MODERATED_LOCKED, 'unlock',
					'close', 'reopen' // BC: now replaced by lock & unlock
				],
			],
			'reason' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => 'string',
			],
		] + parent::getAllowedParams();
	}

	/**
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			'action=flow&submodule=lock-topic&page=Topic:S2tycnas4hcucw8w&cotmoderationState=lock&cotreason=Ahhhh'
				=> 'apihelp-flow+lock-topic-example-1',
		];
	}
}
