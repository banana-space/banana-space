<?php
/**
 * Hooks for TeXParser
 *
 * @file
 * @ingroup Extensions
 */

class TeXParserHooks {

	public static function parserFunctionEcho( $parser, $value ) {
		return '<pre>Echo Function: ' . htmlspecialchars( $value ) . '</pre>';
	}


	public static function onParserFirstCallInit( &$parser ) {
		// Add the following to a wiki page to see how it works:
		// {{#echo: hello }}
		$parser->setFunctionHook( 'echo', 'TeXParserHooks::parserFunctionEcho' );

		return true;
	}

	public static function onParserBeforeInternalParse( &$parser, &$text, &$stripState ){
		if(!$parser->getOptions()->getInterfaceMessage())
		{
			$text='Bon!';
			return false;
		}
		return true;
	}


}
