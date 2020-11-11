<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

/**
 * Special page to display key information to the user
 *
 * @ingroup Extensions
 */
class SpecialOATHEnable extends FormSpecialPage {
	/** @var OATHUserRepository */
	private $OATHRepository;

	/** @var OATHUser */
	private $OATHUser;

	/**
	 * Initialize the OATH user based on the current local User object in the context
	 *
	 * @param OATHUserRepository $repository
	 * @param OATHUser $user
	 */
	public function __construct( OATHUserRepository $repository, OATHUser $user ) {
		parent::__construct( 'OATH', 'oathauth-enable', false );

		$this->OATHRepository = $repository;
		$this->OATHUser = $user;
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * Set the page title and add JavaScript RL modules
	 *
	 * @param HTMLForm $form
	 */
	public function alterForm( HTMLForm $form ) {
		$form->setMessagePrefix( 'oathauth' );
		$form->setWrapperLegend( false );
		$form->getOutput()->setPageTitle( $this->msg( 'oathauth-enable' ) );
		$form->getOutput()->addModules( 'ext.oath.showqrcode' );
		$form->getOutput()->addModuleStyles( 'ext.oath.showqrcode.styles' );
	}

	/**
	 * @return string
	 */
	protected function getDisplayFormat() {
		return 'ooui';
	}

	/**
	 * @return bool
	 */
	public function requiresUnblock() {
		return false;
	}

	/**
	 * Require users to be logged in
	 *
	 * @param User $user
	 *
	 * @return bool|void
	 */
	protected function checkExecutePermissions( User $user ) {
		parent::checkExecutePermissions( $user );

		$this->requireLogin();
	}

	/**
	 * @param string $par
	 */
	public function execute( $par ) {
		$this->getOutput()->disallowUserJs();
		parent::execute( $par );
	}

	/**
	 * @return array[]
	 */
	protected function getFormFields() {
		$key = $this->getRequest()->getSessionData( 'oathauth_key' );

		if ( $key === null ) {
			$key = OATHAuthKey::newFromRandom();
			$this->getRequest()->setSessionData( 'oathauth_key', $key );
		}

		$secret = $key->getSecret();
		$label = "{$this->OATHUser->getIssuer()}:{$this->OATHUser->getAccount()}";
		$qrcodeUrl = "otpauth://totp/"
			. rawurlencode( $label )
			. "?secret="
			. rawurlencode( $secret )
			. "&issuer="
			. rawurlencode( $this->OATHUser->getIssuer() );

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
					. $this->OATHUser->getAccount() . '<br/><br/>'
					. '<strong>' . $this->msg( 'oathauth-secret' )->escaped() . '</strong><br/>'
					. '<kbd>' . $this->getSecretForDisplay( $key ) . '</kbd><br/>',
				'raw' => true,
				'section' => 'step2',
			],
			'scratchtokens' => [
				'type' => 'info',
				'default' =>
					$this->msg( 'oathauth-scratchtokens' )
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
			],
			'returnto' => [
				'type' => 'hidden',
				'default' => $this->getRequest()->getVal( 'returnto' ),
				'name' => 'returnto',
			],
			'returntoquery' => [
				'type' => 'hidden',
				'default' => $this->getRequest()->getVal( 'returntoquery' ),
				'name' => 'returntoquery', ]
		];
	}

	/**
	 * @param array $formData
	 *
	 * @return array|bool
	 */
	public function onSubmit( array $formData ) {
		/** @var OATHAuthKey $key */
		$key = $this->getRequest()->getSessionData( 'oathauth_key' );

		if ( $key->isScratchToken( $formData['token'] ) ) {
			// A scratch token is not allowed for enrollement
			return [ 'oathauth-noscratchforvalidation' ];
		}
		if ( !$key->verifyToken( $formData['token'], $this->OATHUser ) ) {
			return [ 'oathauth-failedtovalidateoath' ];
		}

		$this->getRequest()->setSessionData( 'oathauth_key', null );
		$this->OATHUser->setKey( $key );
		$this->OATHRepository->persist( $this->OATHUser );

		return true;
	}

	public function onSuccess() {
		$this->getOutput()->addWikiMsg( 'oathauth-validatedoath' );
		$this->getOutput()->returnToMain();
	}

	/**
	 * @param $resources array
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
	 * @param OATHAuthKey $key
	 * @return String
	 */
	protected function getSecretForDisplay( OATHAuthKey $key ) {
		return $this->tokenFormatterFunction( $key->getSecret() );
	}

	/**
	 * Retrieve current scratch tokens for display purposes
	 *
	 * The characters of the token are split in groups of 4
	 *
	 * @param OATHAuthKey $key
	 * @return string[]
	 */
	protected function getScratchTokensForDisplay( OATHAuthKey $key ) {
		return array_map( [ $this, 'tokenFormatterFunction' ], $key->getScratchTokens() );
	}

	/**
	 * Formats a key or scratch token by creating groups of 4 seperated by space characters
	 *
	 * @param string $token Token to format
	 * @return string The token formatted for display
	 */
	private function tokenFormatterFunction( $token ) {
		return implode( ' ', str_split( $token, 4 ) );
	}
}
