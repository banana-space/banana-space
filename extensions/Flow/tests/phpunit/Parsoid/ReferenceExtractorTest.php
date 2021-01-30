<?php

namespace Flow\Tests\Parsoid;

use Flow\Container;
use Flow\Conversion\Utils;
use Flow\Exception\WikitextException;
use Flow\Model\UUID;
use Flow\Parsoid\ReferenceFactory;
use Flow\Tests\FlowTestCase;
use ReflectionMethod;
use Title;

/**
 * @group Database
 * @group Flow
 */
class ReferenceExtractorTestCase extends FlowTestCase {
	protected function setUp() : void {
		parent::setUp();

		// Check for Parsoid
		try {
			Utils::convert( 'html', 'wikitext', 'Foo', Title::newFromText( 'UTPage' ) );
		} catch ( WikitextException $excep ) {
			$this->markTestSkipped( 'Parsoid not enabled' );
		}
	}

	public static function referenceExtractorProvider() {
		return [
			[
				'Normal link',
				// source wiki text
				'[[My page]]',
				// expected factory method
				\Flow\Model\WikiReference::class,
				// expected type
				'link',
				// expected target
				'title:My_page',
			],
			[
				'Link with URL encoding issues',
				// source wiki text
				'[[User talk:Werdna?]]',
				// expected factory method
				\Flow\Model\WikiReference::class,
				// expected type
				'link',
				// expected target
				'title:User_talk:Werdna?',
			],
			[
				'Subpage link',
				// source wiki text
				'[[/Subpage]]',
				// expected factory method
				\Flow\Model\WikiReference::class,
				// expected type
				'link',
				// expected target
				'title:Talk:UTPage/Subpage',
				// ???
				'Talk:UTPage',
			],
			[
				'External link',
				// source wiki text
				'[http://www.google.com Google]',
				// expected factory method
				\Flow\Model\URLReference::class,
				// expected type
				'link',
				// expected target
				'url:http://www.google.com',
			],
			[
				'File',
				// source wiki text
				'[[File:Image.png]]',
				// expected factory method
				\Flow\Model\WikiReference::class,
				// expected type
				'file',
				// expected target
				'title:File:Image.png',
			],
			[
				'File with parameters',
				// source wiki text
				'[[File:Image.png|25px]]',
				// expected factory method
				\Flow\Model\WikiReference::class,
				// expected type
				'file',
				// expected target
				'title:File:Image.png',
			],
			[
				'File with encoding issues',
				// source wiki text
				'[[File:Image?.png]]',
				// expected class
				\Flow\Model\WikiReference::class,
				// expected type
				'file',
				// expected target
				'title:File:Image?.png',
			],
			[
				'Template',
				// source wiki text
				'{{Foo}}',
				// expected factory method
				\Flow\Model\WikiReference::class,
				// expected type
				'template',
				// expected target
				'title:Template:Foo',
			],

			[
				'Non-existent File',
				// source wiki text
				'[[File:Some/Files/Really/Should_Not_Ex/ist.png]]',
				// expected factory method
				\Flow\Model\WikiReference::class,
				// expected type
				'file',
				// expected target
				'title:File:Some/Files/Really/Should_Not_Ex/ist.png',
			]
		];
	}

	/**
	 * @dataProvider referenceExtractorProvider
	 */
	public function testReferenceExtractor(
		$description,
		$wikitext,
		$expectedClass,
		$expectedType,
		$expectedTarget,
		$page = 'UTPage'
	) {
		$referenceExtractor = Container::get( 'reference.extractor' );

		$workflow = $this->createMock( \Flow\Model\Workflow::class );
		$workflow->expects( $this->any() )
			->method( 'getId' )
			->will( $this->returnValue( UUID::create() ) );
		$workflow->expects( $this->any() )
			->method( 'getArticleTitle' )
			->will( $this->returnValue( Title::newMainPage() ) );
		$factory = new ReferenceFactory( $workflow, 'foo', UUID::create() );

		$reflMethod = new ReflectionMethod( $referenceExtractor, 'extractReferences' );
		$reflMethod->setAccessible( true );

		$reflProperty = new \ReflectionProperty( $referenceExtractor, 'extractors' );
		$reflProperty->setAccessible( true );
		$extractors = $reflProperty->getValue( $referenceExtractor );

		$html = Utils::convert( 'wt', 'html', $wikitext, Title::newFromText( $page ) );
		$result = $reflMethod->invoke(
			$referenceExtractor,
			$factory,
			$extractors['post'],
			$html
		);
		$this->assertCount( 1, $result, $html );

		$result = reset( $result );
		$this->assertInstanceOf( $expectedClass, $result, $description );
		$this->assertEquals( $expectedType, $result->getType(), $description );
		$this->assertEquals( $expectedTarget, $result->getTargetIdentifier(), $description );
	}
}
