<?php
/**
 * SkinTemplate class for the Timeless skin
 *
 * @ingroup Skins
 */
class SkinTimeless extends SkinTemplate {
	public $skinname = 'timeless', $stylename = 'Timeless',
		$template = 'TimelessTemplate';

	/**
	 * @param OutputPage $out
	 */
	public function initPage( OutputPage $out ) {
		parent::initPage( $out );

		$out->addMeta( 'viewport',
			'width=device-width, initial-scale=1.0, ' .
			'user-scalable=yes, minimum-scale=0.25, maximum-scale=5.0'
		);

		$out->addModuleStyles( [
			'mediawiki.skinning.content.externallinks',
			'skins.timeless',
			// This is a separate module from skins.timeless because it has its own
			// @media declarations in its less, and apparently modules cannot be defined
			// with both. That is the only reason.
			'skins.timeless.misc'
		] );
		$out->addModules( [
			'skins.timeless.js',
			'skins.timeless.mobile'
		] );
	}

	/**
	 * Add CSS via ResourceLoader
	 *
	 * @param OutputPage $out
	 */
	function setupSkinUserCss( OutputPage $out ) {
		parent::setupSkinUserCss( $out );
	}
}
