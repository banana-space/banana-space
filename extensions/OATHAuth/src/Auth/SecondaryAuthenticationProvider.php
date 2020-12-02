<?php

namespace MediaWiki\Extension\OATHAuth\Auth;

use MediaWiki\Auth\AbstractSecondaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Extension\OATHAuth\IModule;
use MediaWiki\Extension\OATHAuth\OATHAuth;
use MediaWiki\MediaWikiServices;
use User;

class SecondaryAuthenticationProvider extends AbstractSecondaryAuthenticationProvider {
	/**
	 * @param string $action
	 * @param array $options
	 *
	 * @return array
	 */
	public function getAuthenticationRequests( $action, array $options ) {
		return [];
	}

	/**
	 * @param User $user
	 * @param User $creator
	 * @param array|AuthenticationRequest[] $reqs
	 * @return AuthenticationResponse
	 */
	public function beginSecondaryAccountCreation( $user, $creator, array $reqs ) {
		return AuthenticationResponse::newAbstain();
	}

	/**
	 * If the user has enabled two-factor authentication, request a second factor.
	 *
	 * @param User $user
	 * @param array $reqs
	 *
	 * @return AuthenticationResponse
	 */
	public function beginSecondaryAuthentication( $user, array $reqs ) {
		$authUser = MediaWikiServices::getInstance()->getService( 'OATHUserRepository' )
			->findByUser( $user );

		$module = $authUser->getModule();
		if ( $module === null ) {
			return AuthenticationResponse::newAbstain();
		}

		$provider = $this->getProviderForModule( $module );
		return $provider->beginSecondaryAuthentication( $user, $reqs );
	}

	/**
	 * Verify the second factor.
	 * @inheritDoc
	 */
	public function continueSecondaryAuthentication( $user, array $reqs ) {
		$authUser = MediaWikiServices::getInstance()->getService( 'OATHUserRepository' )
			->findByUser( $user );

		$module = $authUser->getModule();
		$provider = $this->getProviderForModule( $module );
		$response = $provider->continueSecondaryAuthentication( $user, $reqs );
		if ( $response->status === AuthenticationResponse::PASS ) {
			$user->getRequest()->getSession()->set( OATHAuth::AUTHENTICATED_OVER_2FA, true );
		}
		return $response;
	}

	/**
	 * @param IModule $module
	 * @return SecondaryAuthenticationProvider
	 */
	private function getProviderForModule( IModule $module ) {
		$provider = $module->getSecondaryAuthProvider();
		$provider->setLogger( $this->logger );
		$provider->setManager( $this->manager );
		$provider->setConfig( $this->config );
		return $provider;
	}
}
