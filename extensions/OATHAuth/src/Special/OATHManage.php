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

namespace MediaWiki\Extension\OATHAuth\Special;

use ConfigException;
use Html;
use HTMLForm;
use MediaWiki\Extension\OATHAuth\HTMLForm\IManageForm;
use MediaWiki\Extension\OATHAuth\IModule;
use MediaWiki\Extension\OATHAuth\OATHAuth;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\MediaWikiServices;
use Message;
use MWException;
use OOUI\ButtonWidget;
use OOUI\HorizontalLayout;
use OOUI\HtmlSnippet;
use OOUI\LabelWidget;
use OOUI\PanelLayout;
use PermissionsError;
use SpecialPage;
use UserNotLoggedIn;

class OATHManage extends SpecialPage {
	public const ACTION_ENABLE = 'enable';
	public const ACTION_DISABLE = 'disable';

	/**
	 * @var OATHAuth
	 */
	protected $auth;
	/**
	 * @var OATHUserRepository
	 */
	protected $userRepo;
	/**
	 * @var OATHUser
	 */
	protected $authUser;
	/**
	 * @var string
	 */
	protected $action;
	/**
	 * @var IModule
	 */
	protected $requestedModule;

	/**
	 * Initializes a page to manage available 2FA modules
	 *
	 * @throws ConfigException
	 * @throws MWException
	 */
	public function __construct() {
		parent::__construct( 'OATHManage', 'oathauth-enable' );

		$services = MediaWikiServices::getInstance();
		$this->auth = $services->getService( 'OATHAuth' );
		$this->userRepo = $services->getService( 'OATHUserRepository' );
		$this->authUser = $this->userRepo->findByUser( $this->getUser() );
	}

	/**
	 * @param null|string $subPage
	 * @return void
	 */
	public function execute( $subPage ) {
		$this->getOutput()->enableOOUI();
		$this->getOutput()->disallowUserJs();
		$this->setAction();
		$this->setModule();

		parent::execute( $subPage );

		if ( $this->requestedModule instanceof IModule ) {
			// Performing an action on a requested module
			$this->clearPage();
			if ( $this->shouldShowDisableWarning() ) {
				return $this->showDisableWarning();
			}
			return $this->addModuleHTML( $this->requestedModule );
		}

		$this->addGeneralHelp();
		if ( $this->hasEnabled() ) {
			$this->addEnabledHTML();
			if ( $this->hasAlternativeModules() ) {
				$this->addAlternativesHTML();
			}
			return;
		}
		$this->nothingEnabled();
	}

	/**
	 * @throws PermissionsError
	 * @throws UserNotLoggedIn
	 */
	public function checkPermissions() {
		$this->requireLogin();

		$canEnable = $this->getUser()->isAllowed( 'oathauth-enable' );

		if ( $this->action === static::ACTION_ENABLE && !$canEnable ) {
			$this->displayRestrictionError();
		}

		if ( !$this->hasEnabled() && !$canEnable ) {
			// No enabled module and cannot enable - nothing to do
			$this->displayRestrictionError();
		}

		if ( $this->action === static::ACTION_ENABLE && !$this->getRequest()->wasPosted() ) {
			// Trying to change the 2FA method (one is already enabled)
			$this->checkLoginSecurityLevel( 'oathauth-enable' );
		}
	}

	private function setAction() {
		$this->action = $this->getRequest()->getVal( 'action', '' );
	}

	private function setModule() {
		$moduleKey = $this->getRequest()->getVal( 'module', '' );
		$this->requestedModule = $this->auth->getModuleByKey( $moduleKey );
	}

	private function hasEnabled() {
		return $this->authUser->getModule() instanceof IModule;
	}

	private function getEnabled() {
		return $this->hasEnabled() ? $this->authUser->getModule() : null;
	}

	private function addEnabledHTML() {
		$this->addHeading( wfMessage( 'oathauth-ui-enabled-module' ) );
		$this->addModuleHTML( $this->getEnabled() );
	}

	private function addAlternativesHTML() {
		$this->addHeading( wfMessage( 'oathauth-ui-not-enabled-modules' ) );
		$this->addInactiveHTML();
	}

	private function nothingEnabled() {
		$this->addHeading( wfMessage( 'oathauth-ui-available-modules' ) );
		$this->addInactiveHTML();
	}

	private function addInactiveHTML() {
		foreach ( $this->auth->getAllModules() as $key => $module ) {
			if ( $this->isModuleEnabled( $module ) ) {
				continue;
			}
			$this->addModuleHTML( $module );
		}
	}

	private function addGeneralHelp() {
		$this->getOutput()->addHTML( wfMessage(
			'oathauth-ui-general-help'
		)->parseAsBlock() );
	}

	private function addModuleHTML( IModule $module ) {
		if ( $this->isModuleRequested( $module ) ) {
			return $this->addCustomContent( $module );
		}

		$panel = $this->getGenericContent( $module );
		if ( $this->isModuleEnabled( $module ) ) {
			$this->addCustomContent( $module, $panel );
		}

		return $this->getOutput()->addHTML( (string)$panel );
	}

	/**
	 * Get the panel with generic content for a module
	 *
	 * @param IModule $module
	 * @return PanelLayout
	 */
	private function getGenericContent( IModule $module ) {
		$modulePanel = new PanelLayout( [
			'framed' => true,
			'expanded' => false,
			'padded' => true
		] );
		$headerLayout = new HorizontalLayout();

		$label = new LabelWidget( [
			'label' => $module->getDisplayName()->text()
		] );
		if ( $this->shouldShowGenericButtons() ) {
			$button = new ButtonWidget( [
				'label' => $this->isModuleEnabled( $module ) ?
					wfMessage( 'oathauth-disable-generic' )->text() :
					wfMessage( 'oathauth-enable-generic' )->text(),
				'href' => $this->getOutput()->getTitle()->getLocalURL( [
					'action' => $this->isModuleEnabled( $module ) ?
						static::ACTION_DISABLE : static::ACTION_ENABLE,
					'module' => $module->getName(),
					'warn' => 1
				] )
			] );
			$headerLayout->addItems( [ $button ] );
		}
		$headerLayout->addItems( [ $label ] );

		$modulePanel->appendContent( $headerLayout );
		$modulePanel->appendContent( new HtmlSnippet(
			$module->getDescriptionMessage()->parseAsBlock()
		) );
		return $modulePanel;
	}

	/**
	 * @param IModule $module
	 * @param PanelLayout|null $panel
	 */
	private function addCustomContent( IModule $module, $panel = null ) {
		$form = $module->getManageForm( $this->action, $this->authUser, $this->userRepo );
		if ( $form === null || !$this->isValidFormType( $form ) ) {
			return;
		}
		$form->setTitle( $this->getOutput()->getTitle() );
		$this->ensureRequiredFormFields( $form, $module );
		$form->setSubmitCallback( [ $form, 'onSubmit' ] );
		if ( $form->show( $panel ) ) {
			$form->onSuccess();
		}
	}

	private function addHeading( Message $message ) {
		$this->getOutput()->addHTML( Html::element( 'h2', [], $message->text() ) );
	}

	private function shouldShowGenericButtons() {
		if ( !$this->requestedModule instanceof IModule ) {
			return true;
		}
		if ( !$this->isGenericAction() ) {
			return true;
		}
		return false;
	}

	private function isModuleRequested( IModule $module ) {
		if ( $this->requestedModule instanceof IModule ) {
			if ( $this->requestedModule->getName() === $module->getName() ) {
				return true;
			}
		}
		return false;
	}

	private function isModuleEnabled( IModule $module ) {
		if ( $this->getEnabled() instanceof IModule ) {
			return $this->getEnabled()->getName() === $module->getName();
		}
		return false;
	}

	/**
	 * Checks if given form instance fulfills required conditions
	 *
	 * @param mixed $form
	 * @return bool
	 */
	private function isValidFormType( $form ) {
		if ( !( $form instanceof HTMLForm ) ) {
			return false;
		}
		$implements = class_implements( $form );
		if ( !isset( $implements[IManageForm::class] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * @param IManageForm &$form
	 * @param IModule $module
	 */
	private function ensureRequiredFormFields( IManageForm &$form, IModule $module ) {
		if ( !$form->hasField( 'module' ) ) {
			$form->addHiddenField( 'module', $module->getName() );
		}
		if ( !$form->hasField( 'action' ) ) {
			$form->addHiddenField( 'action', $this->action );
		}
	}

	/**
	 * When performing an action on a module (like enable/disable),
	 * page should contain only form for that action
	 */
	private function clearPage() {
		if ( $this->isGenericAction() ) {
			$displayName = $this->requestedModule->getDisplayName();
			$pageTitle = $this->isModuleEnabled( $this->requestedModule ) ?
				wfMessage( 'oathauth-disable-page-title', $displayName )->text() :
				wfMessage( 'oathauth-enable-page-title', $displayName )->text();
			$this->getOutput()->setPageTitle( $pageTitle );
		}

		$this->getOutput()->clearHTML();
		$this->getOutput()->addBacklinkSubtitle( $this->getOutput()->getTitle() );
	}

	/**
	 * Actions enable and disable are generic and all modules must
	 * implement them, while all other actions are module-specific
	 * @return bool
	 */
	private function isGenericAction() {
		return in_array( $this->action, [ static::ACTION_ENABLE, static::ACTION_DISABLE ] );
	}

	private function hasAlternativeModules() {
		foreach ( $this->auth->getAllModules() as $key => $module ) {
			if ( !$this->isModuleEnabled( $module ) ) {
				return true;
			}
		}
		return false;
	}

	private function shouldShowDisableWarning() {
		return $this->getRequest()->getBool( 'warn' ) &&
			$this->requestedModule instanceof IModule &&
			$this->getEnabled() instanceof IModule;
	}

	private function showDisableWarning() {
		$panel = new PanelLayout( [
			'padded' => true,
			'framed' => true,
			'expanded' => false
		] );
		$headerMessage = $this->isSwitch() ?
			wfMessage( 'oathauth-switch-method-warning-header' ) :
			wfMessage( 'oathauth-disable-method-warning-header' );
		$genericMessage = $this->isSwitch() ?
			wfMessage(
				'oathauth-switch-method-warning',
				$this->getEnabled()->getDisplayName(),
				$this->requestedModule->getDisplayName()
			) :
			wfMessage( 'oathauth-disable-method-warning', $this->getEnabled()->getDisplayName() );

		$panel->appendContent( new HtmlSnippet(
			$genericMessage->parseAsBlock()
		) );

		$customMessage = $this->getEnabled()->getDisableWarningMessage();
		if ( $customMessage instanceof Message ) {
			$panel->appendContent( new HtmlSnippet(
				$customMessage->parseAsBlock()
			) );
		}

		$button = new ButtonWidget( [
			'label' => wfMessage( 'oathauth-disable-method-warning-button-label' )->plain(),
			'href' => $this->getOutput()->getTitle()->getLocalURL( [
				'action' => $this->action,
				'module' => $this->requestedModule->getName()
			] ),
			'flags' => [ 'primary', 'progressive' ]
		] );
		$panel->appendContent( $button );

		$this->getOutput()->setPageTitle( $headerMessage );
		$this->getOutput()->addHTML( $panel->toString() );
	}

	private function isSwitch() {
		return $this->requestedModule instanceof IModule &&
			$this->action === static::ACTION_ENABLE &&
			$this->getEnabled() instanceof IModule;
	}

}
