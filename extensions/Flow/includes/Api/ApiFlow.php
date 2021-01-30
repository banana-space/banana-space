<?php

namespace Flow\Api;

use ApiBase;
use ApiModuleManager;
use Flow\Container;
use Hooks;
use MediaWiki\MediaWikiServices;
use Title;

class ApiFlow extends ApiBase {

	/**
	 * @var ApiModuleManager
	 */
	private $moduleManager;

	private static $alwaysEnabledModules = [
		// POST
		'new-topic' => \Flow\Api\ApiFlowNewTopic::class,
		'edit-header' => \Flow\Api\ApiFlowEditHeader::class,
		'edit-post' => \Flow\Api\ApiFlowEditPost::class,
		'edit-topic-summary' => \Flow\Api\ApiFlowEditTopicSummary::class,
		'reply' => \Flow\Api\ApiFlowReply::class,
		'moderate-post' => \Flow\Api\ApiFlowModeratePost::class,
		'moderate-topic' => \Flow\Api\ApiFlowModerateTopic::class,
		'edit-title' => \Flow\Api\ApiFlowEditTitle::class,
		'lock-topic' => \Flow\Api\ApiFlowLockTopic::class,
		'close-open-topic' => \Flow\Api\ApiFlowLockTopic::class, // BC: has been renamed to lock-topic
		'undo-edit-header' => \Flow\Api\ApiFlowUndoEditHeader::class,
		'undo-edit-post' => \Flow\Api\ApiFlowUndoEditPost::class,
		'undo-edit-topic-summary' => \Flow\Api\ApiFlowUndoEditTopicSummary::class,

		// GET
		// action 'view' exists in Topic.php & TopicList.php, for topic, post &
		// topiclist - we'll want to know topic-/post- or topiclist-view ;)
		'view-topiclist' => \Flow\Api\ApiFlowViewTopicList::class,
		'view-post' => \Flow\Api\ApiFlowViewPost::class,
		'view-post-history' => \Flow\Api\ApiFlowViewPostHistory::class,
		'view-topic' => \Flow\Api\ApiFlowViewTopic::class,
		'view-topic-history' => \Flow\Api\ApiFlowViewTopicHistory::class,
		'view-header' => \Flow\Api\ApiFlowViewHeader::class,
		'view-topic-summary' => \Flow\Api\ApiFlowViewTopicSummary::class,
	];

	private static $searchModules = [
		'search' => \Flow\Api\ApiFlowSearch::class,
	];

	public function __construct( $main, $action ) {
		global $wgFlowSearchEnabled;

		parent::__construct( $main, $action );
		$this->moduleManager = new ApiModuleManager(
			$this,
			MediaWikiServices::getInstance()->getObjectFactory()
		);

		$enabledModules = self::$alwaysEnabledModules;
		if ( $wgFlowSearchEnabled ) {
			$enabledModules += self::$searchModules;
		}

		$this->moduleManager->addModules( $enabledModules, 'submodule' );
	}

	public function getModuleManager() {
		return $this->moduleManager;
	}

	/**
	 * @suppress PhanUndeclaredMethod Phan doesn't infer $module is ApiFlowBase
	 */
	public function execute() {
		// To avoid API warning, register the parameter used to bust browser cache
		$this->getMain()->getVal( '_' );

		$params = $this->extractRequestParams();
		/** @var $module ApiFlowBase */
		$module = $this->moduleManager->getModule( $params['submodule'], 'submodule' );

		// The checks for POST and tokens are the same as ApiMain.php
		$wasPosted = $this->getRequest()->wasPosted();
		if ( !$wasPosted && $module->mustBePosted() ) {
			$this->dieWithErrorOrDebug( [ 'apierror-mustbeposted', $params['submodule'] ] );
		}

		if ( $module->needsToken() ) {
			if ( !isset( $params['token'] ) ) {
				$this->dieWithError( [ 'apierror-missingparam', 'token' ] );
			}

			$module->requirePostedParameters( [ 'token' ] );

			if ( !$module->validateToken( $params['token'], $params ) ) {
				$this->dieWithError( 'apierror-badtoken' );
			}
		}

		$module->extractRequestParams();
		if ( $module->needsPage() ) {
			$module->setPage( $this->getPage( $params ) );
		}
		$module->execute();
		Hooks::run( 'APIFlowAfterExecute', [ $module ] );
	}

	/**
	 * @param array $params
	 * @return Title
	 */
	protected function getPage( $params ) {
		$page = Title::newFromText( $params['page'] );
		if ( !$page ) {
			$this->dieWithError(
				[ 'apierror-invalidtitle', wfEscapeWikiText( $params['page'] ) ], 'invalid-page'
			);
		}

		if ( $page->getNamespace() < 0 ) {
			$this->dieWithError(
				[ 'apierror-invalidtitle', wfEscapeWikiText( $params['page'] ) ], 'invalid-page-negative-namespace'
			);
		}

		/** @var \Flow\TalkpageManager $controller */
		$controller = Container::get( 'occupation_controller' );
		if ( $page->getContentModel() !== CONTENT_MODEL_FLOW_BOARD ) {
			// Just check for permissions, nothing else to do. The Flow board
			// will be put in place right before the rest of the data is stored
			// (in SubmissionHandler::commit), after everything's been validated.
			$status = $controller->safeAllowCreation( $page, $this->getUser(),
				/* $mustNotExist = */ true, /* $forWrite = */ false );
			if ( !$status->isGood() ) {
				$this->dieWithError( [ 'apierror-flow-safeallowcreationfailed', $status->getMessage() ], 'invalid-page' );
			}
		}

		// @phan-suppress-next-line PhanTypeMismatchReturnNullable T240141
		return $page;
	}

	public function getAllowedParams() {
		return [
			'submodule' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => 'submodule',
			],
			'page' => [
				// supply bogus default - not every action may *need* ?page=
				ApiBase::PARAM_DFLT => Title::newFromText( 'Flow-enabled page', NS_TOPIC )->getPrefixedDBkey(),
			],
			'token' => '',
		];
	}

	public function isWriteMode() {
		// We can't use extractRequestParams() here because getHelpFlags() calls this function,
		// and we'd error out because the submodule parameter isn't set.
		$moduleName = $this->getMain()->getVal( 'submodule' );
		$module = $this->moduleManager->getModule( $moduleName, 'submodule' );
		return $module ? $module->isWriteMode() : false;
	}

	public function getHelpUrls() {
		return [
			'https://www.mediawiki.org/wiki/Extension:Flow/API',
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			'action=flow&submodule=edit-header&page=Talk:Sandbox&ehprev_revision=???&ehcontent=Nice%20to&20meet%20you'
				=> 'apihelp-flow-example-1',
		];
	}

	public function mustBePosted() {
		return false;
	}

	public function needsToken() {
		return false;
	}
}
