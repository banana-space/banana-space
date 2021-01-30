<?php

namespace Flow\Api;

use ApiBase;

class ApiFlowNewTopic extends ApiFlowBasePost {

	public function __construct( $api, $modName ) {
		parent::__construct( $api, $modName, 'nt' );
	}

	/**
	 * Taken from ext.flow.base.js
	 * @return array
	 */
	protected function getBlockParams() {
		return [ 'topiclist' => $this->extractRequestParams() ];
	}

	protected function getAction() {
		return 'new-topic';
	}

	public function getAllowedParams() {
		return [
			'topic' => [
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
			'action=flow&submodule=new-topic&page=Talk:Sandbox&nttopic=Hi&ntcontent=Nice%20to&20meet%20you&ntformat=wikitext'
				=> 'apihelp-flow+new-topic-example-1',
		];
	}
}
