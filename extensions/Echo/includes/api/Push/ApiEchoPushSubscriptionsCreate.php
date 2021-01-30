<?php

namespace EchoPush\Api;

use ApiBase;
use ApiMain;
use EchoPush\SubscriptionManager;
use EchoServices;
use Wikimedia\ParamValidator\ParamValidator;

class ApiEchoPushSubscriptionsCreate extends ApiBase {

	/**
	 * Supported push notification providers:
	 * (1) fcm: Firebase Cloud Messaging
	 * (2) apns: Apple Push Notification Service
	 */
	private const PROVIDERS = [ 'fcm', 'apns' ];

	/** @var ApiBase */
	private $parent;

	/** @var SubscriptionManager */
	private $subscriptionManager;

	/**
	 * Static entry point for initializing the module
	 * @param ApiBase $parent Parent module
	 * @param string $name Module name
	 * @return ApiEchoPushSubscriptionsCreate
	 */
	public static function factory( ApiBase $parent, string $name ):
	ApiEchoPushSubscriptionsCreate {
		$subscriptionManger = EchoServices::getInstance()->getPushSubscriptionManager();
		$module = new self( $parent->getMain(), $name, $subscriptionManger );
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
		$provider = $this->getParameter( 'provider' );
		$token = $this->getParameter( 'providertoken' );
		$success = $this->subscriptionManager->create( $this->getUser(), $provider, $token );
		if ( !$success ) {
			$this->dieWithError( 'apierror-echo-push-token-exists' );
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
			'provider' => [
				ParamValidator::PARAM_TYPE => self::PROVIDERS,
				ParamValidator::PARAM_REQUIRED => true,
			],
			'providertoken' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages(): array {
		return [
			"action=echopushsubscriptions&command=create&provider=fcm&providertoken=ABC123" =>
				"apihelp-echopushsubscriptions+create-example"
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
