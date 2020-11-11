<?php

class ParserFunctionsHooks {

	/**
	 * Enable string functions, when running Wikimedia Jenkins unit tests.
	 *
	 * Running Jenkins unit tests without setting $wgPFEnableStringFunctions = true;
	 * will cause all the parser tests for string functions to be skipped.
	 */
	public static function onRegistration() {
		if ( isset( $GLOBALS['wgWikimediaJenkinsCI'] ) && $GLOBALS['wgWikimediaJenkinsCI'] === true ) {
			$GLOBALS['wgPFEnableStringFunctions'] = true;
		}
	}

	/**
	 * @param Parser $parser
	 * @return bool
	 */
	public static function onParserFirstCallInit( $parser ) {
		global $wgPFEnableStringFunctions;

		// These functions accept DOM-style arguments
		$parser->setFunctionHook( 'if', 'ExtParserFunctions::ifObj', Parser::SFH_OBJECT_ARGS );
		$parser->setFunctionHook( 'ifeq', 'ExtParserFunctions::ifeqObj', Parser::SFH_OBJECT_ARGS );
		$parser->setFunctionHook( 'switch', 'ExtParserFunctions::switchObj', Parser::SFH_OBJECT_ARGS );
		$parser->setFunctionHook( 'ifexist', 'ExtParserFunctions::ifexistObj', Parser::SFH_OBJECT_ARGS );
		$parser->setFunctionHook( 'ifexpr', 'ExtParserFunctions::ifexprObj', Parser::SFH_OBJECT_ARGS );
		$parser->setFunctionHook( 'iferror', 'ExtParserFunctions::iferrorObj', Parser::SFH_OBJECT_ARGS );
		$parser->setFunctionHook( 'time', 'ExtParserFunctions::timeObj', Parser::SFH_OBJECT_ARGS );
		$parser->setFunctionHook( 'timel', 'ExtParserFunctions::localTimeObj', Parser::SFH_OBJECT_ARGS );

		$parser->setFunctionHook( 'expr', 'ExtParserFunctions::expr' );
		$parser->setFunctionHook( 'rel2abs', 'ExtParserFunctions::rel2abs' );
		$parser->setFunctionHook( 'titleparts', 'ExtParserFunctions::titleparts' );

		// String Functions
		if ( $wgPFEnableStringFunctions ) {
			$parser->setFunctionHook( 'len',       'ExtParserFunctions::runLen' );
			$parser->setFunctionHook( 'pos',       'ExtParserFunctions::runPos' );
			$parser->setFunctionHook( 'rpos',      'ExtParserFunctions::runRPos' );
			$parser->setFunctionHook( 'sub',       'ExtParserFunctions::runSub' );
			$parser->setFunctionHook( 'count',     'ExtParserFunctions::runCount' );
			$parser->setFunctionHook( 'replace',   'ExtParserFunctions::runReplace' );
			$parser->setFunctionHook( 'explode',   'ExtParserFunctions::runExplode' );
			$parser->setFunctionHook( 'urldecode', 'ExtParserFunctions::runUrlDecode' );
		}

		return true;
	}

	public static function onScribuntoExternalLibraries( $engine, array &$extraLibraries ) {
		if ( $engine === 'lua' ) {
			$extraLibraries['mw.ext.ParserFunctions'] = 'Scribunto_LuaParserFunctionsLibrary';
		}
		return true;
	}
}
