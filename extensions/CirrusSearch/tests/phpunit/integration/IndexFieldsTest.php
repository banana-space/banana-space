<?php

namespace CirrusSearch;

use MediaWiki\MediaWikiServices;
use SearchIndexField;

/**
 * @group CirrusSearch
 * FIXME: what is this class actually testing? Can't cover interfaces.
 * @coversNothing
 */
class IndexFieldsTest extends CirrusIntegrationTestCase {

	public function getTypes() {
		return [
			[ SearchIndexField::INDEX_TYPE_TEXT, 'text', 'CirrusSearch\\Search\\TextIndexField' ],
			[ SearchIndexField::INDEX_TYPE_KEYWORD, 'text', 'CirrusSearch\\Search\\KeywordIndexField' ],
			[ SearchIndexField::INDEX_TYPE_INTEGER, 'long', 'CirrusSearch\\Search\\IntegerIndexField' ],
			[ SearchIndexField::INDEX_TYPE_NUMBER, 'double', 'CirrusSearch\\Search\\NumberIndexField' ],
			[ SearchIndexField::INDEX_TYPE_DATETIME, 'date', 'CirrusSearch\\Search\\DatetimeIndexField' ],
			[ SearchIndexField::INDEX_TYPE_NESTED, 'nested', 'CirrusSearch\\Search\\NestedIndexField' ],
			[ SearchIndexField::INDEX_TYPE_BOOL, 'boolean', 'CirrusSearch\\Search\\BooleanIndexField' ],
		];
	}

	/**
	 * @dataProvider getTypes
	 * @param int    $type Field type
	 * @param string $typeName Internal type name
	 * @param string $klass Class name
	 */
	public function testFieldTypes( $type, $typeName, $klass ) {
		$config =
			MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'CirrusSearch' );
		$engine = new CirrusSearch();
		/**
		 * @var \CirrusSearch\Search\CirrusIndexField $idxField
		 */
		$idxField = new $klass( "test$typeName", $type, $config );
		$map = $idxField->getMapping( $engine );
		$this->assertEquals( $typeName, $map['type'] );
		$this->assertEquals( $type, $idxField->getIndexType() );
		$this->assertEquals( "test$typeName", $idxField->getName() );
	}

	/**
	 * @dataProvider getTypes
	 * @param int    $type Field type
	 * @param string $typeName Internal type name
	 * @param string $klass Class name
	 */
	public function testFieldEngine( $type, $typeName, $klass ) {
		$engine = new CirrusSearch();
		$field = $engine->makeSearchFieldMapping( "test$typeName", $type );
		$this->assertInstanceOf( $klass, $field );
		$this->assertEquals( $type, $field->getIndexType() );
		$this->assertEquals( "test$typeName", $field->getName() );
	}
}
