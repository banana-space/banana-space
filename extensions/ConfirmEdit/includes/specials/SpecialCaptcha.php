<?php

class SpecialCaptcha extends UnlistedSpecialPage {
	public function __construct() {
		parent::__construct( 'Captcha' );
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		$this->setHeaders();

		$instance = ConfirmEditHooks::getInstance();

		if ( $par === 'image' && method_exists( $instance, 'showImage' ) ) {
			// @todo: Do this in a more OOP way
			/** @phan-suppress-next-line PhanUndeclaredMethod */
			$instance->showImage();
			return;
		}

		$instance->showHelp();
	}
}
