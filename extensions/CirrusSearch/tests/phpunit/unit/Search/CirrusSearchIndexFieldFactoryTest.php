<?php

namespace CirrusSearch\Search;

use CirrusSearch\CirrusTestCase;
use CirrusSearch\HashSearchConfig;
use CirrusSearch\SearchConfig;
use SearchIndexField;

/**
 * @group CirrusSearch
 * @covers \CirrusSearch\Search\CirrusSearchIndexFieldFactory
 */
class CirrusSearchIndexFieldFactoryTest extends CirrusTestCase {

	public function provideTestFactory() {
		return [
			'bool' => [ 'foo', \SearchIndexField::INDEX_TYPE_BOOL, BooleanIndexField::class ],
			'datetime' => [ 'foo', \SearchIndexField::INDEX_TYPE_DATETIME, DatetimeIndexField::class ],
			'integer' => [ 'foo', \SearchIndexField::INDEX_TYPE_INTEGER, IntegerIndexField::class ],
			'keyword' => [ 'foo', \SearchIndexField::INDEX_TYPE_KEYWORD, KeywordIndexField::class ],
			'nested' => [ 'foo', \SearchIndexField::INDEX_TYPE_NESTED, NestedIndexField::class ],
			'number' => [ 'foo', \SearchIndexField::INDEX_TYPE_NUMBER, NumberIndexField::class ],
			'short_text' => [ 'foo', \SearchIndexField::INDEX_TYPE_SHORT_TEXT, ShortTextIndexField::class ],
			'text' => [ 'foo', \SearchIndexField::INDEX_TYPE_TEXT, TextIndexField::class ],
			'opening_text' => [ 'opening_text', \SearchIndexField::INDEX_TYPE_TEXT, OpeningTextIndexField::class ],
			'unknown' => [ 'foo', 'unknown_type', \NullIndexField::class ],
		];
	}

	/**
	 * @dataProvider provideTestFactory
	 * @param string $name
	 * @param string $type
	 * @param string $expectedClass
	 * @throws \ConfigException
	 * @covers \CirrusSearch\CirrusSearch::makeSearchFieldMapping()
	 * @covers \CirrusSearch\Search\CirrusSearchIndexFieldFactory::makeSearchFieldMapping()
	 */
	public function testFactory( $name, $type, $expectedClass ) {
		$factory = new CirrusSearchIndexFieldFactory( new HashSearchConfig( [] ) );
		$this->assertInstanceOf( $expectedClass, $factory->makeSearchFieldMapping( $name, $type ) );
		$cirrus = $this->newEngine();
		$this->assertInstanceOf( $expectedClass, $cirrus->makeSearchFieldMapping( $name, $type ) );
	}

	public function testNewStringField() {
		$searchConfig = $this->getSearchConfig();

		$factory = new CirrusSearchIndexFieldFactory( $searchConfig );
		$stringField = $factory->newStringField( 'title' );

		$this->assertInstanceOf( TextIndexField::class, $stringField );
		$this->assertSame( 'title', $stringField->getName(), 'field name is `title`' );
	}

	public function testNewLongField() {
		$searchConfig = $this->getSearchConfig();

		$factory = new CirrusSearchIndexFieldFactory( $searchConfig );
		$longField = $factory->newLongField( 'count' );

		$this->assertInstanceOf( IntegerIndexField::class, $longField );
		$this->assertSame( 'count', $longField->getName(), 'field name is `count`' );
	}

	public function testNewKeywordField() {
		$searchConfig = $this->getSearchConfig();

		$factory = new CirrusSearchIndexFieldFactory( $searchConfig );
		$keywordField = $factory->newKeywordField( 'id' );

		$this->assertInstanceOf( KeywordIndexField::class, $keywordField );
		$this->assertSame( 'id', $keywordField->getName(), 'field name is `id`' );
	}

	public function testTemplateIsKeywordWithCaseSensitiveSubfield() {
		$searchConfig = $this->getSearchConfig();

		$factory = new CirrusSearchIndexFieldFactory( $searchConfig );
		$keywordField = $factory->makeSearchFieldMapping( 'template', SearchIndexField::INDEX_TYPE_KEYWORD );

		$this->assertInstanceOf( KeywordIndexField::class, $keywordField );
		$this->assertSame( 'template', $keywordField->getName(), 'field name is `template`' );
		$mapping = $keywordField->getMapping( $this->newEngine() );
		$this->assertArrayHasKey( 'fields', $mapping );
		$this->assertArrayHasKey( 'keyword', $mapping['fields'] );
		$this->assertNotSame( 0, $keywordField->checkFlag( SearchIndexField::FLAG_CASEFOLD ) );
	}

	private function getSearchConfig(): SearchConfig {
		return new HashSearchConfig( [] );
	}
}
