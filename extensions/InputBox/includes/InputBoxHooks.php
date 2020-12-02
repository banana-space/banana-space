<?php
/**
 * Hooks for InputBox extension
 *
 * @file
 * @ingroup Extensions
 */

/**
 * InputBox hooks
 */
class InputBoxHooks {

	/**
	 * Initialization
	 * @param Parser $parser
	 * @return true
	 */
	public static function register( Parser $parser ) {
		// Register the hook with the parser
		$parser->setHook( 'inputbox', [ 'InputBoxHooks', 'render' ] );

		// Continue
		return true;
	}

	/**
	 * Prepend prefix to wpNewTitle if necessary
	 * @param SpecialPage $special
	 * @param string $subPage
	 * @return true
	 */
	public static function onSpecialPageBeforeExecute( $special, $subPage ) {
		$request = $special->getRequest();
		$prefix = $request->getText( 'prefix', '' );
		$title = $request->getText( 'wpNewTitle', '' );
		$search = $request->getText( 'search', '' );
		$searchfilter = $request->getText( 'searchfilter', '' );
		if ( $special->getName() == 'Movepage' && $prefix !== '' && $title !== '' ) {
			$request->setVal( 'wpNewTitle', $prefix . $title );
			$request->unsetVal( 'prefix' );
		}
		if ( $special->getName() == 'Search' && $searchfilter !== '' ) {
			$request->setVal( 'search', $search . ' ' . $searchfilter );
		}
		return true;
	}

	/**
	 * Render the input box
	 * @param string $input
	 * @param array $args
	 * @param Parser $parser
	 * @return string
	 */
	public static function render( $input, $args, Parser $parser ) {
		// Create InputBox
		$inputBox = new InputBox( $parser );

		// Configure InputBox
		$inputBox->extractOptions( $parser->replaceVariables( $input ) );

		// Return output
		return $inputBox->render();
	}

	/**
	 * <inputbox type=create...> sends requests with action=edit, and
	 * possibly a &prefix=Foo.  So we pick that up here, munge prefix
	 * and title together, and redirect back out to the real page
	 * @param OutputPage $output
	 * @param Article $article
	 * @param Title $title
	 * @param User $user
	 * @param WebRequest $request
	 * @param MediaWiki $wiki
	 * @return bool
	 */
	public static function onMediaWikiPerformAction(
		$output,
		$article,
		$title,
		$user,
		$request,
		$wiki
	) {
		if ( $wiki->getAction() !== 'edit' && $request->getText( 'veaction' ) !== 'edit' ) {
			// not our problem
			return true;
		}
		if ( $request->getText( 'prefix', '' ) === '' ) {
			// Fine
			return true;
		}

		$params = $request->getValues();
		$title = $params['prefix'];
		if ( isset( $params['title'] ) ) {
			$title .= $params['title'];
		}
		unset( $params['prefix'] );
		$params['title'] = $title;

		global $wgScript;
		$output->redirect( wfAppendQuery( $wgScript, $params ), '301' );
		return false;
	}
}
