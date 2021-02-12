<?php
/**
 * @file
 * @license GPL-2.0-or-later
 */

use Wikimedia\CSS\Grammar\Alternative;
use Wikimedia\CSS\Grammar\Juxtaposition;
use Wikimedia\CSS\Grammar\MatcherFactory;
use Wikimedia\CSS\Grammar\Quantifier;
use Wikimedia\CSS\Grammar\TokenMatcher;
use Wikimedia\CSS\Objects\Token;
use Wikimedia\CSS\Sanitizer\FontFaceAtRuleSanitizer;

/**
 * Extend the standard `@font-face` matcher to require a prefix on families.
 */
class TemplateStylesFontFaceAtRuleSanitizer extends FontFaceAtRuleSanitizer {

	/**
	 * @param MatcherFactory $matcherFactory
	 */
	public function __construct( MatcherFactory $matcherFactory ) {
		parent::__construct( $matcherFactory );

		// Only allow the font-family if it begins with "TemplateStyles"
		$this->propertySanitizer->setKnownProperties( [
			'font-family' => new Alternative( [
				new TokenMatcher( Token::T_STRING, function ( Token $t ) {
					return substr( $t->value(), 0, 14 ) === 'TemplateStyles';
				} ),
				new Juxtaposition( [
					new TokenMatcher( Token::T_IDENT, function ( Token $t ) {
						return substr( $t->value(), 0, 14 ) === 'TemplateStyles';
					} ),
					Quantifier::star( $matcherFactory->ident() ),
				] ),
			] ),
		] + $this->propertySanitizer->getKnownProperties() );
	}
}
