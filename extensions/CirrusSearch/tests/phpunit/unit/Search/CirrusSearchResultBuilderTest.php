<?php

namespace CirrusSearch\Search;

use CirrusSearch\CirrusTestCase;
use Title;

/**
 * @covers \CirrusSearch\Search\CirrusSearchResultBuilder
 * @covers \CirrusSearch\Search\ArrayCirrusSearchResult
 */
class CirrusSearchResultBuilderTest extends CirrusTestCase {
	/**
	 * @var CirrusSearchResultBuilder
	 */
	private $builder;

	public function testMandatoryFields() {
		$builder = new CirrusSearchResultBuilder( Title::makeTitle( NS_MAIN, 'Main Page' ), 'foo|234' );
		$this->assertEquals( Title::makeTitle( NS_MAIN, 'Main Page' ), $builder->build()->getTitle() );
		$this->assertEquals( 'foo|234', $builder->build()->getDocId() );

		$interwikiTitle = Title::makeTitle( NS_CATEGORY, 'MyTitle', '', 'iw' );
		$builder = new CirrusSearchResultBuilder( $interwikiTitle, '1' );
		$this->assertEquals( 'iw', $builder->build()->getInterwikiPrefix() );
	}

	public function provideTest() {
		$now = new \MWTimestamp();
		return [
			'score' => [ 'score', 2.3, 0.0 ],
			'explanation' => [ 'explanation', [ 'some' => 'array' ], null ],
			'textSnippet' => [ 'textSnippet', 'some text', '' ],
			'titleSnippet' => [ 'titleSnippet', 'some text', '' ],
			'redirectSnippet' => [ 'redirectSnippet', 'some text', '' ],
			'sectionSnippet' => [ 'sectionSnippet', 'some text', '' ],
			'categorySnippet' => [ 'categorySnippet', 'some text', '' ],
			'redirectTitle' => [ 'redirectTitle', Title::makeTitle( NS_MAIN, 'Main Page' ), null ],
			'sectionTitle' => [ 'sectionTitle', Title::makeTitle( NS_MAIN, 'Main Page' ), null ],
			'timestamp' => [ 'timestamp', $now, '', $now->getTimestamp( TS_MW ) ],
			'wordCount' => [ 'wordCount', 4, 0 ],
			'byteSize' => [ 'byteSize', 324, 0 ],
			'interwikiNamespaceText' => [ 'interwikiNamespaceText', 'Utilisateur', '' ],
			'fileMatch' => [ 'fileMatch', true, false ],
		];
	}

	/**
	 * @dataProvider provideTest
	 * @param string $field
	 * @param mixed $value
	 * @param mixed $expectedDefaultValue
	 * @param mixed|null $expectedValue
	 */
	public function test( $field, $value, $expectedDefaultValue, $expectedValue = null ) {
		$expectedValue = $expectedValue ?: $value;
		$getter = $this->getter( $field, gettype( $value ) );
		$setter = $this->setter( $field );

		$this->builder = $this->builder !== null ?
			$this->builder->reset( Title::makeTitle( NS_MAIN, 'Main Page' ), '1' ) :
			new CirrusSearchResultBuilder( Title::makeTitle( NS_MAIN, 'Main Page' ), '1' );

		$res = $this->builder->build();
		$this->assertEquals( $expectedDefaultValue, $getter( $res ) );

		$setter( $value );
		$res = $this->builder->build();
		$this->assertEquals( $expectedValue, $getter( $res ) );
	}

	public function setter( $field ) {
		return function ( $v ) use ( $field ) {
			return call_user_func( [ $this->builder, $field ], $v );
		};
	}

	private function getter( $field, $type ) {
		return function ( CirrusSearchResult $result ) use ( $field, $type ) {
			return call_user_func( [ $result, ( $type === 'boolean' ? 'is' : 'get' ) . ucfirst( $field ) ] );
		};
	}
}
