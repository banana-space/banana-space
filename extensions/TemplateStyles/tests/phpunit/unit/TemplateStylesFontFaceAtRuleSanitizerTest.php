<?php

use Wikimedia\CSS\Grammar\MatcherFactory;
use Wikimedia\CSS\Parser\Parser;
use Wikimedia\CSS\Util;

/**
 * @group TemplateStyles
 * @covers TemplateStylesFontFaceAtRuleSanitizer
 */
class TemplateStylesFontFaceAtRuleSanitizerTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideRules
	 * @param string $input
	 * @param bool $handled
	 * @param string|null $output
	 * @param string|null $minified
	 * @param array $errors
	 * @param array $options
	 */
	public function testRules( $input, $handled, $output, $minified, $errors = [], $options = [] ) {
		$san = new TemplateStylesFontFaceAtRuleSanitizer( new MatcherFactory() );
		$rule = Parser::newFromString( $input )->parseRule();
		$oldRule = clone $rule;

		$this->assertSame( $handled, $san->handlesRule( $rule ) );
		$ret = $san->sanitize( $rule );
		$this->assertSame( $errors, $san->getSanitizationErrors() );
		if ( $output === null ) {
			$this->assertNull( $ret );
		} else {
			$this->assertNotNull( $ret );
			$this->assertSame( $output, (string)$ret );
			$this->assertSame( $minified, Util::stringify( $ret, [ 'minify' => true ] ) );
		}

		$this->assertEquals( (string)$oldRule, (string)$rule, 'Rule wasn\'t overwritten' );
	}

	public static function provideRules() {
		return [
			'non-prefixed font family as string' => [
				'@font-face {
					font-family: "foo bar";
				}',
				true,
				'@font-face {}',
				'@font-face{}',
				[
					[ 'bad-value-for-property', 2, 19, 'font-family' ],
				]
			],
			'non-prefixed font family as idents' => [
				'@font-face {
					font-family: foo bar;
				}',
				true,
				'@font-face {}',
				'@font-face{}',
				[
					[ 'bad-value-for-property', 2, 19, 'font-family' ],
				]
			],
			'prefixed font family as string' => [
				'@font-face {
					font-family: "TemplateStyles foo bar";
				}',
				true,
				'@font-face { font-family: "TemplateStyles foo bar"; }',
				'@font-face{font-family:"TemplateStyles foo bar"}',
			],
			'non-prefixed font family as idents (1)' => [
				'@font-face {
					font-family: TemplateStyles foo bar;
				}',
				true,
				'@font-face { font-family: TemplateStyles foo bar; }',
				'@font-face{font-family:TemplateStyles foo bar}',
			],
			'non-prefixed font family as idents (2)' => [
				'@font-face {
					font-family: TemplateStylesFoo bar;
				}',
				true,
				'@font-face { font-family: TemplateStylesFoo bar; }',
				'@font-face{font-family:TemplateStylesFoo bar}',
			],
		];
	}
}
