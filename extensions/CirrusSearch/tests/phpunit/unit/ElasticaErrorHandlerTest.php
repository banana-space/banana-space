<?php

namespace CirrusSearch;

use Elastica\Exception\ExceptionInterface;
use Elastica\Exception\NotFoundException;
use Elastica\Exception\ResponseException;
use Elastica\Request;
use Elastica\Response;

/**
 * @group CirrusSearch
 * @covers \CirrusSearch\ElasticaErrorHandler
 */
class ElasticaErrorHandlerTest extends CirrusTestCase {

	public static function provideExceptions() {
		return [
			'Regex is rejected' => [
				'rejected',
				self::newResponseException( 'invalid_regex_exception', 'Syntax error somewhere' ),
			],
			'Too many clauses is rejected' => [
				'rejected',
				self::newResponseException( 'too_many_clauses', 'Too many boolean clauses' ),
			],
			'NPE is failed' => [
				'failed',
				self::newResponseException( 'null_pointer_exception', 'Bug somewhere' ),
			],
			'Exotic NPE is unknown' => [
				'unknown',
				self::newResponseException( 'null_pointer_error', 'Bug in the bug' ),
			],
			'Elastica connection problem is failed' => [
				'failed',
				new \Elastica\Exception\Connection\HttpException( CURLE_COULDNT_CONNECT ),
			],
			'Elastica connection timeout is failed' => [
				'failed',
				new \Elastica\Exception\Connection\HttpException( 28 ),
			],
			'null is unkown' => [
				'unknown',
				null,
			],
		];
	}

	/**
	 * @dataProvider provideExceptions
	 */
	public function testExceptionClassifier( $expected_type, $exception ) {
		$this->assertEquals( $expected_type, ElasticaErrorHandler::classifyError( $exception ) );
	}

	public static function newResponseException( $type, $message ) {
		return new \Elastica\Exception\ResponseException(
			new \Elastica\Request( 'dummy' ),
			new \Elastica\Response(
				[
					'error' => [
						'root_cause' => [ [
							'type' => $type,
							'reason' => $message,
						] ],
					]
				]
			)
		);
	}

	public function extractMessageAndStatusProvider() {
		return [
			'non-elasticsearch error' => [
				'expected' => 'unknown: Status code 503; 503 Bad Gateway',
				'exception' => new ResponseException(
					new Request( 'dummy' ),
					new Response( '503 Bad Gateway', 503 )
				),
			],
		];
	}

	/**
	 * @dataProvider extractMessageAndStatusProvider
	 */
	public function testExtractMessageAndStatus( $expected, $exception ) {
		list( $status, $message ) = ElasticaErrorHandler::extractMessageAndStatus( $exception );
		$this->assertEquals( $expected, $message );
	}

	public function extractFullErrorProvider() {
		return [
			'non-elasticsearch error' => [
				'expected' => [
					'type' => 'unknown',
					'reason' => 'Status code 503; 503 Bad Gateway',
				],
				'exception' => new ResponseException(
					new Request( 'dummy' ),
					new Response( '503 Bad Gateway', 503 )
				),
				'message' => print_r( ( new Response( '503 Bad Gateway', 503 ) )->getData(), true )
			],
			'non-response error' => [
				'expected' => [
					'type' => 'unknown',
					'reason' => 'not found at 1234',
				],
				'exception' => new NotFoundException( 'not found at 1234' )
			],
		];
	}

	/**
	 * @dataProvider extractFullErrorProvider
	 */
	public function testExtractFullError( $expected, ExceptionInterface $exception, $message = '' ) {
		$this->assertEquals( $expected, ElasticaErrorHandler::extractFullError( $exception ), $message );
	}
}
