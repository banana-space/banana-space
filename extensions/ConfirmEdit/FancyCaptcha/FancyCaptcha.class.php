<?php

use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthManager;

/**
 * FancyCaptcha for displaying captchas precomputed by captcha.py
 */
class FancyCaptcha extends SimpleCaptcha {
	// used for fancycaptcha-edit, fancycaptcha-addurl, fancycaptcha-badlogin,
	// fancycaptcha-accountcreate, fancycaptcha-create, fancycaptcha-sendemail via getMessage()
	protected static $messagePrefix = 'fancycaptcha-';

	/**
	 * @return FileBackend
	 */
	public function getBackend() {
		global $wgCaptchaFileBackend, $wgCaptchaDirectory;

		if ( $wgCaptchaFileBackend ) {
			return FileBackendGroup::singleton()->get( $wgCaptchaFileBackend );
		} else {
			static $backend = null;
			if ( !$backend ) {
				$backend = new FSFileBackend( [
					'name'           => 'captcha-backend',
					'wikiId'         => wfWikiID(),
					'lockManager'    => new NullLockManager( [] ),
					'containerPaths' => [ 'captcha-render' => $wgCaptchaDirectory ],
					'fileMode'       => 777,
					'obResetFunc'    => 'wfResetOutputBuffers',
					'streamMimeFunc' => [ 'StreamFile', 'contentTypeFromPath' ]
				] );
			}
			return $backend;
		}
	}

	/**
	 * @deprecated Use getCaptchaCount instead for an accurate figure
	 * @return int Number of captcha files
	 */
	public function estimateCaptchaCount() {
		wfDeprecated( __METHOD__ );
		return $this->getCaptchaCount();
	}

	/**
	 * @return int Number of captcha files
	 */
	public function getCaptchaCount() {
		$backend = $this->getBackend();
		$files = $backend->getFileList(
			[ 'dir' => $backend->getRootStoragePath() . '/captcha-render' ]
		);

		return iterator_count( $files );
	}

	/**
	 * Check if the submitted form matches the captcha session data provided
	 * by the plugin when the form was generated.
	 *
	 * @param string $answer
	 * @param array $info
	 * @return bool
	 */
	function keyMatch( $answer, $info ) {
		global $wgCaptchaSecret;

		$digest = $wgCaptchaSecret . $info['salt'] . $answer . $wgCaptchaSecret . $info['salt'];
		$answerHash = substr( md5( $digest ), 0, 16 );

		if ( $answerHash == $info['hash'] ) {
			wfDebug( "FancyCaptcha: answer hash matches expected {$info['hash']}\n" );
			return true;
		} else {
			wfDebug( "FancyCaptcha: answer hashes to $answerHash, expected {$info['hash']}\n" );
			return false;
		}
	}

	/**
	 * @param array &$resultArr
	 */
	function addCaptchaAPI( &$resultArr ) {
		$info = $this->pickImage();
		if ( !$info ) {
			$resultArr['captcha']['error'] = 'Out of images';
			return;
		}
		$index = $this->storeCaptcha( $info );
		$title = SpecialPage::getTitleFor( 'Captcha', 'image' );
		$resultArr['captcha'] = $this->describeCaptchaType();
		$resultArr['captcha']['id'] = $index;
		$resultArr['captcha']['url'] = $title->getLocalURL( 'wpCaptchaId=' . urlencode( $index ) );
	}

	/**
	 * @return array
	 */
	public function describeCaptchaType() {
		return [
			'type' => 'image',
			'mime' => 'image/png',
		];
	}

	/**
	 * @param int $tabIndex
	 * @return array
	 */
	function getFormInformation( $tabIndex = 1 ) {
		$modules = [];

		$title = SpecialPage::getTitleFor( 'Captcha', 'image' );
		$info = $this->getCaptcha();
		$index = $this->storeCaptcha( $info );

		// Loaded only for clients with JS enabled
		$modules[] = 'ext.confirmEdit.fancyCaptcha';

		$captchaReload = Html::element(
			'small',
			[
				'class' => 'confirmedit-captcha-reload fancycaptcha-reload'
			],
			wfMessage( 'fancycaptcha-reload-text' )->text()
		);

		$form = Html::openElement( 'div' ) .
			Html::element( 'label', [
					'for' => 'wpCaptchaWord',
				],
				wfMessage( 'captcha-label' )->text() . ' ' . wfMessage( 'fancycaptcha-captcha' )->text()
			) .
			Html::openElement( 'div', [ 'class' => 'fancycaptcha-captcha-container' ] ) .
			Html::openElement( 'div', [ 'class' => 'fancycaptcha-captcha-and-reload' ] ) .
			Html::openElement( 'div', [ 'class' => 'fancycaptcha-image-container' ] ) .
			Html::element( 'img', [
					'class'  => 'fancycaptcha-image',
					'src'    => $title->getLocalURL( 'wpCaptchaId=' . urlencode( $index ) ),
					'alt'    => ''
				]
			) . $captchaReload . Html::closeElement( 'div' ) . Html::closeElement( 'div' ) . "\n" .
			Html::element( 'input', [
					'name' => 'wpCaptchaWord',
					'class' => 'mw-ui-input',
					'id'   => 'wpCaptchaWord',
					'type' => 'text',
					'size' => '12',  // max_length in captcha.py plus fudge factor
					'autocomplete' => 'off',
					'autocorrect' => 'off',
					'autocapitalize' => 'off',
					'required' => 'required',
					'tabindex' => $tabIndex,
					'placeholder' => wfMessage( 'fancycaptcha-imgcaptcha-ph' )
				]
			); // tab in before the edit textarea
			if ( $this->action == 'createaccount' ) {
				// use raw element, because the message can contain links or some other html
				$form .= Html::rawElement( 'small', [
						'class' => 'mw-createacct-captcha-assisted'
					], wfMessage( 'createacct-imgcaptcha-help' )->parse()
				);
			}
			$form .= Html::element( 'input', [
					'type'  => 'hidden',
					'name'  => 'wpCaptchaId',
					'id'    => 'wpCaptchaId',
					'value' => $index
				]
			) . Html::closeElement( 'div' ) . Html::closeElement( 'div' ) . "\n";

		return [
			'html' => $form,
			'modules' => $modules,
			// Uses addModuleStyles so it is loaded when JS is disabled.
			'modulestyles' => [ 'ext.confirmEdit.fancyCaptcha.styles' ],
		];
	}

	/**
	 * Select a previously generated captcha image from the queue.
	 * @return mixed tuple of (salt key, text hash) or false if no image to find
	 */
	protected function pickImage() {
		global $wgCaptchaDirectoryLevels;

		$lockouts = 0; // number of times another process claimed a file before this one
		$baseDir = $this->getBackend()->getRootStoragePath() . '/captcha-render';
		return $this->pickImageDir( $baseDir, $wgCaptchaDirectoryLevels, $lockouts );
	}

	/**
	 * @param string $directory
	 * @param int $levels
	 * @param int &$lockouts
	 * @return array|bool
	 */
	protected function pickImageDir( $directory, $levels, &$lockouts ) {
		global $wgMemc;

		if ( $levels <= 0 ) { // $directory has regular files
			return $this->pickImageFromDir( $directory, $lockouts );
		}

		$backend = $this->getBackend();

		$key  = "fancycaptcha:dirlist:{$backend->getWikiId()}:" . sha1( $directory );
		$dirs = $wgMemc->get( $key ); // check cache
		if ( !is_array( $dirs ) || !count( $dirs ) ) { // cache miss
			$dirs = []; // subdirs actually present...
			foreach ( $backend->getTopDirectoryList( [ 'dir' => $directory ] ) as $entry ) {
				if ( ctype_xdigit( $entry ) && strlen( $entry ) == 1 ) {
					$dirs[] = $entry;
				}
			}
			wfDebug( "Cache miss for $directory subdirectory listing.\n" );
			if ( count( $dirs ) ) {
				$wgMemc->set( $key, $dirs, 86400 );
			}
		}

		if ( !count( $dirs ) ) {
			// Remove this directory if empty so callers don't keep looking here
			$backend->clean( [ 'dir' => $directory ] );
			return false; // none found
		}

		$place = mt_rand( 0, count( $dirs ) - 1 ); // pick a random subdir
		// In case all dirs are not filled, cycle through next digits...
		$fancyCount = count( $dirs );
		for ( $j = 0; $j < $fancyCount; $j++ ) {
			$char = $dirs[( $place + $j ) % count( $dirs )];
			$info = $this->pickImageDir( "$directory/$char", $levels - 1, $lockouts );
			if ( $info ) {
				return $info; // found a captcha
			} else {
				wfDebug( "Could not find captcha in $directory.\n" );
				$wgMemc->delete( $key ); // files changed on disk?
			}
		}

		return false; // didn't find any images in this directory... empty?
	}

	/**
	 * @param string $directory
	 * @param int &$lockouts
	 * @return array|bool
	 */
	protected function pickImageFromDir( $directory, &$lockouts ) {
		global $wgMemc;

		$backend = $this->getBackend();

		$key   = "fancycaptcha:filelist:{$backend->getWikiId()}:" . sha1( $directory );
		$files = $wgMemc->get( $key ); // check cache
		if ( !is_array( $files ) || !count( $files ) ) { // cache miss
			$files = []; // captcha files
			foreach ( $backend->getTopFileList( [ 'dir' => $directory ] ) as $entry ) {
				$files[] = $entry;
				if ( count( $files ) >= 500 ) { // sanity
					wfDebug( 'Skipping some captchas; $wgCaptchaDirectoryLevels set too low?.' );
					break;
				}
			}
			if ( count( $files ) ) {
				$wgMemc->set( $key, $files, 86400 );
			}
			wfDebug( "Cache miss for $directory captcha listing.\n" );
		}

		if ( !count( $files ) ) {
			// Remove this directory if empty so callers don't keep looking here
			$backend->clean( [ 'dir' => $directory ] );
			return false;
		}

		$info = $this->pickImageFromList( $directory, $files, $lockouts );
		if ( !$info ) {
			wfDebug( "Could not find captcha in $directory.\n" );
			$wgMemc->delete( $key ); // files changed on disk?
		}

		return $info;
	}

	/**
	 * @param string $directory
	 * @param array $files
	 * @param int &$lockouts
	 * @return array|bool
	 */
	protected function pickImageFromList( $directory, array $files, &$lockouts ) {
		global $wgMemc, $wgCaptchaDeleteOnSolve;

		if ( !count( $files ) ) {
			return false; // none found
		}

		$backend  = $this->getBackend();
		$place    = mt_rand( 0, count( $files ) - 1 ); // pick a random file
		$misses   = 0; // number of files in listing that don't actually exist
		$fancyImageCount = count( $files );
		for ( $j = 0; $j < $fancyImageCount; $j++ ) {
			$entry = $files[( $place + $j ) % count( $files )];
			if ( preg_match( '/^image_([0-9a-f]+)_([0-9a-f]+)\\.png$/', $entry, $matches ) ) {
				if ( $wgCaptchaDeleteOnSolve ) { // captcha will be deleted when solved
					$key = "fancycaptcha:filelock:{$backend->getWikiId()}:" . sha1( $entry );
					// Try to claim this captcha for 10 minutes (for the user to solve)...
					if ( ++$lockouts <= 10 && !$wgMemc->add( $key, '1', 600 ) ) {
						continue; // could not acquire (skip it to avoid race conditions)
					}
				}
				if ( !$backend->fileExists( [ 'src' => "$directory/$entry" ] ) ) {
					if ( ++$misses >= 5 ) { // too many files in the listing don't exist
						break; // listing cache too stale? break out so it will be cleared
					}
					continue; // try next file
				}
				return [
					'salt'   => $matches[1],
					'hash'   => $matches[2],
					'viewed' => false,
				];
			}
		}

		return false; // none found
	}

	/**
	 * @return bool|StatusValue
	 */
	function showImage() {
		global $wgOut, $wgRequest;

		$wgOut->disable();

		$index = $wgRequest->getVal( 'wpCaptchaId' );
		$info = $this->retrieveCaptcha( $index );
		if ( $info ) {
			$timestamp = new MWTimestamp();
			$info['viewed'] = $timestamp->getTimestamp();
			$this->storeCaptcha( $info );

			$salt = $info['salt'];
			$hash = $info['hash'];

			return $this->getBackend()->streamFile( [
				'src'     => $this->imagePath( $salt, $hash ),
				'headers' => [ "Cache-Control: private, s-maxage=0, max-age=3600" ]
			] )->isOK();
		}

		wfHttpError( 400, 'Request Error', 'Requested bogus captcha image' );
		return false;
	}

	/**
	 * @param string $salt
	 * @param string $hash
	 * @return string
	 */
	public function imagePath( $salt, $hash ) {
		global $wgCaptchaDirectoryLevels;

		$file = $this->getBackend()->getRootStoragePath() . '/captcha-render/';
		for ( $i = 0; $i < $wgCaptchaDirectoryLevels; $i++ ) {
			$file .= $hash{ $i } . '/';
		}
		$file .= "image_{$salt}_{$hash}.png";

		return $file;
	}

	/**
	 * @param string $basename
	 * @return array (salt, hash)
	 * @throws Exception
	 */
	public function hashFromImageName( $basename ) {
		if ( preg_match( '/^image_([0-9a-f]+)_([0-9a-f]+)\\.png$/', $basename, $matches ) ) {
			return [ $matches[1], $matches[2] ];
		} else {
			throw new Exception( "Invalid filename '$basename'.\n" );
		}
	}

	/**
	 * Delete a solved captcha image, if $wgCaptchaDeleteOnSolve is true.
	 * @inheritDoc
	 */
	protected function passCaptcha( $index, $word ) {
		global $wgCaptchaDeleteOnSolve;

		$info = $this->retrieveCaptcha( $index ); // get the captcha info before it gets deleted
		$pass = parent::passCaptcha( $index, $word );

		if ( $pass && $wgCaptchaDeleteOnSolve ) {
			$this->getBackend()->quickDelete( [
				'src' => $this->imagePath( $info['salt'], $info['hash'] )
			] );
		}

		return $pass;
	}

	/**
	 * Returns an array with 'salt' and 'hash' keys. Hash is
	 * md5( $wgCaptchaSecret . $salt . $answer . $wgCaptchaSecret . $salt )[0..15]
	 * @return array
	 * @throws Exception When a captcha image cannot be produced.
	 */
	public function getCaptcha() {
		$info = $this->pickImage();
		if ( !$info ) {
			throw new UnderflowException( 'Ran out of captcha images' );
		}
		return $info;
	}

	/**
	 * @param array $captchaData
	 * @param string $id
	 * @return string
	 */
	public function getCaptchaInfo( $captchaData, $id ) {
		$title = SpecialPage::getTitleFor( 'Captcha', 'image' );
		return $title->getLocalURL( 'wpCaptchaId=' . urlencode( $id ) );
	}

	/**
	 * @param array $requests
	 * @param array $fieldInfo
	 * @param array &$formDescriptor
	 * @param string $action
	 */
	public function onAuthChangeFormFields(
		array $requests, array $fieldInfo, array &$formDescriptor, $action
	) {
		/** @var CaptchaAuthenticationRequest $req */
		$req =
			AuthenticationRequest::getRequestByClass( $requests,
				CaptchaAuthenticationRequest::class, true );
		if ( !$req ) {
			return;
		}

		// HTMLFancyCaptchaField will include this
		unset( $formDescriptor['captchaInfo' ] );

		$formDescriptor['captchaWord'] = [
			'class' => HTMLFancyCaptchaField::class,
			'imageUrl' => $this->getCaptchaInfo( $req->captchaData, $req->captchaId ),
			'label-message' => $this->getMessage( $this->action ),
			'showCreateHelp' => in_array( $action, [
				AuthManager::ACTION_CREATE,
				AuthManager::ACTION_CREATE_CONTINUE
			], true ),
		] + $formDescriptor['captchaWord'];
	}
}
