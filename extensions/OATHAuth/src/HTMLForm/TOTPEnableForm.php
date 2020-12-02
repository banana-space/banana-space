<?php

namespace MediaWiki\Extension\OATHAuth\HTMLForm;

use Html;
use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWiki\Logger\LoggerFactory;
use Status;

class TOTPEnableForm extends OATHAuthOOUIHTMLForm implements IManageForm {
	/**
	 * @param array|bool|Status|string $submitResult
	 * @return string
	 */
	public function getHTML( $submitResult ) {
		$this->getOutput()->addModules( 'ext.oath.totp.showqrcode' );
		$this->getOutput()->addModuleStyles( 'ext.oath.totp.showqrcode.styles' );

		return parent::getHTML( $submitResult );
	}

	/**
	 * Add content to output when operation was successful
	 */
	public function onSuccess() {
		$this->getOutput()->addWikiMsg( 'oathauth-validatedoath' );
	}

	/**
	 * @return array
	 */
	protected function getDescriptors() {
		$keyData = $this->getRequest()->getSessionData( 'oathauth_totp_key' ) ?? [];
		$key = TOTPKey::newFromArray( $keyData );
		if ( !$key instanceof TOTPKey ) {
			$key = TOTPKey::newFromRandom();
			$this->getRequest()->setSessionData(
				'oathauth_totp_key',
				$key->jsonSerialize()
			);
		}

		$secret = $key->getSecret();
		$label = "{$this->oathUser->getIssuer()}:{$this->oathUser->getAccount()}";
		$qrcodeUrl = "otpauth://totp/"
			. rawurlencode( $label )
			. "?secret="
			. rawurlencode( $secret )
			. "&issuer="
			. rawurlencode( $this->oathUser->getIssuer() );

		$qrcodeElement = Html::element( 'div', [
			'data-mw-qrcode-url' => $qrcodeUrl,
			'class' => 'mw-display-qrcode',
			// Include width/height, so js won't re-arrange layout
			// And non-js users will have this hidden with CSS
			'style' => 'width: 256px; height: 256px;'
		] );

		return [
			'app' => [
				'type' => 'info',
				'default' => $this->msg( 'oathauth-step1-test' )->escaped(),
				'raw' => true,
				'section' => 'step1',
			],
			'qrcode' => [
				'type' => 'info',
				'default' => $qrcodeElement,
				'raw' => true,
				'section' => 'step2',
			],
			'manual' => [
				'type' => 'info',
				'label-message' => 'oathauth-step2alt',
				'default' =>
					'<strong>' . $this->msg( 'oathauth-account' )->escaped() . '</strong><br/>'
					. htmlspecialchars( $this->oathUser->getAccount() ) . '<br/><br/>'
					. '<strong>' . $this->msg( 'oathauth-secret' )->escaped() . '</strong><br/>'
					. '<kbd>' . $this->getSecretForDisplay( $key ) . '</kbd><br/>',
				'raw' => true,
				'section' => 'step2',
			],
			'scratchtokens' => [
				'type' => 'info',
				'default' =>
					$this->msg( 'oathauth-scratchtokens' )->parse()
					. $this->createResourceList( $this->getScratchTokensForDisplay( $key ) ),
				'raw' => true,
				'section' => 'step3',
			],
			'token' => [
				'type' => 'text',
				'default' => '',
				'label-message' => 'oathauth-entertoken',
				'name' => 'token',
				'section' => 'step4',
				'dir' => 'ltr',
				'autocomplete' => false,
				'spellcheck' => false,
			]
		];
	}

	/**
	 * @param array $resources
	 * @return string
	 */
	private function createResourceList( $resources ) {
		$resourceList = '';
		foreach ( $resources as $resource ) {
			$resourceList .= Html::rawElement( 'li', [], Html::rawElement( 'kbd', [], $resource ) );
		}
		return Html::rawElement( 'ul', [], $resourceList );
	}

	/**
	 * Retrieve the current secret for display purposes
	 *
	 * The characters of the token are split in groups of 4
	 *
	 * @param TOTPKey $key
	 * @return string
	 */
	protected function getSecretForDisplay( TOTPKey $key ) {
		return $this->tokenFormatterFunction( $key->getSecret() );
	}

	/**
	 * Retrieve current scratch tokens for display purposes
	 *
	 * The characters of the token are split in groups of 4
	 *
	 * @param TOTPKey $key
	 * @return string[]
	 */
	protected function getScratchTokensForDisplay( TOTPKey $key ) {
		return array_map( [ $this, 'tokenFormatterFunction' ], $key->getScratchTokens() );
	}

	/**
	 * Formats a key or scratch token by creating groups of 4 separated by space characters
	 *
	 * @param string $token Token to format
	 * @return string The token formatted for display
	 */
	private function tokenFormatterFunction( $token ) {
		return implode( ' ', str_split( $token, 4 ) );
	}

	/**
	 * @param array $formData
	 * @return array|bool
	 * @throws \ConfigException
	 * @throws \MWException
	 */
	public function onSubmit( array $formData ) {
		$keyData = $this->getRequest()->getSessionData( 'oathauth_totp_key' ) ?? [];
		$key = TOTPKey::newFromArray( $keyData );
		if ( !$key instanceof TOTPKey ) {
			return [ 'oathauth-invalidrequest' ];
		}

		if ( $key->isScratchToken( $formData['token'] ) ) {
			// A scratch token is not allowed for enrollment
			LoggerFactory::getInstance( 'authentication' )->info(
				'OATHAuth {user} attempted to enable 2FA using a scratch token from {clientip}', [
					'user' => $this->getUser()->getName(),
					'clientip' => $this->getRequest()->getIP(),
				]
			);
			return [ 'oathauth-noscratchforvalidation' ];
		}
		if ( !$key->verify( [ 'token' => $formData['token'] ], $this->oathUser ) ) {
			LoggerFactory::getInstance( 'authentication' )->info(
				'OATHAuth {user} failed to provide a correct token while enabling 2FA from {clientip}', [
					'user' => $this->getUser()->getName(),
					'clientip' => $this->getRequest()->getIP(),
				]
			);
			return [ 'oathauth-failedtovalidateoath' ];
		}

		$this->getRequest()->setSessionData( 'oathauth_totp_key', null );
		$this->oathUser->setKeys( [ $key ] );
		$this->oathUser->setModule( $this->module );
		$this->oathRepo->persist( $this->oathUser, $this->getRequest()->getIP() );

		return true;
	}
}
