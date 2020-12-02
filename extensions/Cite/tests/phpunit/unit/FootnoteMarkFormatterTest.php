<?php

namespace Cite\Tests;

use Cite\AnchorFormatter;
use Cite\ErrorReporter;
use Cite\FootnoteMarkFormatter;
use Cite\ReferenceMessageLocalizer;
use Message;
use Parser;
use Wikimedia\TestingAccessWrapper;

/**
 * @coversDefaultClass \Cite\FootnoteMarkFormatter
 */
class FootnoteMarkFormatterTest extends \MediaWikiUnitTestCase {

	/**
	 * @covers ::linkRef
	 * @covers ::__construct
	 * @dataProvider provideLinkRef
	 */
	public function testLinkRef( string $group, array $ref, string $expectedOutput ) {
		$fooLabels = 'a b c';

		$mockErrorReporter = $this->createMock( ErrorReporter::class );
		$mockErrorReporter->method( 'plain' )->willReturnCallback(
			function ( $parser, ...$args ) {
				return implode( '|', $args );
			}
		);
		$anchorFormatter = $this->createMock( AnchorFormatter::class );
		$anchorFormatter->method( 'getReferencesKey' )->willReturnArgument( 0 );
		$anchorFormatter->method( 'refKey' )->willReturnCallback(
			function ( ...$args ) {
				return implode( '+', $args );
			}
		);
		$mockMessageLocalizer = $this->createMock( ReferenceMessageLocalizer::class );
		$mockMessageLocalizer->method( 'formatNum' )->willReturnArgument( 0 );
		$mockMessageLocalizer->method( 'localizeDigits' )->willReturnArgument( 0 );
		$mockMessageLocalizer->method( 'msg' )->willReturnCallback(
			function ( ...$args ) use ( $group, $fooLabels ) {
				$msg = $this->createMock( Message::class );
				$msg->method( 'isDisabled' )->willReturn( $group !== 'foo' );
				$msg->method( 'plain' )->willReturn( $args[0] === 'cite_reference_link'
					? '(' . implode( '|', $args ) . ')'
					: $fooLabels );
				return $msg;
			}
		);
		$mockParser = $this->createMock( Parser::class );
		$mockParser->method( 'recursiveTagParse' )->willReturnArgument( 0 );
		$formatter = new FootnoteMarkFormatter(
			$mockErrorReporter,
			$anchorFormatter,
			$mockMessageLocalizer
		);

		$output = $formatter->linkRef( $mockParser, $group, $ref );
		$this->assertSame( $expectedOutput, $output );
	}

	public function provideLinkRef() {
		return [
			'Default label' => [
				'',
				[
					'name' => null,
					'number' => 3,
					'key' => 4,
					'count' => -1,
				],
				'(cite_reference_link|4+|4|3)'
			],
			'Default label, named group' => [
				'bar',
				[
					'name' => null,
					'number' => 3,
					'key' => 4,
					'count' => -1,
				],
				'(cite_reference_link|4+|4|bar 3)'
			],
			'Custom label' => [
				'foo',
				[
					'name' => null,
					'number' => 3,
					'key' => 4,
					'count' => -1,
				],
				'(cite_reference_link|4+|4|c)'
			],
			'Custom label overrun' => [
				'foo',
				[
					'name' => null,
					'number' => 10,
					'key' => 4,
					'count' => -1,
				],
				'(cite_reference_link|4+|4|' .
					'cite_error_no_link_label_group&#124;foo&#124;cite_link_label_group-foo)'
			],
			'Named ref' => [
				'',
				[
					'name' => 'a',
					'number' => 3,
					'key' => 4,
					'count' => 0,
				],
				'(cite_reference_link|a+4-0|a-4|3)'
			],
			'Named ref reused' => [
				'',
				[
					'name' => 'a',
					'number' => 3,
					'key' => 4,
					'count' => 2,
				],
				'(cite_reference_link|a+4-2|a-4|3)'
			],
			'Subreference' => [
				'',
				[
					'name' => null,
					'number' => 3,
					'key' => 4,
					'count' => -1,
					'extends' => 'b',
					'extendsIndex' => 2,
				],
				'(cite_reference_link|4+|4|3.2)'
			],
		];
	}

	/**
	 * @covers ::getLinkLabel
	 *
	 * @dataProvider provideGetLinkLabel
	 *
	 * @param string|null $expectedLabel
	 * @param int $offset
	 * @param string $group
	 * @param string $label
	 * @param string|null $labelList
	 */
	public function testGetLinkLabel( $expectedLabel, $offset, $group, $labelList ) {
		$mockMessageLocalizer = $this->createMock( ReferenceMessageLocalizer::class );
		$mockMessageLocalizer->method( 'msg' )->willReturnCallback(
			function ( ...$args ) use ( $labelList ) {
				$msg = $this->createMock( Message::class );
				$msg->method( 'isDisabled' )->willReturn( $labelList === null );
				$msg->method( 'plain' )->willReturn( $labelList );
				return $msg;
			}
		);
		$mockErrorReporter = $this->createMock( ErrorReporter::class );
		$mockErrorReporter->method( 'plain' )->willReturnCallback(
			function ( $parser, ...$args ) {
				return implode( '|', $args );
			}
		);
		/** @var FootnoteMarkFormatter $formatter */
		$formatter = TestingAccessWrapper::newFromObject( new FootnoteMarkFormatter(
			$mockErrorReporter,
			$this->createMock( AnchorFormatter::class ),
			$mockMessageLocalizer
		) );

		$output = $formatter->getLinkLabel(
			$this->createMock( Parser::class ), $group, $offset );
		$this->assertSame( $expectedLabel, $output );
	}

	public function provideGetLinkLabel() {
		yield [ null, 1, '', null ];
		yield [ null, 2, '', null ];
		yield [ null, 1, 'foo', null ];
		yield [ null, 2, 'foo', null ];
		yield [ 'a', 1, 'foo', 'a b c' ];
		yield [ 'b', 2, 'foo', 'a b c' ];
		yield [ 'å', 1, 'foo', 'å β' ];
		yield [ 'cite_error_no_link_label_group|foo|cite_link_label_group-foo', 4, 'foo', 'a b c' ];
	}

}
