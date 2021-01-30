<?php

namespace Flow\Api;

use ApiBase;
use ApiMessage;
use Flow\Block\AbstractBlock;
use Flow\Container;
use Flow\Model\AbstractRevision;
use Flow\WorkflowLoader;
use Flow\WorkflowLoaderFactory;
use Status;
use Title;

abstract class ApiFlowBase extends ApiBase {

	/**
	 * @var WorkflowLoader
	 */
	protected $loader;

	/**
	 * @var Title
	 */
	protected $page;

	/**
	 * @var ApiFlow
	 */
	protected $apiFlow;

	/**
	 * @param ApiFlow $api
	 * @param string $modName
	 * @param string $prefix
	 */
	public function __construct( $api, $modName, $prefix = '' ) {
		$this->apiFlow = $api;
		parent::__construct( $api->getMain(), $modName, $prefix );
	}

	/**
	 * @return array
	 */
	abstract protected function getBlockParams();

	/**
	 * Returns true if the submodule required the page parameter to be set.
	 * Most submodules will need to be passed the page the API request is for.
	 * For some (e.g. search), this is not needed at all.
	 *
	 * @return bool
	 */
	public function needsPage() {
		return true;
	}

	/**
	 * @param Title $page
	 */
	public function setPage( Title $page ) {
		$this->page = $page;
	}

	/**
	 * Return the name of the flow action
	 * @return string
	 */
	abstract protected function getAction();

	/**
	 * @return WorkflowLoader
	 */
	protected function getLoader() {
		if ( $this->loader === null ) {
			/** @var WorkflowLoaderFactory $factory */
			$factory = Container::get( 'factory.loader.workflow' );
			$this->loader = $factory->createWorkflowLoader( $this->page );
		}

		return $this->loader;
	}

	/**
	 * @param bool $addAliases
	 * @return string[]
	 */
	protected function getModerationStates( $addAliases = true ) {
		$states = [
			AbstractRevision::MODERATED_NONE,
			AbstractRevision::MODERATED_DELETED,
			AbstractRevision::MODERATED_HIDDEN,
			AbstractRevision::MODERATED_SUPPRESSED,
		];

		if ( $addAliases ) {
			// aliases for AbstractRevision::MODERATED_NONE
			$states = array_merge( $states, [
				'restore', 'unhide', 'undelete', 'unsuppress',
			] );
		}

		return $states;
	}

	/**
	 * Kill the request if errors were encountered.
	 *
	 * @param AbstractBlock[] $blocks
	 */
	protected function processError( $blocks ) {
		$status = Status::newGood();
		foreach ( $blocks as $block ) {
			if ( $block->hasErrors() ) {
				foreach ( $block->getErrors() as $key ) {
					$status->fatal( ApiMessage::create(
						$block->getErrorMessage( $key ), $key, [ $key => $block->getErrorExtra( $key ) ]
					) );
				}
			}
		}
		if ( !$status->isGood() ) {
			$this->dieStatus( $status );
		}
	}

	/**
	 * Override prefix on CSRF token so the same code can be reused for
	 * all modules.  This is a *TEMPORARY* hack please remove as soon as
	 * unprefixed tokens are working correctly again (bug 70099).
	 *
	 * @param string $paramName
	 * @return string
	 */
	public function encodeParamName( $paramName ) {
		return $paramName === 'token'
			? $paramName
			: parent::encodeParamName( $paramName );
	}

	/**
	 * @inheritDoc
	 */
	public function getHelpUrls() {
		return [
			'https://www.mediawiki.org/wiki/Extension:Flow/API#' . $this->getAction(),
		];
	}

	/**
	 * @inheritDoc
	 */
	public function needsToken() {
		return 'csrf';
	}

	/**
	 * @inheritDoc
	 */
	public function getParent() {
		return $this->apiFlow;
	}

}
