<?php

namespace Flow\Api;

use ApiBase;

class ApiFlowReply extends ApiFlowBasePost {

	public function __construct( $api, $modName ) {
		parent::__construct( $api, $modName, 'rep' );
	}

	/**
	 * @return array
	 */
	protected function getBlockParams() {
		return [ 'topic' => $this->extractRequestParams() ];
	}

	protected function getAction() {
		return 'reply';
	}

	public function getAllowedParams() {
		return [
			'replyTo' => [
				ApiBase::PARAM_REQUIRED => true,
			],
			'content' => [
				ApiBase::PARAM_REQUIRED => true,
			],
			'format' => [
				ApiBase::PARAM_DFLT => 'wikitext',
				ApiBase::PARAM_TYPE => [ 'html', 'wikitext' ],
			],
		] + parent::getAllowedParams();
	}

	/**
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			'action=flow&submodule=reply&page=Topic:S2tycnas4hcucw8w' .
				'&repreplyTo=050e554490c2b269143b080027630f57&repcontent=Nice%20to&20meet%20you' .
				'&repformat=wikitext' => 'apihelp-flow+reply-example-1',
		];
	}
}
