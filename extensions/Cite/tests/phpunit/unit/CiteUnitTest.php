<?php

namespace Cite\Tests\Unit;

use Cite\Cite;
use Cite\ErrorReporter;
use Cite\FootnoteMarkFormatter;
use Cite\ReferencesFormatter;
use Cite\ReferenceStack;
use Language;
use LogicException;
use Parser;
use ParserOptions;
use ParserOutput;
use StripState;
use Wikimedia\TestingAccessWrapper;

/**
 * @coversDefaultClass \Cite\Cite
 *
 * @license GPL-2.0-or-later
 */
class CiteUnitTest extends \MediaWikiUnitTestCase {

	/**
	 * @covers ::validateRef
	 * @covers ::validateRefOutsideOfReferences
	 * @covers ::validateRefInReferences
	 * @dataProvider provideValidateRef
	 */
	public function testValidateRef(
		array $referencesStack,
		?string $inReferencesGroup,
		bool $isSectionPreview,
		?string $text,
		?string $group,
		?string $name,
		?string $extends,
		?string $follow,
		?string $dir,
		$expected
	) {
		/** @var ErrorReporter $errorReporter */
		$errorReporter = $this->createMock( ErrorReporter::class );
		/** @var ReferenceStack $stack */
		$stack = TestingAccessWrapper::newFromObject( new ReferenceStack( $errorReporter ) );
		$stack->refs = $referencesStack;

		/** @var Cite $cite */
		$cite = TestingAccessWrapper::newFromObject( $this->newCite() );
		$cite->referenceStack = $stack;
		$cite->inReferencesGroup = $inReferencesGroup;
		$cite->isSectionPreview = $isSectionPreview;

		$status = $cite->validateRef( $text, $group, $name, $extends, $follow, $dir );
		if ( is_string( $expected ) ) {
			$this->assertSame( $expected, $status->getErrors()[0]['message'] );
		} else {
			$this->assertSame( $expected, $status->isGood(), $status->getErrors()[0]['message'] ?? '' );
		}
	}

	public function provideValidateRef() {
		return [
			// Shared <ref> validations regardless of context
			'Numeric name' => [
				'referencesStack' => [],
				'inReferencesGroup' => null,
				'isSectionPreview' => false,
				'text' => null,
				'group' => '',
				'name' => '1',
				'extends' => null,
				'follow' => null,
				'dir' => null,
				'expected' => 'cite_error_ref_numeric_key',
			],
			'Numeric follow' => [
				'referencesStack' => [],
				'inReferencesGroup' => null,
				'isSectionPreview' => false,
				'text' => 't',
				'group' => '',
				'name' => null,
				'extends' => null,
				'follow' => '1',
				'dir' => null,
				'expected' => 'cite_error_ref_numeric_key',
			],
			'Numeric extends' => [
				'referencesStack' => [],
				'inReferencesGroup' => null,
				'isSectionPreview' => false,
				'text' => 't',
				'group' => '',
				'name' => null,
				'extends' => '1',
				'follow' => null,
				'dir' => null,
				'expected' => 'cite_error_ref_numeric_key',
			],
			'Follow with name' => [
				'referencesStack' => [],
				'inReferencesGroup' => null,
				'isSectionPreview' => false,
				'text' => 't',
				'group' => '',
				'name' => 'n',
				'extends' => null,
				'follow' => 'f',
				'dir' => null,
				'expected' => 'cite_error_ref_too_many_keys',
			],
			'Follow with extends' => [
				'referencesStack' => [],
				'inReferencesGroup' => null,
				'isSectionPreview' => false,
				'text' => 't',
				'group' => '',
				'name' => null,
				'extends' => 'e',
				'follow' => 'f',
				'dir' => null,
				'expected' => 'cite_error_ref_too_many_keys',
			],
			// Validating <ref> outside of <references>
			'text-only <ref>' => [
				'referencesStack' => [],
				'inReferencesGroup' => null,
				'isSectionPreview' => false,
				'text' => 't',
				'group' => '',
				'name' => null,
				'extends' => null,
				'follow' => null,
				'dir' => null,
				'expected' => true,
			],
			'Whitespace or empty text' => [
				'referencesStack' => [],
				'inReferencesGroup' => null,
				'isSectionPreview' => false,
				'text' => '',
				'group' => '',
				'name' => null,
				'extends' => null,
				'follow' => null,
				'dir' => null,
				'expected' => 'cite_error_ref_no_input',
			],
			'totally empty <ref>' => [
				'referencesStack' => [],
				'inReferencesGroup' => null,
				'isSectionPreview' => false,
				'text' => null,
				'group' => '',
				'name' => null,
				'extends' => null,
				'follow' => null,
				'dir' => null,
				'expected' => 'cite_error_ref_no_key',
			],
			'empty-name <ref>' => [
				'referencesStack' => [],
				'inReferencesGroup' => null,
				'isSectionPreview' => false,
				'text' => 't',
				'group' => '',
				'name' => '',
				'extends' => null,
				'follow' => null,
				'dir' => null,
				'expected' => true,
			],
			'contains <ref>-like text' => [
				'referencesStack' => [],
				'inReferencesGroup' => null,
				'isSectionPreview' => false,
				'text' => 'Foo <ref name="bar">',
				'group' => '',
				'name' => 'n',
				'extends' => null,
				'follow' => null,
				'dir' => null,
				'expected' => 'cite_error_included_ref',
			],

			// Validating a <ref> in <references>
			'most trivial <ref> in <references>' => [
				'referencesStack' => [ 'g' => [ 'n' => [] ] ],
				'inReferencesGroup' => 'g',
				'isSectionPreview' => false,
				'text' => 'not empty',
				'group' => 'g',
				'name' => 'n',
				'extends' => null,
				'follow' => null,
				'dir' => null,
				'expected' => true,
			],
			'Different group than <references>' => [
				'referencesStack' => [ 'g' => [ 'n' => [] ] ],
				'inReferencesGroup' => 'g1',
				'isSectionPreview' => false,
				'text' => 't',
				'group' => 'g2',
				'name' => 'n',
				'extends' => null,
				'follow' => null,
				'dir' => null,
				'expected' => 'cite_error_references_group_mismatch',
			],
			'Unnamed in <references>' => [
				'referencesStack' => [ 'g' => [ 'n' => [] ] ],
				'inReferencesGroup' => 'g',
				'isSectionPreview' => false,
				'text' => 't',
				'group' => 'g',
				'name' => null,
				'extends' => null,
				'follow' => null,
				'dir' => null,
				'expected' => 'cite_error_references_no_key',
			],
			'Empty name in <references>' => [
				'referencesStack' => [ 'g' => [ 'n' => [] ] ],
				'inReferencesGroup' => 'g',
				'isSectionPreview' => false,
				'text' => 't',
				'group' => 'g',
				'name' => '',
				'extends' => null,
				'follow' => null,
				'dir' => null,
				'expected' => 'cite_error_references_no_key',
			],
			'Empty text in <references>' => [
				'referencesStack' => [ 'g' => [ 'n' => [] ] ],
				'inReferencesGroup' => 'g',
				'isSectionPreview' => false,
				'text' => '',
				'group' => 'g',
				'name' => 'n',
				'extends' => null,
				'follow' => null,
				'dir' => null,
				'expected' => 'cite_error_empty_references_define',
			],
			'Group never used' => [
				'referencesStack' => [ 'g2' => [ 'n' => [] ] ],
				'inReferencesGroup' => 'g',
				'isSectionPreview' => false,
				'text' => 'not empty',
				'group' => 'g',
				'name' => 'n',
				'extends' => null,
				'follow' => null,
				'dir' => null,
				'expected' => 'cite_error_references_missing_group',
			],
			'Ref never used' => [
				'referencesStack' => [ 'g' => [ 'n' => [] ] ],
				'inReferencesGroup' => 'g',
				'isSectionPreview' => false,
				'text' => 'not empty',
				'group' => 'g',
				'name' => 'n2',
				'extends' => null,
				'follow' => null,
				'dir' => null,
				'expected' => 'cite_error_references_missing_key',
			],
			'Good dir' => [
				'referencesStack' => [],
				'inReferencesGroup' => null,
				'isSectionPreview' => false,
				'text' => 'not empty',
				'group' => '',
				'name' => 'n',
				'extends' => null,
				'follow' => null,
				'dir' => 'RTL',
				'expected' => true,
			],
			'Bad dir' => [
				'referencesStack' => [],
				'inReferencesGroup' => null,
				'isSectionPreview' => false,
				'text' => 'not empty',
				'group' => '',
				'name' => 'n',
				'extends' => null,
				'follow' => null,
				'dir' => 'foobar',
				'expected' => 'cite_error_ref_invalid_dir',
			],
		];
	}

	/**
	 * @covers ::validateRef
	 */
	public function testValidateRef_noExtends() {
		global $wgCiteBookReferencing;
		$wgCiteBookReferencing = false;

		/** @var Cite $cite */
		$cite = TestingAccessWrapper::newFromObject( $this->newCite() );
		$status = $cite->validateRef( 'text', '', 'name', 'a', null, null );
		$this->assertSame( 'cite_error_ref_too_many_keys', $status->getErrors()[0]['message'] );
	}

	/**
	 * @covers ::parseArguments
	 * @dataProvider provideParseArguments
	 */
	public function testParseArguments(
		array $attributes,
		array $expectedValue,
		string $expectedError = null
	) {
		/** @var Cite $cite */
		$cite = TestingAccessWrapper::newFromObject( $this->newCite() );
		$status = $cite->parseArguments(
			$attributes,
			[ 'dir', 'extends', 'follow', 'group', 'name' ]
		);
		$this->assertSame( $expectedValue, array_values( $status->getValue() ) );
		$this->assertSame( !$expectedError, $status->isGood() );
		if ( $expectedError ) {
			$this->assertSame( $expectedError, $status->getErrors()[0]['message'] );
		}
	}

	public function provideParseArguments() {
		// Note: Values are guaranteed to be trimmed by the parser, see
		// Sanitizer::decodeTagAttributes()
		return [
			[ [], [ null, null, null, null, null ] ],

			// One attribute only
			[ [ 'dir' => 'invalid' ], [ 'invalid', null, null, null, null ] ],
			[ [ 'dir' => 'rtl' ], [ 'rtl', null, null, null, null ] ],
			[ [ 'follow' => 'f' ], [ null, null, 'f', null, null ] ],
			[ [ 'group' => 'g' ], [ null, null, null, 'g', null ] ],
			[
				[ 'invalid' => 'i' ],
				[ null, null, null, null, null ],
				'cite_error_ref_too_many_keys'
			],
			[
				[ 'invalid' => null ],
				[ null, null, null, null, null ],
				'cite_error_ref_too_many_keys'
			],
			[ [ 'name' => 'n' ], [ null, null, null, null, 'n' ] ],
			[ [ 'name' => null ], [ null, null, null, null, null ] ],
			[ [ 'extends' => 'e' ], [ null, 'e', null, null, null ] ],

			// Pairs
			[ [ 'follow' => 'f', 'name' => 'n' ], [ null, null, 'f', null, 'n' ] ],
			[ [ 'follow' => null, 'name' => null ], [ null, null, null, null, null ] ],
			[ [ 'follow' => 'f', 'extends' => 'e' ], [ null, 'e', 'f', null, null ] ],
			[ [ 'group' => 'g', 'name' => 'n' ], [ null, null, null, 'g', 'n' ] ],

			// Combinations of 3 or more attributes
			[
				[ 'group' => 'g', 'name' => 'n', 'extends' => 'e', 'dir' => 'rtl' ],
				[ 'rtl', 'e', null, 'g', 'n' ]
			],
		];
	}

	/**
	 * @covers ::guardedReferences
	 * @dataProvider provideGuardedReferences
	 */
	public function testGuardedReferences(
		?string $text,
		array $argv,
		int $expectedRollbackCount,
		string $expectedInReferencesGroup,
		bool $expectedResponsive,
		string $expectedOutput
	) {
		global $wgCiteResponsiveReferences;
		$wgCiteResponsiveReferences = false;

		/** @var Parser $parser */
		$parser = $this->createMock( Parser::class );

		$cite = $this->newCite();
		/** @var Cite $spy */
		$spy = TestingAccessWrapper::newFromObject( $cite );
		$spy->errorReporter = $this->createMock( ErrorReporter::class );
		$spy->errorReporter->method( 'halfParsed' )->willReturnCallback(
			function ( Parser $parser, ...$args ) {
				return '(' . implode( '|', $args ) . ')';
			}
		);
		$spy->referencesFormatter = $this->createMock( ReferencesFormatter::class );
		$spy->referencesFormatter->method( 'formatReferences' )
			->with( $parser, [], $expectedResponsive, false )
			->willReturn( 'references!' );
		$spy->isSectionPreview = false;
		$spy->referenceStack = $this->createMock( ReferenceStack::class );
		$spy->referenceStack->method( 'popGroup' )
			->with( $expectedInReferencesGroup )->willReturn( [] );
		$spy->referenceStack->expects( $expectedRollbackCount ? $this->once() : $this->never() )
			->method( 'rollbackRefs' )
			->with( $expectedRollbackCount )
			->willReturn( [ [ 't', [] ] ] );

		$output = $spy->guardedReferences( $parser, $text, $argv );
		$this->assertSame( $expectedOutput, $output );
	}

	public function provideGuardedReferences() {
		return [
			'Bare references tag' => [
				null,
				[],
				0,
				'',
				false,
				'references!'
			],
			'References with group' => [
				null,
				[ 'group' => 'g' ],
				0,
				'g',
				false,
				'references!'
			],
			'Empty references tag' => [
				'',
				[],
				0,
				'',
				false,
				'references!'
			],
			'Set responsive' => [
				'',
				[ 'responsive' => '1' ],
				0,
				'',
				true,
				'references!'
			],
			'Unknown attribute' => [
				'',
				[ 'blargh' => '0' ],
				0,
				'',
				false,
				'(cite_error_references_invalid_parameters)',
			],
			'Contains refs (which are broken)' => [
				Parser::MARKER_PREFIX . '-ref- and ' . Parser::MARKER_PREFIX . '-notref-',
				[],
				1,
				'',
				false,
				'references!' . "\n" . '(cite_error_references_no_key)'
			],
		];
	}

	/**
	 * @covers ::guardedRef
	 * @dataProvider provideGuardedRef
	 */
	public function testGuardedRef(
		string $text,
		array $argv,
		?string $inReferencesGroup,
		array $initialRefs,
		string $expectOutput,
		array $expectedErrors,
		array $expectedRefs
	) {
		$mockParser = $this->createMock( Parser::class );
		$mockParser->method( 'getStripState' )
			->willReturn( $this->createMock( StripState::class ) );

		$mockErrorReporter = $this->createMock( ErrorReporter::class );
		$mockErrorReporter->method( 'halfParsed' )->willReturnCallback(
			function ( $parser, ...$args ) {
				return '(' . implode( '|', $args ) . ')';
			}
		);
		$mockErrorReporter->method( 'plain' )->willReturnCallback(
			function ( $parser, ...$args ) {
				return '(' . implode( '|', $args ) . ')';
			}
		);

		/** @var ReferenceStack $referenceStack */
		$referenceStack = TestingAccessWrapper::newFromObject( new ReferenceStack( $mockErrorReporter ) );
		$referenceStack->refs = $initialRefs;

		$mockFootnoteMarkFormatter = $this->createMock( FootnoteMarkFormatter::class );
		$mockFootnoteMarkFormatter->method( 'linkRef' )->willReturn( '<foot />' );

		$cite = $this->newCite();
		/** @var Cite $spy */
		$spy = TestingAccessWrapper::newFromObject( $cite );
		$spy->errorReporter = $mockErrorReporter;
		$spy->footnoteMarkFormatter = $mockFootnoteMarkFormatter;
		$spy->inReferencesGroup = $inReferencesGroup;
		$spy->referenceStack = $referenceStack;

		$result = $spy->guardedRef( $mockParser, $text, $argv );
		$this->assertSame( $expectOutput, $result );
		$this->assertSame( $expectedErrors, $spy->mReferencesErrors );
		$this->assertSame( $expectedRefs, $referenceStack->refs );
	}

	public function provideGuardedRef() {
		return [
			'Whitespace text' => [
				' ',
				[
					'name' => 'a',
				],
				null,
				[],
				'<foot />',
				[],
				[
					'' => [
						'a' => [
							'count' => 0,
							'dir' => null,
							'key' => 1,
							'name' => 'a',
							'text' => null,
							'number' => 1,
						],
					],
				]
			],
			'Empty in default references' => [
				'',
				[],
				'',
				[ '' => [] ],
				'',
				[ '(cite_error_references_no_key)' ],
				[ '' => [] ]
			],
			'Fallback to references group' => [
				'text',
				[
					'name' => 'a',
				],
				'foo',
				[
					'foo' => [
						'a' => []
					]
				],
				'',
				[],
				[
					'foo' => [
						'a' => [ 'text' => 'text' ],
					],
				]
			],
			'Successful ref' => [
				'text',
				[
					'name' => 'a',
				],
				null,
				[],
				'<foot />',
				[],
				[
					'' => [
						'a' => [
							'count' => 0,
							'dir' => null,
							'key' => 1,
							'name' => 'a',
							'text' => 'text',
							'number' => 1,
						],
					],
				]
			],
			'Invalid ref' => [
				'text',
				[
					'name' => 'a',
					'badkey' => 'b',
				],
				null,
				[],
				'(cite_error_ref_too_many_keys)',
				[],
				[]
			],
			'Successful references ref' => [
				'text',
				[
					'name' => 'a',
				],
				'',
				[
					'' => [
						'a' => []
					]
				],
				'',
				[],
				[
					'' => [
						'a' => [ 'text' => 'text' ],
					],
				]
			],
			'Mismatched text in references' => [
				'text-2',
				[
					'name' => 'a',
				],
				'',
				[
					'' => [
						'a' => [
							'text' => 'text-1',
						]
					]
				],
				'',
				[],
				[
					'' => [
						'a' => [ 'text' => 'text-1 (cite_error_references_duplicate_key|a)' ],
					],
				]
			],
		];
	}

	/**
	 * @covers ::guardedRef
	 */
	public function testGuardedRef_extendsProperty() {
		$mockOutput = $this->createMock( ParserOutput::class );
		// This will be our most important assertion.
		$mockOutput->expects( $this->once() )
			->method( 'setProperty' )
			->with( Cite::BOOK_REF_PROPERTY, true );

		$mockParser = $this->createMock( Parser::class );
		$mockParser->method( 'getOutput' )->willReturn( $mockOutput );
		$mockParser->method( 'getStripState' )
			->willReturn( $this->createMock( StripState::class ) );
		/** @var Parser $mockParser */

		$cite = $this->newCite();
		/** @var Cite $spy */
		$spy = TestingAccessWrapper::newFromObject( $cite );
		$spy->errorReporter = $this->createMock( ErrorReporter::class );

		$spy->guardedRef( $mockParser, 'text', [ Cite::BOOK_REF_ATTRIBUTE => 'a' ] );
	}

	/**
	 * @coversNothing
	 */
	public function testReferencesSectionPreview() {
		$parserOptions = $this->createMock( ParserOptions::class );
		$parserOptions->method( 'getIsSectionPreview' )->willReturn( true );

		$parser = $this->createMock( Parser::class );
		$parser->method( 'getOptions' )->willReturn( $parserOptions );
		$parser->method( 'getContentLanguage' )->willReturn( $this->createMock( Language::class ) );

		/** @var Cite $cite */
		$cite = TestingAccessWrapper::newFromObject( new Cite( $parser ) );
		// Assume the currently parsed <ref> is wrapped in <references>
		$cite->inReferencesGroup = '';

		$html = $cite->guardedRef( $parser, 'a', [ 'name' => 'a' ] );
		$this->assertSame( '', $html );
	}

	/**
	 * @covers ::__clone
	 * @covers ::__construct
	 */
	public function testClone() {
		$cite = $this->newCite();

		$this->expectException( LogicException::class );
		clone $cite;
	}

	private function newCite() : Cite {
		$mockOptions = $this->createMock( ParserOptions::class );
		$mockOptions->method( 'getIsPreview' )->willReturn( false );
		$mockOptions->method( 'getIsSectionPreview' )->willReturn( false );
		$mockOptions->method( 'getUserLangObj' )->willReturn(
			$this->createMock( Language::class ) );
		$mockParser = $this->createMock( Parser::class );
		$mockParser->method( 'getOptions' )->willReturn( $mockOptions );
		$mockParser->method( 'getContentLanguage' )->willReturn(
			$this->createMock( Language::class ) );
		/** @var Parser $mockParser */
		return new Cite( $mockParser );
	}

}
