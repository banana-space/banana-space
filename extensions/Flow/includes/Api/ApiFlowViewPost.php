<?php

namespace Flow\Api;

use ApiBase;

class ApiFlowViewPost extends ApiFlowBaseGet {
	public function __construct( $api, $modName ) {
		parent::__construct( $api, $modName, 'vp' );
	}

	/**
	 * Taken from ext.flow.base.js
	 *
	 * @return array
	 */
	protected function getBlockParams() {
		return [ 'topic' => $this->extractRequestParams() ];
	}

	protected function getAction() {
		return 'view-post';
	}

	public function getAllowedParams() {
		return [
			'postId' => [
				ApiBase::PARAM_REQUIRED => true,
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
			'action=flow&submodule=view-post&page=Topic:S2tycnas4hcucw8w&vppostId=???&vpformat=wikitext'
				=> 'apihelp-flow+view-post-example-1',
		];
	}
}
