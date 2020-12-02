<?php

namespace MediaWiki\Extension\OATHAuth\Special;

use ConfigException;
use FormSpecialPage;
use HTMLForm;
use ManualLogEntry;
use MediaWiki\Extension\OATHAuth\IModule;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MWException;
use User;

class VerifyOATHForUser extends FormSpecialPage {

	private const OATHAUTH_IS_ENABLED = 'enabled';
	private const OATHAUTH_NOT_ENABLED = 'disabled';

	/** @var OATHUserRepository */
	private $userRepo;

	/** @var string */
	private $enabledStatus;

	/** @var string */
	private $targetUser;

	public function __construct() {
		parent::__construct( 'VerifyOATHForUser', 'oathauth-verify-user' );
		$this->userRepo = MediaWikiServices::getInstance()->getService( 'OATHUserRepository' );
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * @return string
	 */
	protected function getLoginSecurityLevel() {
		return $this->getName();
	}

	/**
	 * @param HTMLForm $form
	 */
	public function alterForm( HTMLForm $form ) {
		$form->setMessagePrefix( 'oathauth' );
		$form->getOutput()->setPageTitle( $this->msg( 'oathauth-verify-for-user' ) );
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
		return true;
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
		return [
			'user' => [
				'type' => 'user',
				'default' => '',
				'label-message' => 'oathauth-enteruser',
				'name' => 'user',
				'required' => true,
			],
			'reason' => [
				'type' => 'text',
				'default' => '',
				'label-message' => 'oathauth-enterverifyreason',
				'name' => 'reason',
				'required' => true,
			],
		];
	}

	/**
	 * @param array $formData
	 * @return array|true
	 * @throws ConfigException
	 * @throws MWException
	 */
	public function onSubmit( array $formData ) {
		$this->targetUser = $formData['user'];
		$user = User::newFromName( $this->targetUser );
		if ( !$user || $user->getId() === 0 ) {
			return [ 'oathauth-user-not-found' ];
		}
		$oathUser = $this->userRepo->findByUser( $user );

		if ( !( $oathUser->getModule() instanceof IModule ) ||
			!$oathUser->getModule()->isEnabled( $oathUser ) ) {
			$result = self::OATHAUTH_NOT_ENABLED;
		} else {
			$result = self::OATHAUTH_IS_ENABLED;
		}

		$this->enabledStatus = $result;

		$logEntry = new ManualLogEntry( 'oath', 'verify' );
		$logEntry->setPerformer( $this->getUser() );
		$logEntry->setTarget( $user->getUserPage() );
		$logEntry->setComment( $formData['reason'] );
		$logEntry->insert();

		LoggerFactory::getInstance( 'authentication' )->info(
			'OATHAuth status checked for {usertarget} by {user} from {clientip}', [
				'user' => $this->getUser()->getName(),
				'usertarget' => $this->targetUser,
				'clientip' => $this->getRequest()->getIP(),
			]
		);

		return true;
	}

	/**
	 * @throws MWException
	 */
	public function onSuccess() {
		switch ( $this->enabledStatus ) {
			case self::OATHAUTH_IS_ENABLED:
				$msg = 'oathauth-verify-enabled';
				break;
			case self::OATHAUTH_NOT_ENABLED:
				$msg = 'oathauth-verify-disabled';
				break;
			default:
				throw new MWException(
					'Verification was successful but status is unknown'
				);
		}

		$out = $this->getOutput();
		$out->addBacklinkSubtitle( $this->getPageTitle() );
		$out->addWikiMsg( $msg, $this->targetUser );
	}

}
