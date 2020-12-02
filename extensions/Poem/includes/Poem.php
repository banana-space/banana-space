<?php

/**
 * This class handles formatting poems in WikiText, specifically anything within
 * <poem></poem> tags.
 *
 * @license CC0-1.0
 * @author Nikola Smolenski <smolensk@eunet.yu>
 */
class Poem {
	/**
	 * Bind the renderPoem function to the <poem> tag
	 * @param Parser $parser
	 */
	public static function init( Parser $parser ) {
		$parser->setHook( 'poem', [ self::class, 'renderPoem' ] );
	}

	/**
	 * Parse the text into proper poem format
	 * @param string|null $in The text inside the poem tag
	 * @param string[] $param
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return string
	 */
	public static function renderPoem( $in, array $param, Parser $parser, PPFrame $frame ) {
		// using newlines in the text will cause the parser to add <p> tags,
		// which may not be desired in some cases
		$newline = isset( $param['compact'] ) ? '' : "\n";

		$tag = $parser->insertStripItem( "<br />" );

		// replace colons with indented spans
		$text = preg_replace_callback( '/^(:+)(.+)$/m', [ self::class, 'indentVerse' ], $in );

		// replace newlines with <br /> tags unless they are at the beginning or end
		// of the poem, or would directly follow exactly 4 dashes. See Parser::internalParse() for
		// the exact syntax for horizontal rules.
		$text = preg_replace(
			[ '/^\n/', '/\n$/D', '/(?<!^----)\n/m' ],
			[ "", "", "$tag\n" ],
			$text
		);

		// replace spaces at the beginning of a line with non-breaking spaces
		$text = preg_replace_callback( '/^( +)/m', [ self::class, 'replaceSpaces' ], $text );

		$text = $parser->recursiveTagParse( $text, $frame );

		// Because of limitations of the regular expression above, horizontal rules with more than 4
		// dashes still need special handling.
		$text = str_replace( '<hr />' . $tag, '<hr />', $text );

		$attribs = Sanitizer::validateTagAttributes( $param, 'div' );

		// Wrap output in a <div> with "poem" class.
		if ( isset( $attribs['class'] ) ) {
			$attribs['class'] = 'poem ' . $attribs['class'];
		} else {
			$attribs['class'] = 'poem';
		}

		return Html::rawElement( 'div', $attribs, $newline . trim( $text ) . $newline );
	}

	/**
	 * Callback for preg_replace_callback() that replaces spaces with non-breaking spaces
	 * @param string[] $m Matches from the regular expression
	 *   - $m[1] consists of 1 or more spaces
	 * @return string
	 */
	protected static function replaceSpaces( array $m ) {
		return str_replace( ' ', '&#160;', $m[1] );
	}

	/**
	 * Callback for preg_replace_callback() that wraps content in an indented span
	 * @param string[] $m Matches from the regular expression
	 *   - $m[1] consists of 1 or more colons
	 *   - $m[2] consists of the text after the colons
	 * @return string
	 */
	protected static function indentVerse( array $m ) {
		$attribs = [
			'class' => 'mw-poem-indented',
			'style' => 'display: inline-block; margin-left: ' . strlen( $m[1] ) . 'em;'
		];
		// @todo Should this really be raw?
		return Html::rawElement( 'span', $attribs, $m[2] );
	}
}
