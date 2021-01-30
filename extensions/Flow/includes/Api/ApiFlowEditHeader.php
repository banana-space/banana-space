<?php

namespace Flow\Api;

use ApiBase;

class ApiFlowEditHeader extends ApiFlowBasePost {

	public function __construct( $api, $modName ) {
		parent::__construct( $api, $modName, 'eh' );
	}

	/**
	 * Taken from ext.flow.base.js
	 * @return array
	 */
	protected function getBlockParams() {
		return [ 'header' => $this->extractRequestParams() ];
	}

	protected function getAction() {
		return 'edit-header';
	}

	public function getAllowedParams() {
		return [
			'prev_revision' => [
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
			'action=flow&submodule=edit-header&page=Talk:Sandbox&ehprev_revision=???&ehcontent=Nice%20to&20meet%20you&ehformat=wikitext'
				=> 'apihelp-flow+edit-header-example-1',
		];
	}
}
