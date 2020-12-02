<?php

class CodeEditorHooks {
	/**
	 * @param Title $title
	 * @param string $model
	 * @param string $format
	 * @return null|string
	 */
	public static function getPageLanguage( Title $title, $model, $format ) {
		if ( $model === CONTENT_MODEL_JAVASCRIPT ) {
			return 'javascript';
		} elseif ( $model === CONTENT_MODEL_CSS ) {
			return 'css';
		} elseif ( $model === CONTENT_MODEL_JSON ) {
			return 'json';
		}

		// Give extensions a chance
		// Note: $model and $format were added around the time of MediaWiki 1.28.
		$lang = null;
		Hooks::run( 'CodeEditorGetPageLanguage', [ $title, &$lang, $model, $format ] );

		return $lang;
	}

	/**
	 * @param User $user
	 * @param array &$defaultPreferences
	 */
	public static function getPreferences( User $user, &$defaultPreferences ) {
		$defaultPreferences['usecodeeditor'] = [
			'type' => 'api',
			'default' => '1',
		];
	}

	/**
	 * @param EditPage $editpage
	 * @param OutputPage $output
	 * @throws ErrorPageError
	 */
	public static function editPageShowEditFormInitial( EditPage $editpage, OutputPage $output ) {
		$title = $editpage->getContextTitle();
		$model = $editpage->contentModel;
		$format = $editpage->contentFormat;

		$lang = self::getPageLanguage( $title, $model, $format );
		if ( $lang && $output->getUser()->getOption( 'usebetatoolbar' ) ) {
			$output->addModules( 'ext.codeEditor' );
			$output->addJsConfigVars( 'wgCodeEditorCurrentLanguage', $lang );
			// Needed because ACE adds a blob: url web-worker.
			$output->getCSP()->addScriptSrc( "blob:" );
		} elseif ( !ExtensionRegistry::getInstance()->isLoaded( "WikiEditor" ) ) {
			throw new ErrorPageError( "codeeditor-error-title", "codeeditor-error-message" );
		}
	}
}
