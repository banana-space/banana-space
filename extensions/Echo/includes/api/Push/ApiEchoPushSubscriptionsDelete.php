<?php

namespace EchoPush\Api;

use ApiBase;
use ApiMain;
use EchoPush\SubscriptionManager;
use EchoServices;
use Wikimedia\ParamValidator\ParamValidator;

class ApiEchoPushSubscriptionsDelete extends ApiBase {

	/** @var ApiBase */
	private $parent;

	/** @var SubscriptionManager */
	private $subscriptionManager;

	/**
	 * Static entry point for initializing the module
	 * @param ApiBase $parent Parent module
	 * @param string $name Module name
	 * @return ApiEchoPushSubscriptionsDelete
	 */
	public static function factory( ApiBase $parent, string $name ):
	ApiEchoPushSubscriptionsDelete {
		$subscriptionManager = EchoServices::getInstance()->getPushSubscriptionManager();
		$module = new self( $parent->getMain(), $name, $subscriptionManager );
		$module->parent = $parent;
		return $module;
	}

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param SubscriptionManager $subscriptionManager
	 */
	public function __construct(
		ApiMain $mainModule,
		string $moduleName,
		SubscriptionManager $subscriptionManager
	) {
		parent::__construct( $mainModule, $moduleName );
		$this->subscriptionManager = $subscriptionManager;
	}

	/**
	 * Entry point for executing the module.
	 * @inheritDoc
	 */
	public function execute(): void {
		$token = $this->getParameter( 'providertoken' );
		$numRowsDeleted = $this->subscriptionManager->delete( $this->getUser(), $token );
		if ( $numRowsDeleted == 0 ) {
			$this->dieWithError( 'apierror-echo-push-token-not-found' );
		}
	}

	/**
	 * Get the parent module.
	 * @return ApiBase
	 */
	public function getParent(): ApiBase {
		return $this->parent;
	}

	/** @inheritDoc */
	protected function getAllowedParams(): array {
		return [
			'providertoken' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages(): array {
		return [
			"action=echopushsubscriptions&command=delete&providertoken=ABC123" =>
				"apihelp-echopushsubscriptions+delete-example"
		];
	}

	// The parent module already enforces these but they make documentation nicer.

	/** @inheritDoc */
	public function isWriteMode(): bool {
		return true;
	}

	/** @inheritDoc */
	public function mustBePosted(): bool {
		return true;
	}

	/** @inheritDoc */
	public function isInternal(): bool {
		// experimental!
		return true;
	}

}
