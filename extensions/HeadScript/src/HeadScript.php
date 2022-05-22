<?php

class HeadScript {

	/**
	 * Code for adding the head script to the wiki
	 *
	 * @param OutputPage &$out
	 * @param Skin &$skin
	 */
	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
		global $wgHeadScriptCode, $wgHeadScriptName;

		$out->addHeadItem( $wgHeadScriptName, $wgHeadScriptCode );
	}
}
