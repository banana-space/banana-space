<?php

namespace Flow\Api;

use ApiBase;

class ApiFlowViewHeader extends ApiFlowBaseGet {
	public function __construct( $api, $modName ) {
		parent::__construct( $api, $modName, 'vh' );
	}

	/**
	 * Taken from ext.flow.base.js
	 * @return array
	 */
	protected function getBlockParams() {
		return [ 'header' => $this->extractRequestParams() ];
	}

	protected function getAction() {
		return 'view-header';
	}

	public function getAllowedParams() {
		return [
			'format' => [
				ApiBase::PARAM_TYPE => [ 'html', 'wikitext', 'fixed-html' ],
				ApiBase::PARAM_DFLT => 'fixed-html',
			],
			'revId' => null,
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			'action=flow&submodule=view-header&page=Talk:Sandbox&vhformat=wikitext&revId='
				=> 'apihelp-flow+view-header-example-1',
		];
	}
}
