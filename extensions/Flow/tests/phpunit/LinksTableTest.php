<?php

namespace Flow\Tests;

use Flow\Container;
use Flow\Conversion\Utils;
use Flow\Data\Listener\ReferenceRecorder;
use Flow\Data\ManagerGroup;
use Flow\Exception\WikitextException;
use Flow\LinksTableUpdater;
use Flow\Model\AbstractRevision;
use Flow\Model\PostRevision;
use Flow\Model\Reference;
use Flow\Model\UUID;
use Flow\Model\Workflow;
use Flow\Parsoid\ReferenceFactory;
use Title;

/**
 * @covers \Flow\Data\Listener\ReferenceRecorder
 * @covers \Flow\Model\AbstractRevision
 * @covers \Flow\Model\PostRevision
 * @covers \Flow\Parsoid\ReferenceFactory
 *
 * @group Flow
 * @group Database
 */
class LinksTableTest extends PostRevisionTestCase {
	/**
	 * @var array
	 */
	protected $tablesUsed = [
		'flow_ext_ref',
		'flow_revision',
		'flow_topic_list',
		'flow_tree_node',
		'flow_tree_revision',
		'flow_wiki_ref',
		'flow_workflow',
		'page',
		'revision',
		'ip_changes',
		'text',
	];

	/**
	 * @var ManagerGroup
	 */
	protected $storage;

	/**
	 * @var ReferenceRecorder
	 */
	protected $recorder;

	/**
	 * @var LinksTableUpdater
	 */
	protected $updater;

	/**
	 * @var Workflow
	 */
	protected $workflow;

	/**
	 * @var PostRevision
	 */
	protected $revision;

	protected function setUp() : void {
		parent::setUp();

		// create a workflow & revision associated with it
		$this->revision = $this->generateObject();
		$this->workflow = $this->workflows[$this->revision->getCollectionId()->getAlphadecimal()];
		$this->storage = Container::get( 'storage' );
		$this->recorder = Container::get( 'reference.recorder' );
		$this->updater = Container::get( 'reference.updater.links-tables' );

		// Check for Parsoid
		try {
			Utils::convert( 'html', 'wikitext', 'Foo', $this->workflow->getOwnerTitle() );
		} catch ( WikitextException $excep ) {
			$this->markTestSkipped( 'Parsoid not enabled' );
		}

		// These tests don't provide sufficient data to properly run all listeners
		$this->clearExtraLifecycleHandlers();
	}

	/**
	 * Generate a reply to $this->revision (which is a topic title)
	 *
	 * @param array $overrides
	 * @return PostRevision
	 * @throws \Flow\Exception\FlowException
	 */
	protected function generatePost( array $overrides ) {
		$uuid = UUID::create();
		return $this->generateObject( $overrides + [
			'rev_change_type' => 'reply',

			// generate new post id
			'tree_rev_descendant_id' => $uuid->getBinary(),
			'rev_type_id' => $uuid->getBinary(),

			// make sure it's a reply to $this->revision
			'tree_parent_id' => $this->revision->getPostId(),
		] );
	}

	protected static function getTestTitle() {
		return Title::newFromText( 'UTPage' );
	}

	public static function provideGetReferencesFromRevisionContent() {
		return [
			[
				'[[Foo]]',
				[
					[
						'factoryMethod' => 'createWikiReference',
						'refType' => 'link',
						'value' => 'Foo',
					],
				],
			],
			[
				'[http://www.google.com Foo]',
				[
					[
						'factoryMethod' => 'createUrlReference',
						'refType' => 'link',
						'value' => 'http://www.google.com',
					],
				],
			],
			[
				'[[File:Foo.jpg]]',
				[
					[
						'factoryMethod' => 'createWikiReference',
						'refType' => 'file',
						'value' => 'File:Foo.jpg',
					],
				],
			],
			[
				'{{Foo}}',
				[
					[
						'factoryMethod' => 'createWikiReference',
						'refType' => 'template',
						'value' => 'Template:Foo',
					],
				],
			],
			[
				'{{Foo}} [[Foo]] [[File:Foo.jpg]] {{Foo}} [[Bar]]',
				[
					[
						'factoryMethod' => 'createWikiReference',
						'refType' => 'template',
						'value' => 'Template:Foo',
					],
					[
						'factoryMethod' => 'createWikiReference',
						'refType' => 'link',
						'value' => 'Foo',
					],
					[
						'factoryMethod' => 'createWikiReference',
						'refType' => 'file',
						'value' => 'File:Foo.jpg',
					],
					[
						'factoryMethod' => 'createWikiReference',
						'refType' => 'link',
						'value' => 'Bar',
					],
				],
			]
		];
	}

	/**
	 * @dataProvider provideGetReferencesFromRevisionContent
	 */
	public function testGetReferencesFromRevisionContent( $content, array $expectedReferences ) {
		$content = Utils::convert( 'wikitext', 'html', $content, $this->workflow->getOwnerTitle() );
		$revision = $this->generatePost( [ 'rev_content' => $content ] );

		$expectedReferences = $this->expandReferences( $this->workflow, $revision, $expectedReferences );

		$foundReferences = $this->recorder->getReferencesFromRevisionContent( $this->workflow, $revision );

		$this->assertReferenceListsEqual( $expectedReferences, $foundReferences );
	}

	/**
	 * @dataProvider provideGetReferencesFromRevisionContent
	 */
	public function testGetReferencesAfterRevisionInsert( $content, array $expectedReferences ) {
		$content = Utils::convert( 'wikitext', 'html', $content, $this->workflow->getOwnerTitle() );
		$revision = $this->generatePost( [ 'rev_content' => $content ] );

		// Save to storage to test if ReferenceRecorder listener picks this up
		$this->store( $this->revision );
		$this->store( $revision );

		$expectedReferences = $this->expandReferences( $this->workflow, $revision, $expectedReferences );

		// References will be stored as linked from Topic:<id>
		$title = Title::newFromText( $this->workflow->getId()->getAlphadecimal(), NS_TOPIC );

		// Retrieve references from storage
		$foundReferences = $this->updater->getReferencesForTitle( $title );

		$this->assertReferenceListsEqual( $expectedReferences, $foundReferences );
	}

	public static function provideGetExistingReferences() {
		return [ /* list of test runs */
			[ /* list of arguments */
				[ /* list of references */
					[ /* list of parameters */
						'factoryMethod' => 'createWikiReference',
						'refType' => 'template',
						'value' => 'Template:Foo',
					],
					[
						'factoryMethod' => 'createWikiReference',
						'refType' => 'link',
						'value' => 'Foo',
					],
					[
						'factoryMethod' => 'createWikiReference',
						'refType' => 'file',
						'value' => 'File:Foo.jpg',
					],
					[
						'factoryMethod' => 'createWikiReference',
						'refType' => 'link',
						'value' => 'Bar',
					],
				],
			],
		];
	}

	/**
	 * @dataProvider provideGetExistingReferences
	 */
	public function testGetExistingReferences( array $references ) {
		list( $workflow, $revision, $title ) = $this->getBlandTestObjects();

		$references = $this->expandReferences( $workflow, $revision, $references );

		$this->storage->multiPut( $references );

		$foundReferences = $this->recorder
			->getExistingReferences( $revision->getRevisionType(), $revision->getCollectionId() );

		$this->assertReferenceListsEqual( $references, $foundReferences );
	}

	public static function provideReferenceDiff() {
		$references = self::getSampleReferences();

		return [
			// Just adding a few
			[
				[],
				[
					$references['fooLink'],
					$references['barLink']
				],
				[
					$references['fooLink'],
					$references['barLink'],
				],
				[],
			],
			// Removing one
			[
				[
					$references['fooLink'],
					$references['barLink']
				],
				[
					$references['fooLink'],
				],
				[
				],
				[
					$references['barLink'],
				],
			],
			// Equality robustness
			[
				[
					$references['fooLink'],
				],
				[
					$references['FooLink'],
				],
				[
				],
				[
				],
				[ // test is only valid if Foo and foo are same page
					'wgCapitalLinks' => true,
				]
			],
			// Inequality robustness
			[
				[
					$references['fooLink'],
				],
				[
					$references['barLink'],
				],
				[
					$references['barLink'],
				],
				[
					$references['fooLink'],
				],
			],
		];
	}

	/**
	 * @dataProvider provideReferenceDiff
	 */
	public function testReferenceDiff( array $old, array $new, array $expectedAdded, array $expectedRemoved, array $globals = [] ) {
		if ( $globals ) {
			$this->setMwGlobals( $globals );
		}
		list( $workflow, $revision, $title ) = $this->getBlandTestObjects();

		foreach ( [ 'old', 'new', 'expectedAdded', 'expectedRemoved' ] as $varName ) {
			$$varName = $this->expandReferences( $workflow, $revision, $$varName );
		}

		list( $added, $removed ) = $this->recorder->referencesDifference( $old, $new );

		$this->assertReferenceListsEqual( $added, $expectedAdded );
		$this->assertReferenceListsEqual( $removed, $expectedRemoved );
	}

	public static function provideMutateParserOutput() {
		$references = self::getSampleReferences();

		return [
			[
				[ // references
					$references['fooLink'],
					$references['fooTemplate'],
					$references['googleLink'],
					$references['fooImage'],
				],
				[
					'getLinks' => [
						NS_MAIN => [ 'Foo' => 0, ],
					],
					'getTemplates' => [
						NS_TEMPLATE => [ 'Foo' => 0, ],
					],
					'getImages' => [
						'Foo.jpg' => true,
					],
					'getExternalLinks' => [
						'http://www.google.com' => true,
					],
				],
			],
			[
				[
					$references['subpageLink'],
				],
				[
					'getLinks' => [
						// NS_MAIN is the namespace of static::getTestTitle()
						NS_MAIN => [ static::getTestTitle()->getDBkey() . '/Subpage' => 0, ]
					],
				],
			],
			[
				[
					$references['ExtLinkWithInvalidUTF8Sequence']
				],
				[
					'getExternalLinks' => [
						'http://www.google.com/%E8' => true,
					],
				]
			],
		];
	}

	/**
	 * @dataProvider provideMutateParserOutput
	 */
	public function testMutateParserOutput( array $references, array $expectedItems ) {
		list( $workflow, $revision, $title ) = $this->getBlandTestObjects();

		/*
		 * Because the data provider is static, we can't access $this->workflow
		 * in there. Once of the things being tested is a subpage link.
		 * Thus, we would have to provide the correct namespace & title for
		 * $this->workflow->getArticleTitle(), under which the subpage will be
		 * created.
		 * Let's work around this by overwriting $workflow->title to a "known"
		 * value, so that we can hardcode that into the expected return value in
		 * the static provider.
		 */
		$title = static::getTestTitle();
		$reflectionWorkflow = new \ReflectionObject( $workflow );
		$reflectionProperty = $reflectionWorkflow->getProperty( 'title' );
		$reflectionProperty->setAccessible( true );
		$reflectionProperty->setValue( $workflow, $title );

		$references = $this->expandReferences( $workflow, $revision, $references );
		$parserOutput = new \ParserOutput;

		// Clear the LinksUpdate to allow clean testing
		foreach ( array_keys( $expectedItems ) as $fieldName ) {
			$parserOutput->$fieldName = [];
		}

		$this->updater->mutateParserOutput( $title, $parserOutput, $references );

		foreach ( $expectedItems as $method => $content ) {
			$this->assertEquals( $content, $parserOutput->$method(), $method );
		}
	}

	protected function getBlandTestObjects() {
		return [
			/* workflow = */ $this->workflow,
			/* revision = */ $this->revision,
			/* title = */ $this->workflow->getArticleTitle(),
		];
	}

	/**
	 * @param Workflow $workflow
	 * @param AbstractRevision $revision
	 * @param array[] $references
	 *
	 * @return Reference[]
	 */
	protected function expandReferences( Workflow $workflow, AbstractRevision $revision, array $references ) {
		$referenceObjs = [];
		$factory = new ReferenceFactory( $workflow, $revision->getRevisionType(), $revision->getCollectionId() );

		foreach ( $references as $ref ) {
			$referenceObjs[] = $factory->{$ref['factoryMethod']}( $ref['refType'], $ref['value'] );
		}

		return $referenceObjs;
	}

	protected static function getSampleReferences() {
		return [
			'fooLink' => [
				'factoryMethod' => 'createWikiReference',
				'refType' => 'link',
				'value' => 'Foo',
			],
			'subpageLink' => [
				'factoryMethod' => 'createWikiReference',
				'refType' => 'link',
				'value' => '/Subpage',
			],
			'FooLink' => [
				'factoryMethod' => 'createWikiReference',
				'refType' => 'link',
				'value' => 'foo',
			],
			'barLink' => [
				'factoryMethod' => 'createWikiReference',
				'refType' => 'link',
				'value' => 'Bar',
			],
			'fooTemplate' => [
				'factoryMethod' => 'createWikiReference',
				'refType' => 'template',
				'value' => 'Template:Foo',
			],
			'googleLink' => [
				'factoryMethod' => 'createUrlReference',
				'refType' => 'link',
				'value' => 'http://www.google.com'
			],
			'ExtLinkWithInvalidUTF8Sequence' => [
				'factoryMethod' => 'createUrlReference',
				'refType' => 'link',
				'value' => 'http://www.google.com/%E8'
			],
			'fooImage' => [
				'factoryMethod' => 'createWikiReference',
				'refType' => 'file',
				'value' => 'File:Foo.jpg',
			],
			'foreignFoo' => [
				'factoryMethod' => 'createWikiReference',
				'refType' => 'link',
				'value' => 'Foo',
			],
		];
	}

	/**
	 * @param Reference[] $input
	 *
	 * @return string[]
	 */
	protected function flattenReferenceList( array $input ) {
		$list = [];

		foreach ( $input as $reference ) {
			$list[$reference->getUniqueIdentifier()] = $reference;
		}

		ksort( $list );
		return array_keys( $list );
	}

	/**
	 * @param Reference[] $input1
	 * @param Reference[] $input2
	 */
	protected function assertReferenceListsEqual( array $input1, array $input2 ) {
		$list1 = $this->flattenReferenceList( $input1 );
		$list2 = $this->flattenReferenceList( $input2 );

		$this->assertEquals( $list1, $list2 );
	}
}
