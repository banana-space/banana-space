<?php

namespace CirrusSearch\Job;

use CirrusSearch\CirrusTestCase;

/**
 * @covers \CirrusSearch\Job\ElasticaDocumentsJsonSerde
 */
class ElasticaDocumentsJsonSerdeTest extends CirrusTestCase {
	public static function documentsProvider() {
		return [
			'empty docs' => [ [] ],
			'simple' => [
				[ new \Elastica\Document( 'abc', [ 'title' => 'Foobar', ] ) ],
			],
			'multiple documents' => [
				[
					new \Elastica\Document( 'abc', [ 'title' => 'Foobar', ] ),
					new \Elastica\Document( 'zyx', [ 'title' => 'Baz', ] ),
				],
			],
			'complex document' => [
				[
					new \Elastica\Document( '5432', [
						'title' => 'Train',
						'redirect' => [
							[ 'namespace' => 0, 'title' => 'Thomas' ],
						],
						'heading' => [ 'engine', 'models' ],
						'incoming_links' => 42,
						'coordinates' => [],
					] ),
				],
			],
		];
	}

	/**
	 * @dataProvider documentsProvider
	 */
	public function testRoundTrip( $docs ) {
		$impl = new ElasticaDocumentsJsonSerde();
		$serialized = json_encode( $impl->serialize( $docs ) );
		$roundtrip = $impl->deserialize( json_decode( $serialized, true ) );
		$this->assertEquals( $docs, $roundtrip );
	}
}
