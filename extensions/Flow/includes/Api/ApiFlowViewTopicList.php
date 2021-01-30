<?php

namespace Flow\Api;

use ApiBase;

class ApiFlowViewTopicList extends ApiFlowBaseGet {
	public function __construct( $api, $modName ) {
		parent::__construct( $api, $modName, 'vtl' );
	}

	/**
	 * Taken from ext.flow.base.js
	 *
	 * @return array
	 */
	protected function getBlockParams() {
		return [ 'topiclist' => $this->extractRequestParams() ];
	}

	protected function getAction() {
		return 'view-topiclist';
	}

	public function getAllowedParams() {
		global $wgFlowDefaultLimit, $wgFlowMaxLimit;

		return [
			'offset-dir' => [
				ApiBase::PARAM_TYPE => [ 'fwd', 'rev' ],
				ApiBase::PARAM_DFLT => 'fwd',
			],
			'sortby' => [
				ApiBase::PARAM_TYPE => [ 'newest', 'updated', 'user' ],
				ApiBase::PARAM_DFLT => 'user',
			],
			'savesortby' => [
				ApiBase::PARAM_TYPE => 'boolean',
				ApiBase::PARAM_DFLT => false,
			],
			'offset-id' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => false,
			],
			'offset' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => false,
			],
			'include-offset' => [
				ApiBase::PARAM_TYPE => 'boolean',
				ApiBase::PARAM_DFLT => false,
			],
			'limit' => [
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_DFLT => $wgFlowDefaultLimit,
				ApiBase::PARAM_MAX => $wgFlowMaxLimit,
				ApiBase::PARAM_MAX2 => $wgFlowMaxLimit,
			],
			'toconly' => [
				ApiBase::PARAM_TYPE => 'boolean',
				ApiBase::PARAM_DFLT => false,
			],
			'format' => [
				ApiBase::PARAM_TYPE => [ 'html', 'wikitext', 'fixed-html' ],
				ApiBase::PARAM_DFLT => 'fixed-html',
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			'action=flow&submodule=view-topiclist&page=Talk:Sandbox'
				=> 'apihelp-flow+view-topiclist-example-1',
		];
	}
}
