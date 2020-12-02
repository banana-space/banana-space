<?php

namespace Cite\Hooks;

use Cite\Cite;
use Parser;
use StripState;

/**
 * @license GPL-2.0-or-later
 */
class CiteParserHooks {

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserFirstCallInit
	 *
	 * @param Parser $parser
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		CiteParserTagHooks::register( $parser );
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserClearState
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserCloned
	 *
	 * @param Parser $parser
	 */
	public static function onParserClearStateOrCloned( Parser $parser ) {
		unset( $parser->extCite );
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserAfterParse
	 *
	 * @param Parser $parser
	 * @param string &$text
	 * @param StripState $stripState
	 */
	public static function onParserAfterParse( Parser $parser, &$text, $stripState ) {
		if ( isset( $parser->extCite ) ) {
			/** @var Cite $cite */
			$cite = $parser->extCite;
			$text .= $cite->checkRefsNoReferences( $parser, $parser->getOptions()->getIsSectionPreview() );
		}
	}

}
