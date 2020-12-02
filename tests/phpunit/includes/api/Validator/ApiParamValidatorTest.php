<?php

namespace MediaWiki\Api\Validator;

use ApiBase;
use ApiMain;
use ApiMessage;
use ApiTestCase;
use ApiUsageException;
use FauxRequest;
use MediaWiki\MediaWikiServices;
use Message;
use Wikimedia\Message\DataMessageValue;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\EnumDef;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers MediaWiki\Api\Validator\ApiParamValidator
 * @group API
 * @group medium
 */
class ApiParamValidatorTest extends ApiTestCase {

	private function getValidator( FauxRequest $request ) : array {
		$context = $this->apiContext->newTestContext( $request, $this->getTestUser()->getUser() );
		$main = new ApiMain( $context );
		return [
			new ApiParamValidator( $main, MediaWikiServices::getInstance()->getObjectFactory() ),
			$main
		];
	}

	public function testKnownTypes() : void {
		[ $validator ] = $this->getValidator( new FauxRequest( [] ) );
		$this->assertSame(
			[
				'boolean', 'enum', 'expiry', 'integer', 'limit', 'namespace', 'NULL', 'password',
				'string', 'submodule', 'tags', 'text', 'timestamp', 'user', 'upload',
			],
			$validator->knownTypes()
		);
	}

	/**
	 * @dataProvider provideNormalizeSettings
	 * @param array|mixed $settings
	 * @param array $expect
	 */
	public function testNormalizeSettings( $settings, array $expect ) : void {
		[ $validator ] = $this->getValidator( new FauxRequest( [] ) );
		$this->assertEquals( $expect, $validator->normalizeSettings( $settings ) );
	}

	public function provideNormalizeSettings() : array {
		return [
			'Basic test' => [
				[],
				[
					ParamValidator::PARAM_IGNORE_UNRECOGNIZED_VALUES => true,
					IntegerDef::PARAM_IGNORE_RANGE => true,
					ParamValidator::PARAM_TYPE => 'NULL',
				],
			],
			'Explicit ParamValidator::PARAM_IGNORE_UNRECOGNIZED_VALUES' => [
				[
					ParamValidator::PARAM_IGNORE_UNRECOGNIZED_VALUES => false,
				],
				[
					ParamValidator::PARAM_IGNORE_UNRECOGNIZED_VALUES => false,
					IntegerDef::PARAM_IGNORE_RANGE => true,
					ParamValidator::PARAM_TYPE => 'NULL',
				],
			],
			'Explicit IntegerDef::PARAM_IGNORE_RANGE' => [
				[
					IntegerDef::PARAM_IGNORE_RANGE => false,
				],
				[
					ParamValidator::PARAM_IGNORE_UNRECOGNIZED_VALUES => true,
					IntegerDef::PARAM_IGNORE_RANGE => false,
					ParamValidator::PARAM_TYPE => 'NULL',
				],
			],
			'Handle ApiBase::PARAM_RANGE_ENFORCE' => [
				[
					ApiBase::PARAM_RANGE_ENFORCE => true,
				],
				[
					ApiBase::PARAM_RANGE_ENFORCE => true,
					ParamValidator::PARAM_IGNORE_UNRECOGNIZED_VALUES => true,
					IntegerDef::PARAM_IGNORE_RANGE => false,
					ParamValidator::PARAM_TYPE => 'NULL',
				],
			],
			'Handle EnumDef::PARAM_DEPRECATED_VALUES, null' => [
				[
					EnumDef::PARAM_DEPRECATED_VALUES => [
						'null' => null,
						'true' => true,
						'string' => 'some-message',
						'array' => [ 'some-message', 'with', 'params' ],
						'Message' => ApiMessage::create(
							[ 'api-message', 'with', 'params' ], 'somecode', [ 'some-data' ]
						),
						'MessageValue' => MessageValue::new( 'message-value', [ 'with', 'params' ] ),
						'DataMessageValue' => DataMessageValue::new(
							'data-message-value', [ 'with', 'params' ], 'somecode', [ 'some-data' ]
						),
					],
				],
				[
					ParamValidator::PARAM_IGNORE_UNRECOGNIZED_VALUES => true,
					IntegerDef::PARAM_IGNORE_RANGE => true,
					EnumDef::PARAM_DEPRECATED_VALUES => [
						'null' => null,
						'true' => true,
						'string' => DataMessageValue::new( 'some-message', [], 'bogus', [ '💩' => 'back-compat' ] ),
						'array' => DataMessageValue::new(
							'some-message', [ 'with', 'params' ], 'bogus', [ '💩' => 'back-compat' ]
						),
						'Message' => DataMessageValue::new(
							'api-message', [ 'with', 'params' ], 'bogus', [ '💩' => 'back-compat' ]
						),
						'MessageValue' => MessageValue::new( 'message-value', [ 'with', 'params' ] ),
						'DataMessageValue' => DataMessageValue::new(
							'data-message-value', [ 'with', 'params' ], 'somecode', [ 'some-data' ]
						),
					],
					ParamValidator::PARAM_TYPE => 'NULL',
				],
			],
		];
	}

	/**
	 * @dataProvider provideCheckSettings
	 * @param array $params All module parameters.
	 * @param string $name Parameter to test.
	 * @param array $expect
	 */
	public function testCheckSettings( array $params, string $name, array $expect ) : void {
		[ $validator, $main ] = $this->getValidator( new FauxRequest( [] ) );
		$module = $main->getModuleFromPath( 'query+allpages' );

		$mock = $this->getMockBuilder( ParamValidator::class )
			->disableOriginalConstructor()
			->setMethods( [ 'checkSettings' ] )
			->getMock();
		$mock->expects( $this->once() )->method( 'checkSettings' )
			->willReturnCallback( function ( $n, $settings, $options ) use ( $name, $module ) {
				$this->assertSame( "ap$name", $n );
				$this->assertSame( [ 'module' => $module ], $options );

				$ret = [ 'issues' => [ 'X' ], 'allowedKeys' => [ 'Y' ], 'messages' => [] ];
				$stack = is_array( $settings ) ? [ &$settings ] : [];
				while ( $stack ) {
					foreach ( $stack[0] as $k => $v ) {
						if ( $v instanceof MessageValue ) {
							$ret['messages'][] = $v;
						} elseif ( is_array( $v ) ) {
							$stack[] = &$stack[0][$k];
						}
					}
					array_shift( $stack );
				}
				return $ret;
			} );
		TestingAccessWrapper::newFromObject( $validator )->paramValidator = $mock;

		$this->assertEquals( $expect, $validator->checkSettings( $module, $params, $name, [] ) );
	}

	public function provideCheckSettings() {
		$keys = [
			'Y', ApiBase::PARAM_RANGE_ENFORCE, ApiBase::PARAM_HELP_MSG, ApiBase::PARAM_HELP_MSG_APPEND,
			ApiBase::PARAM_HELP_MSG_INFO, ApiBase::PARAM_HELP_MSG_PER_VALUE, ApiBase::PARAM_TEMPLATE_VARS,
		];

		return [
			'Basic test' => [
				[ 'test' => null ],
				'test',
				[
					'issues' => [ 'X' ],
					'allowedKeys' => $keys,
					'messages' => [],
				]
			],
			'Message mapping' => [
				[ 'test' => [
					EnumDef::PARAM_DEPRECATED_VALUES => [
						'a' => true,
						'b' => 'bbb',
						'c' => [ 'ccc', 'p1', 'p2' ],
						'd' => Message::newFromKey( 'ddd' )->plaintextParams( 'p1', 'p2' ),
					],
				] ],
				'test',
				[
					'issues' => [ 'X' ],
					'allowedKeys' => $keys,
					'messages' => [
						DataMessageValue::new( 'bbb', [], 'bogus', [ '💩' => 'back-compat' ] ),
						DataMessageValue::new( 'ccc', [], 'bogus', [ '💩' => 'back-compat' ] )
							->params( 'p1', 'p2' ),
						DataMessageValue::new( 'ddd', [], 'bogus', [ '💩' => 'back-compat' ] )
							->plaintextParams( 'p1', 'p2' ),
					],
				]
			],
			'Test everything' => [
				[
					'xxx' => [
						ParamValidator::PARAM_TYPE => 'not tested here',
						ParamValidator::PARAM_ISMULTI => true
					],
					'test-{x}' => [
						ApiBase::PARAM_TYPE => [],
						ApiBase::PARAM_RANGE_ENFORCE => true,
						ApiBase::PARAM_HELP_MSG => 'foo',
						ApiBase::PARAM_HELP_MSG_APPEND => [],
						ApiBase::PARAM_HELP_MSG_INFO => [],
						ApiBase::PARAM_HELP_MSG_PER_VALUE => [],
						ApiBase::PARAM_TEMPLATE_VARS => [
							'x' => 'xxx',
						]
					],
				],
				'test-{x}',
				[
					'issues' => [ 'X' ],
					'allowedKeys' => $keys,
					'messages' => [
						MessageValue::new( 'foo' ),
					],
				]
			],
			'Bad types' => [
				[ 'test' => [
					ApiBase::PARAM_RANGE_ENFORCE => 1,
					ApiBase::PARAM_HELP_MSG => false,
					ApiBase::PARAM_HELP_MSG_APPEND => 'foo',
					ApiBase::PARAM_HELP_MSG_INFO => 'bar',
					ApiBase::PARAM_HELP_MSG_PER_VALUE => true,
					ApiBase::PARAM_TEMPLATE_VARS => false,
				] ],
				'test',
				[
					'issues' => [
						'X',
						ApiBase::PARAM_RANGE_ENFORCE => 'PARAM_RANGE_ENFORCE must be boolean, got integer',
						'Message specification for PARAM_HELP_MSG is not valid',
						ApiBase::PARAM_HELP_MSG_APPEND => 'PARAM_HELP_MSG_APPEND must be an array, got string',
						ApiBase::PARAM_HELP_MSG_INFO => 'PARAM_HELP_MSG_INFO must be an array, got string',
						ApiBase::PARAM_HELP_MSG_PER_VALUE => 'PARAM_HELP_MSG_PER_VALUE must be an array, got boolean',
						ApiBase::PARAM_TEMPLATE_VARS => 'PARAM_TEMPLATE_VARS must be an array, got boolean',
					],
					'allowedKeys' => $keys,
					'messages' => [],
				]
			],
			'PARAM_VALUE_LINKS is deprecated' => [
				[ 'test' => [ ApiBase::PARAM_VALUE_LINKS => null ] ],
				'test',
				[
					'issues' => [
						'X',
						ApiBase::PARAM_VALUE_LINKS => 'PARAM_VALUE_LINKS was deprecated in MediaWiki 1.35',
					],
					'allowedKeys' => $keys,
					'messages' => [],
				]
			],
			'PARAM_HELP_MSG (string)' => [
				[ 'test' => [
					ApiBase::PARAM_HELP_MSG => 'foo',
				] ],
				'test',
				[
					'issues' => [ 'X' ],
					'allowedKeys' => $keys,
					'messages' => [
						MessageValue::new( 'foo' ),
					],
				]
			],
			'PARAM_HELP_MSG (array)' => [
				[ 'test' => [
					ApiBase::PARAM_HELP_MSG => [ 'foo', 'bar' ],
				] ],
				'test',
				[
					'issues' => [ 'X' ],
					'allowedKeys' => $keys,
					'messages' => [
						MessageValue::new( 'foo', [ 'bar' ] ),
					],
				]
			],
			'PARAM_HELP_MSG (Message)' => [
				[ 'test' => [
					ApiBase::PARAM_HELP_MSG => Message::newFromKey( 'foo' )->numParams( 123 ),
				] ],
				'test',
				[
					'issues' => [ 'X' ],
					'allowedKeys' => $keys,
					'messages' => [
						MessageValue::new( 'foo' )->numParams( 123 ),
					],
				]
			],
			'PARAM_HELP_MSG_APPEND' => [
				[ 'test' => [ ApiBase::PARAM_HELP_MSG_APPEND => [
					'foo',
					false,
					[ 'bar', 'p1', 'p2' ],
					Message::newFromKey( 'baz' )->numParams( 123 ),
				] ] ],
				'test',
				[
					'issues' => [
						'X',
						'Message specification for PARAM_HELP_MSG_APPEND[1] is not valid',
					],
					'allowedKeys' => $keys,
					'messages' => [
						MessageValue::new( 'foo' ),
						MessageValue::new( 'bar', [ 'p1', 'p2' ] ),
						MessageValue::new( 'baz' )->numParams( 123 ),
					],
				]
			],
			'PARAM_HELP_MSG_INFO' => [
				[ 'test' => [ ApiBase::PARAM_HELP_MSG_INFO => [
					'foo',
					[ false ],
					[ 'foo' ],
					[ 'bar', 'p1', 'p2' ],
				] ] ],
				'test',
				[
					'issues' => [
						'X',
						'PARAM_HELP_MSG_INFO[0] must be an array, got string',
						'PARAM_HELP_MSG_INFO[1][0] must be a string, got boolean',
					],
					'allowedKeys' => $keys,
					'messages' => [
						MessageValue::new( 'apihelp-query+allpages-paraminfo-foo' ),
						MessageValue::new( 'apihelp-query+allpages-paraminfo-bar', [ 'p1', 'p2' ] ),
					],
				]
			],
			'PARAM_HELP_MSG_PER_VALUE for non-array type' => [
				[ 'test' => [
					ParamValidator::PARAM_TYPE => 'namespace',
					ApiBase::PARAM_HELP_MSG_PER_VALUE => [],
				] ],
				'test',
				[
					'issues' => [
						'X',
						ApiBase::PARAM_HELP_MSG_PER_VALUE
							=> 'PARAM_HELP_MSG_PER_VALUE can only be used with PARAM_TYPE as an array',
					],
					'allowedKeys' => $keys,
					'messages' => [],
				]
			],
			'PARAM_HELP_MSG_PER_VALUE' => [
				[ 'test' => [
					ParamValidator::PARAM_TYPE => [ 'a', 'b', 'c', 'd' ],
					ApiBase::PARAM_HELP_MSG_PER_VALUE => [
						'a' => null,
						'b' => 'bbb',
						'c' => [ 'ccc', 'p1', 'p2' ],
						'd' => Message::newFromKey( 'ddd' )->numParams( 123 ),
						'e' => 'eee',
					],
				] ],
				'test',
				[
					'issues' => [
						'X',
						'Message specification for PARAM_HELP_MSG_PER_VALUE[a] is not valid',
						'PARAM_HELP_MSG_PER_VALUE contains "e", which is not in PARAM_TYPE.',
					],
					'allowedKeys' => $keys,
					'messages' => [
						MessageValue::new( 'bbb' ),
						MessageValue::new( 'ccc', [ 'p1', 'p2' ] ),
						MessageValue::new( 'ddd' )->numParams( 123 ),
						MessageValue::new( 'eee' ),
					],
				]
			],
			'Template-style parameter name without PARAM_TEMPLATE_VARS' => [
				[ 'test{x}' => null ],
				'test{x}',
				[
					'issues' => [
						'X',
						"Parameter name may not contain '{' or '}' without PARAM_TEMPLATE_VARS",
					],
					'allowedKeys' => $keys,
					'messages' => [],
				]
			],
			'PARAM_TEMPLATE_VARS cannot be empty' => [
				[ 'test{x}' => [
					ApiBase::PARAM_TEMPLATE_VARS => [],
				] ],
				'test{x}',
				[
					'issues' => [
						'X',
						ApiBase::PARAM_TEMPLATE_VARS => 'PARAM_TEMPLATE_VARS cannot be the empty array',
					],
					'allowedKeys' => $keys,
					'messages' => [],
				]
			],
			'PARAM_TEMPLATE_VARS, ok' => [
				[
					'ok' => [
						ParamValidator::PARAM_ISMULTI => true,
					],
					'ok-templated-{x}' => [
						ParamValidator::PARAM_ISMULTI => true,
						ApiBase::PARAM_TEMPLATE_VARS => [
							'x' => 'ok',
						],
					],
					'test-{a}-{b}' => [
						ApiBase::PARAM_TEMPLATE_VARS => [
							'a' => 'ok',
							'b' => 'ok-templated-{x}',
						],
					],
				],
				'test-{a}-{b}',
				[
					'issues' => [ 'X' ],
					'allowedKeys' => $keys,
					'messages' => [],
				]
			],
			'PARAM_TEMPLATE_VARS simple errors' => [
				[
					'ok' => [
						ParamValidator::PARAM_ISMULTI => true,
					],
					'not-multi' => false,
					'test-{a}-{b}-{c}' => [
						ApiBase::PARAM_TEMPLATE_VARS => [
							'{x}' => 'ok',
							'not-in-name' => 'ok',
							'a' => false,
							'b' => 'missing',
							'c' => 'not-multi',
						],
					],
				],
				'test-{a}-{b}-{c}',
				[
					'issues' => [
						'X',
						"PARAM_TEMPLATE_VARS keys may not contain '{' or '}', got \"{x}\"",
						'Parameter name must contain PARAM_TEMPLATE_VARS key {not-in-name}',
						'PARAM_TEMPLATE_VARS[a] has invalid target type boolean',
						'PARAM_TEMPLATE_VARS[b] target parameter "missing" does not exist',
						'PARAM_TEMPLATE_VARS[c] target parameter "not-multi" must have PARAM_ISMULTI = true',
					],
					'allowedKeys' => $keys,
					'messages' => [],
				]
			],
			'PARAM_TEMPLATE_VARS no recursion' => [
				[
					'test-{a}' => [
						ParamValidator::PARAM_ISMULTI => true,
						ApiBase::PARAM_TEMPLATE_VARS => [
							'a' => 'test-{a}',
						],
					],
				],
				'test-{a}',
				[
					'issues' => [
						'X',
						'PARAM_TEMPLATE_VARS[a] cannot target the parameter itself'
					],
					'allowedKeys' => $keys,
					'messages' => [],
				]
			],
			'PARAM_TEMPLATE_VARS targeting another template, target must be a subset' => [
				[
					'ok1' => [ ParamValidator::PARAM_ISMULTI => true ],
					'ok2' => [ ParamValidator::PARAM_ISMULTI => true ],
					'test1-{a}' => [
						ApiBase::PARAM_TEMPLATE_VARS => [
							'a' => 'test2-{a}',
						],
					],
					'test2-{a}' => [
						ParamValidator::PARAM_ISMULTI => true,
						ApiBase::PARAM_TEMPLATE_VARS => [
							'a' => 'ok2',
						],
					],
				],
				'test1-{a}',
				[
					'issues' => [
						'X',
						'PARAM_TEMPLATE_VARS[a]: Target\'s PARAM_TEMPLATE_VARS must be a subset of the original',
					],
					'allowedKeys' => $keys,
					'messages' => [],
				]
			],
		];
	}

	/**
	 * @dataProvider provideGetValue
	 * @param string|null $data Request value
	 * @param mixed $settings Settings
	 * @param mixed $expect Expected value, or an expected ApiUsageException
	 */
	public function testGetValue( ?string $data, $settings, $expect ) : void {
		[ $validator, $main ] = $this->getValidator( new FauxRequest( [ 'aptest' => $data ] ) );
		$module = $main->getModuleFromPath( 'query+allpages' );

		if ( $expect instanceof ApiUsageException ) {
			try {
				$validator->getValue( $module, 'test', $settings, [] );
				$this->fail( 'Expected exception not thrown' );
			} catch ( ApiUsageException $e ) {
				$this->assertSame( $module->getModulePath(), $e->getModulePath() );
				$this->assertEquals( $expect->getStatusValue(), $e->getStatusValue() );
			}
		} else {
			$this->assertEquals( $expect, $validator->getValue( $module, 'test', $settings, [] ) );
		}
	}

	public function provideGetValue() : array {
		return [
			'Basic test' => [
				'1234',
				[
					ParamValidator::PARAM_TYPE => 'integer',
				],
				1234
			],
			'Test for default' => [
				null,
				1234,
				1234
			],
			'Test no value' => [
				null,
				[
					ParamValidator::PARAM_TYPE => 'integer',
				],
				null,
			],
			'Test boolean (false)' => [
				null,
				false,
				null,
			],
			'Test boolean (true)' => [
				'',
				false,
				true,
			],
			'Validation failure' => [
				'xyz',
				[
					ParamValidator::PARAM_TYPE => 'integer',
				],
				ApiUsageException::newWithMessage( null, [
					'paramvalidator-badinteger',
					Message::plaintextParam( 'aptest' ),
					Message::plaintextParam( 'xyz' ),
				], 'badinteger' ),
			],
		];
	}

	/**
	 * @dataProvider provideValidateValue
	 * @param mixed $value Value to validate
	 * @param mixed $settings Settings
	 * @param mixed $value Value to validate
	 * @param mixed $expect Expected value, or an expected ApiUsageException
	 */
	public function testValidateValue( $value, $settings, $expect ) : void {
		[ $validator, $main ] = $this->getValidator( new FauxRequest() );
		$module = $main->getModuleFromPath( 'query+allpages' );

		if ( $expect instanceof ApiUsageException ) {
			try {
				$validator->validateValue( $module, 'test', $value, $settings, [] );
				$this->fail( 'Expected exception not thrown' );
			} catch ( ApiUsageException $e ) {
				$this->assertSame( $module->getModulePath(), $e->getModulePath() );
				$this->assertEquals( $expect->getStatusValue(), $e->getStatusValue() );
			}
		} else {
			$this->assertEquals(
				$expect,
				$validator->validateValue( $module, 'test', $value, $settings, [] )
			);
		}
	}

	public function provideValidateValue() : array {
		return [
			'Basic test' => [
				1234,
				[
					ParamValidator::PARAM_TYPE => 'integer',
				],
				1234
			],
			'Validation failure' => [
				1234,
				[
					ParamValidator::PARAM_TYPE => 'integer',
					IntegerDef::PARAM_IGNORE_RANGE => false,
					IntegerDef::PARAM_MAX => 10,
				],
				ApiUsageException::newWithMessage( null, [
					'paramvalidator-outofrange-max',
					Message::plaintextParam( 'aptest' ),
					Message::plaintextParam( 1234 ),
					Message::numParam( '' ),
					Message::numParam( 10 ),
				], 'outofrange', [ 'min' => null, 'curmax' => 10, 'max' => 10, 'highmax' => 10 ] ),
			],
		];
	}

	public function testGetParamInfo() {
		[ $validator, $main ] = $this->getValidator( new FauxRequest() );
		$module = $main->getModuleFromPath( 'query+allpages' );
		$dummy = (object)[];

		$settings = [
			'foo' => (object)[],
		];
		$options = [
			'bar' => (object)[],
		];

		$mock = $this->getMockBuilder( ParamValidator::class )
			->disableOriginalConstructor()
			->setMethods( [ 'getParamInfo' ] )
			->getMock();
		$mock->expects( $this->once() )->method( 'getParamInfo' )
		   ->with(
				$this->identicalTo( 'aptest' ),
				$this->identicalTo( $settings ),
				$this->identicalTo( $options + [ 'module' => $module ] )
		   )
		   ->willReturn( [ $dummy ] );

		TestingAccessWrapper::newFromObject( $validator )->paramValidator = $mock;
		$this->assertSame( [ $dummy ], $validator->getParamInfo( $module, 'test', $settings, $options ) );
	}

	public function testGetHelpInfo() {
		[ $validator, $main ] = $this->getValidator( new FauxRequest() );
		$module = $main->getModuleFromPath( 'query+allpages' );

		$settings = [
			'foo' => (object)[],
		];
		$options = [
			'bar' => (object)[],
		];

		$mock = $this->getMockBuilder( ParamValidator::class )
			->disableOriginalConstructor()
			->setMethods( [ 'getHelpInfo' ] )
			->getMock();
		$mock->expects( $this->once() )->method( 'getHelpInfo' )
		   ->with(
				$this->identicalTo( 'aptest' ),
				$this->identicalTo( $settings ),
				$this->identicalTo( $options + [ 'module' => $module ] )
		   )
		   ->willReturn( [
			   'mv1' => MessageValue::new( 'parentheses', [ 'foobar' ] ),
			   'mv2' => MessageValue::new( 'paramvalidator-help-continue' ),
		   ] );

		TestingAccessWrapper::newFromObject( $validator )->paramValidator = $mock;
		$ret = $validator->getHelpInfo( $module, 'test', $settings, $options );
		$this->assertArrayHasKey( 'mv1', $ret );
		$this->assertInstanceOf( Message::class, $ret['mv1'] );
		$this->assertEquals( '(parentheses: foobar)', $ret['mv1']->inLanguage( 'qqx' )->plain() );
		$this->assertArrayHasKey( 'mv2', $ret );
		$this->assertInstanceOf( Message::class, $ret['mv2'] );
		$this->assertEquals(
			[ 'api-help-param-continue', 'paramvalidator-help-continue' ],
			$ret['mv2']->getKeysToTry()
		);
		$this->assertCount( 2, $ret );
	}
}
