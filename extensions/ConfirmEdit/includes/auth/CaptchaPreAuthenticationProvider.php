<?php

use MediaWiki\Auth\AbstractPreAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Logger\LoggerFactory;

class CaptchaPreAuthenticationProvider extends AbstractPreAuthenticationProvider {
	public function getAuthenticationRequests( $action, array $options ) {
		$captcha = ConfirmEditHooks::getInstance();
		$user = User::newFromName( $options['username'] );

		$needed = false;
		switch ( $action ) {
			case AuthManager::ACTION_CREATE:
				$needed = $captcha->needCreateAccountCaptcha( $user ?: new User() );
				if ( $needed ) {
					$captcha->setAction( 'accountcreate' );
					LoggerFactory::getInstance( 'authevents' )
						->info( 'Captcha shown on account creation', [
							'event' => 'captcha.display',
							'eventType' => 'accountcreation',
						] );
				}
				break;
			case AuthManager::ACTION_LOGIN:
				// Captcha is shown on login when there were too many failed attempts from the
				// current IP or user. The latter is a bit awkward because we don't know the
				// username yet. The username from the last successful login is stored in a cookie,
				// but we still must make sure to not lock out other usernames so we use a session
				// flag. This will result in confusing error messages if the browser cannot persist
				// the session, but then login would be impossible anyway so no big deal.

				// If the username ends to be one that does not trigger the captcha, that will
				// result in weird behavior (if the user leaves the captcha field open, they get
				// a required field error, if they fill it with an invalid answer, it will pass)
				// - again, not a huge deal.
				$session = $this->manager->getRequest()->getSession();
				$sessionFlag = $session->get( 'ConfirmEdit:loginCaptchaPerUserTriggered' );
				$suggestedUsername = $session->suggestLoginUsername();
				if (
					$captcha->isBadLoginTriggered()
					|| $sessionFlag
					|| $suggestedUsername && $captcha->isBadLoginPerUserTriggered( $suggestedUsername )
				) {
					$needed = true;
					$captcha->setAction( 'badlogin' );
					LoggerFactory::getInstance( 'authevents' )
						->info( 'Captcha shown on account creation', [
							'event' => 'captcha.display',
							'eventType' => 'accountcreation',
						] );
					break;
				}
				break;
		}

		if ( $needed ) {
			return [ $captcha->createAuthenticationRequest() ];
		} else {
			return [];
		}
	}

	public function testForAuthentication( array $reqs ) {
		$captcha = ConfirmEditHooks::getInstance();
		$username = AuthenticationRequest::getUsernameFromRequests( $reqs );
		$success = true;
		$isBadLoginPerUserTriggered = $username ?
			$captcha->isBadLoginPerUserTriggered( $username ) : false;

		if ( $captcha->isBadLoginTriggered() || $isBadLoginPerUserTriggered ) {
			$captcha->setAction( 'badlogin' );
			$captcha->setTrigger( "post-badlogin login '$username'" );
			$success = $this->verifyCaptcha( $captcha, $reqs, new User() );
			LoggerFactory::getInstance( 'authevents' )->info( 'Captcha submitted on login', [
				'event' => 'captcha.submit',
				'eventType' => 'login',
				'successful' => $success,
			] );
		}

		if ( $isBadLoginPerUserTriggered || $isBadLoginPerUserTriggered === null ) {
			$session = $this->manager->getRequest()->getSession();
			$session->set( 'ConfirmEdit:loginCaptchaPerUserTriggered', true );
		}

		// Make brute force attacks harder by not telling whether the password or the
		// captcha failed.
		return $success ? Status::newGood() : $this->makeError( 'wrongpassword', $captcha );
	}

	public function testForAccountCreation( $user, $creator, array $reqs ) {
		$captcha = ConfirmEditHooks::getInstance();

		if ( $captcha->needCreateAccountCaptcha( $creator ) ) {
			$username = $user->getName();
			$captcha->setAction( 'accountcreate' );
			$captcha->setTrigger( "new account '$username'" );
			$success = $this->verifyCaptcha( $captcha, $reqs, $user );
			LoggerFactory::getInstance( 'authevents' )->info( 'Captcha submitted on account creation', [
				'event' => 'captcha.submit',
				'eventType' => 'accountcreation',
				'successful' => $success,
			] );
			if ( !$success ) {
				return $this->makeError( 'captcha-createaccount-fail', $captcha );
			}
		}
		return Status::newGood();
	}

	public function postAuthentication( $user, AuthenticationResponse $response ) {
		$captcha = ConfirmEditHooks::getInstance();
		switch ( $response->status ) {
			case AuthenticationResponse::PASS:
			case AuthenticationResponse::RESTART:
				$session = $this->manager->getRequest()->getSession();
				$session->remove( 'ConfirmEdit:loginCaptchaPerUserTriggered' );
				$captcha->resetBadLoginCounter( $user ? $user->getName() : null );
				break;
			case AuthenticationResponse::FAIL:
				$captcha->increaseBadLoginCounter( $user ? $user->getName() : null );
				break;
		}
	}

	/**
	 * Verify submitted captcha.
	 * Assumes that the user has to pass the capctha (permission checks are caller's responsibility).
	 * @param SimpleCaptcha $captcha
	 * @param AuthenticationRequest[] $reqs
	 * @param User $user
	 * @return bool
	 */
	protected function verifyCaptcha( SimpleCaptcha $captcha, array $reqs, User $user ) {
		/** @var CaptchaAuthenticationRequest $req */
		$req = AuthenticationRequest::getRequestByClass( $reqs,
			CaptchaAuthenticationRequest::class, true );
		if ( !$req ) {
			return false;
		}
		return $captcha->passCaptchaLimited( $req->captchaId, $req->captchaWord, $user );
	}

	/**
	 * @param string $message Message key
	 * @param SimpleCaptcha $captcha
	 * @return Status
	 */
	protected function makeError( $message, SimpleCaptcha $captcha ) {
		$error = $captcha->getError();
		if ( $error ) {
			return Status::newFatal( wfMessage( 'captcha-error', $error ) );
		}
		return Status::newFatal( $message );
	}
}
