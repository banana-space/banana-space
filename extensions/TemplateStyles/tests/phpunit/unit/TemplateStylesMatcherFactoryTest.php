<?php

use Wikimedia\CSS\Objects\ComponentValueList;
use Wikimedia\CSS\Objects\Token;

/**
 * @group TemplateStyles
 * @covers TemplateStylesMatcherFactory
 */
class TemplateStylesMatcherFactoryTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideUrls
	 * @param string $type
	 * @param string $url
	 * @param bool $expect
	 */
	public function testUrls( $type, $url, $expect ) {
		$factory = new TemplateStylesMatcherFactory( [
			'test1' => [
				'<^http://example\.com/test1/>',
			],
			'test2' => [
				'<^http://example\.com/test2/A/>',
				'<^http://example\.com/test2/B/>',
			],
			'anything' => [
				'<.>',
			],
		] );

		$list = new ComponentValueList( [
			new Token( Token::T_STRING, $url )
		] );
		$this->assertSame( $expect, (bool)$factory->urlstring( $type )->matchAgainst( $list ) );

		$list = new ComponentValueList( [
			new Token( Token::T_URL, $url )
		] );
		$this->assertSame( $expect, (bool)$factory->url( $type )->matchAgainst( $list ) );
	}

	public static function provideUrls() {
		return [
			[ 'test1', 'http://example.com/test1/foobar', true ],
			[ 'test2', 'http://example.com/test1/foobar', false ],
			[ 'test2', 'http://example.com/test2/A/foobar', true ],
			[ 'test2', 'http://example.com/test2/B/foobar', true ],
			[ 'test2', 'http://example.com/test2/C/foobar', false ],
			[ 'test3', 'http://example.com/test3/foobar', false ],
			[ 'test1', 'http://example.com/test1/../../etc/password', false ],
			[ 'test1', 'http://example.com/test1/..%2F..%2Fetc%2Fpassword', false ],
			[ 'test1', 'http://example.com/test1/etc\\password', false ],
			[ 'test1', 'http://example.com/test%31/foobar', true ],
			[ 'test1', 'http://example.com/test1/x=/%2E/foobar', false ],
			[ 'test1', 'http://example.com/test1/?x=/%2E/foobar', true ],
			[ 'test1', 'http://example.com/test1/%3Fx=/%2E/foobar', false ],
			[ 'test1', 'http://example.com/test1/#x=/%2E/foobar', true ],
			[ 'test1', 'http://example.com/test1/%23x=/%2E/foobar', false ],
			[ 'anything', 'totally bogus', true ],
			[ 'anything', '/dotdot/../still/fails/though', false ],
			[ 'anything', '../still/fails/though', false ],
			[ 'anything', 'still/fails/..', false ],
			[ 'anything', '..', false ],
		];
	}

}
