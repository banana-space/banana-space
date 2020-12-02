<?php

/**
 * @group TemplateData
 * @group Database
 * @covers TemplateDataBlob
 */
class TemplateDataBlobTest extends MediaWikiTestCase {

	protected function setUp() : void {
		parent::setUp();

		$this->setContentLang( 'en' );
	}

	/**
	 * Helper method to generate a string that gzip can't compress.
	 *
	 * Output is consistent when given the same seed.
	 */
	private static function generatePseudorandomString( $length, $seed ) {
		// Compatibility with PHP7.1+; see T206287
		if ( defined( 'MT_RAND_PHP' ) ) {
			mt_srand( $seed, MT_RAND_PHP );
		} else {
			mt_srand( $seed );
		}

		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$characters_max = strlen( $characters ) - 1;

		$string = '';

		for ( $i = 0; $i < $length; $i++ ) {
			$string .= $characters[mt_rand( 0, $characters_max )];
		}

		return $string;
	}

	public static function provideParse() {
		$cases = [
			[
				'input' => '[]
				',
				'status' => 'Property "templatedata" is expected to be of type "object".'
			],
			[
				'input' => '{
					"params": {}
				}
				',
				'output' => '{
					"description": null,
					"params": {},
					"sets": [],
					"format": null,
					"maps" : {}
				}
				',
				'status' => true,
				'msg' => 'Minimal valid blob'
			],
			[
				'input' => '{
					"params": {},
					"foo": "bar"
				}
				',
				'status' => 'Unexpected property "foo".',
				'msg' => 'Unknown properties'
			],
			[
				'input' => '{}',
				'status' => 'Required property "params" not found.',
				'msg' => 'Empty object'
			],
			[
				'input' => '{
					"foo": "bar"
				}
				',
				'status' => 'Unexpected property "foo".',
				'msg' => 'Unknown properties invalidate the blob'
			],
			[
				'input' => '{
					"params": {
						"foo": {}
					}
				}
				',
				'output' => '{
					"description": null,
					"params": {
						"foo": {
							"label": null,
							"description": null,
							"default": null,
							"example": null,
							"required": false,
							"suggested": false,
							"deprecated": false,
							"aliases": [],
							"type": "unknown",
							"autovalue": null
						}
					},
					"sets": [],
					"format": null,
					"maps": {}
				}
				',
				'msg' => 'Optional properties are added if missing'
			],
			[
				'input' => '{
					"params": {
						"comment": {
							"type": "string/line"
						}
					}
				}
				',
				'output' => '{
					"description": null,
					"params": {
						"comment": {
							"label": null,
							"description": null,
							"default": null,
							"example": null,
							"autovalue": null,
							"required": false,
							"suggested": false,
							"deprecated": false,
							"aliases": [],
							"type": "line"
						}
					},
					"sets": [],
					"format": null,
					"maps": {}
				}
				',
				'msg' => 'Old string/* types are mapped to the unprefixed versions'
			],
			[
				'input' => '{
					"description": "User badge MediaWiki developers.",
					"params": {
						"nickname": {
							"label": null,
							"description": "User name of user who owns the badge",
							"default": "Base page name of the host page",
							"example": null,
							"required": false,
							"suggested": true,
							"aliases": [
								"1"
							]
						}
					}
				}
				',
				'output' => '{
					"description": {
						"en": "User badge MediaWiki developers."
					},
					"params": {
						"nickname": {
							"label": null,
							"description": {
								"en": "User name of user who owns the badge"
							},
							"default": {
								"en": "Base page name of the host page"
							},
							"example": null,
							"required": false,
							"suggested": true,
							"deprecated": false,
							"aliases": [
								"1"
							],
							"type": "unknown",
							"autovalue": null
						}
					},
					"sets": [],
					"format": null,
					"maps": {}
				}
				',
				'msg' => 'InterfaceText is expanded to langcode-keyed object, assuming content language'
			],
			[
				'input' => '{
					"description": "Document the documenter.",
					"params": {
						"1d": {
							"description": "Description of the template parameter",
							"required": true,
							"default": "example"
						},
						"2d": {
							"inherits": "1d",
							"default": "overridden"
						}
					}
				}
				',
				'output' => '{
					"description": {
						"en": "Document the documenter."
					},
					"params": {
						"1d": {
							"label": null,
							"description": {
								"en": "Description of the template parameter"
							},
							"example": null,
							"required": true,
							"suggested": false,
							"default": {
								"en": "example"
							},
							"deprecated": false,
							"aliases": [],
							"type": "unknown",
							"autovalue": null
						},
						"2d": {
							"label": null,
							"description": {
								"en": "Description of the template parameter"
							},
							"example": null,
							"required": true,
							"suggested": false,
							"default": {
								"en": "overridden"
							},
							"deprecated": false,
							"aliases": [],
							"type": "unknown",
							"autovalue": null
						}
					},
					"sets": [],
					"format": null,
					"maps" : {}
				}
				',
				'msg' => 'The inherits property copies over properties from another parameter '
					. '(preserving overides)'
			],
			[
				'input' => '{
					"params": {},
					"sets": [
						{
							"label": "Example"
						}
					]
				}',
				'status' => 'Required property "sets.0.params" not found.'
			],
			[
				'input' => '{
					"params": {
						"foo": {
						}
					},
					"sets": [
						{
							"params": ["foo"]
						}
					]
				}',
				'status' => 'Required property "sets.0.label" not found.'
			],
			[
				'input' => '{
					"params": {
						"foo": {
						},
						"bar": {
						}
					},
					"sets": [
						{
							"label": "Foo with Quux",
							"params": ["foo", "quux"]
						}
					]
				}',
				'status' => 'Invalid value for property "sets.0.params[1]".'
			],
			[
				'input' => '{
					"params": {
						"foo": {
						},
						"bar": {
						},
						"quux": {
						}
					},
					"sets": [
						{
							"label": "Foo with Quux",
							"params": ["foo", "quux"]
						},
						{
							"label": "Bar with Quux",
							"params": ["bar", "quux"]
						}
					]
				}',
				'output' => '{
					"description": null,
					"params": {
						"foo": {
							"label": null,
							"required": false,
							"example": null,
							"suggested": false,
							"description": null,
							"deprecated": false,
							"aliases": [],
							"default": null,
							"type": "unknown",
							"autovalue": null
						},
						"bar": {
							"label": null,
							"required": false,
							"suggested": false,
							"description": null,
							"example": null,
							"deprecated": false,
							"aliases": [],
							"default": null,
							"type": "unknown",
							"autovalue": null
						},
						"quux": {
							"label": null,
							"required": false,
							"suggested": false,
							"description": null,
							"example": null,
							"deprecated": false,
							"aliases": [],
							"default": null,
							"type": "unknown",
							"autovalue": null
						}
					},
					"sets": [
						{
							"label": {
								"en": "Foo with Quux"
							},
							"params": ["foo", "quux"]
						},
						{
							"label": {
								"en": "Bar with Quux"
							},
							"params": ["bar", "quux"]
						}
					],
					"format": null,
					"maps": {}
				}',
				'status' => true
			],
			[
				'input' => '{
					"description": "Testing some template description.",
					"params": {
						"bar": {
							"label": "Bar label",
							"description": "Bar description",
							"default": "Baz",
							"example": "Foo bar baz",
							"autovalue": "{{SomeTemplate}}",
							"required": true,
							"suggested": false,
							"deprecated": false,
							"aliases": [ "foo", "baz" ],
							"type": "line"
						}
					}
				}
				',
				'output' => '{
					"description": {
						"en": "Testing some template description."
					},
					"params": {
						"bar": {
							"label": {
								"en": "Bar label"
							},
							"description": {
								"en": "Bar description"
							},
							"default": {
								"en": "Baz"
							},
							"example": {
								"en": "Foo bar baz"
							},
							"autovalue": "{{SomeTemplate}}",
							"required": true,
							"suggested": false,
							"deprecated": false,
							"aliases": [ "foo", "baz" ],
							"type": "line"
						}
					},
					"sets": [],
					"format": null,
					"maps" : {}
				}
				',
				'msg' => 'Parameter attributes preserve information.'
			],
			[
				'input' => '{
					"params": {
						"foo": {
						},
						"bar": {
						}
					},
					"sets": [],
					"maps": {
						"application": {
							"things": [
								"foo",
								["bar", "quux"]
							]
						}
					}
				}',
				'status' => 'Invalid parameter "quux" for property "maps.application.things".'
			],
			[
				'input' => '{
					"params": {
						"foo": {
						},
						"bar": {
						}
					},
					"sets": [],
					"maps": {
						"application": {
							"things": {
								"appbar": "bar",
								"appfoo": "foo"
							}
						}
					}
				}',
				'status' => 'Property "maps.application.things" is expected to be of type "string|array".'
			],
			[
				'input' => '{
					"params": {
						"foo": {
						},
						"bar": {
						}
					},
					"sets": [],
					"maps": {
						"application": {
							"things": [
								[ true ]
							]
						}
					}
				}',
				'status' => 'Property "maps.application.things[0][0]" is expected to be of type "string".'
			],
			[
				'input' => '{
					"params": {
						"foo": {}
					},
					"format": "meshuggah format"
				}',
				'status' => 'Property "format" is expected to be "inline", "block", or a valid format string.'
			],
			[
				'input' => '{
					"params": {},
					"format": "inline"
				}',
				'output' => '{
					"description": null,
					"params": {},
					"sets": [],
					"format": "inline",
					"maps": {}
				}
				',
				'msg' => '"inline" is a valid format string',
				'status' => true
			],
			[
				'input' => '{
					"params": {},
					"format": "block"
				}',
				'output' => '{
					"description": null,
					"params": {},
					"sets": [],
					"format": "block",
					"maps": {}
				}
				',
				'msg' => '"block" is a valid format string',
				'status' => true
			],
			[
				'input' => '{
					"params": {},
					"format": "{{_ |\n ___ = _}}"
				}',
				'output' => '{
					"description": null,
					"params": {},
					"sets": [],
					"format": "{{_ |\n ___ = _}}",
					"maps": {}
				}
				',
				'msg' => 'Custom parameter format string (1)',
				'status' => true
			],
			[
				'input' => '{
					"params": {},
					"format": "{{_|_=_\n}}\n"
				}',
				'output' => '{
					"description": null,
					"params": {},
					"sets": [],
					"format": "{{_|_=_\n}}\n",
					"maps": {}
				}
				',
				'msg' => 'Custom parameter format string (2)',
				'status' => true
			],
		];

		$calls = [];
		foreach ( $cases as $case ) {
			$calls[] = [ $case ];
		}
		return $calls;
	}

	protected static function getStatusText( Status $status ) {
		$str = Parser::stripOuterParagraph( $status->getHtml() );
		// Unescape char references for things like "[, "]" and "|" for
		// cleaner test assertions and output
		$str = Sanitizer::decodeCharReferences( $str );
		return $str;
	}

	private static function ksort( array &$input ) {
		ksort( $input );
		foreach ( $input as $key => &$value ) {
			if ( is_array( $value ) ) {
				self::ksort( $value );
			}
		}
	}

	/**
	 * PHPUnit'a assertEquals does weak comparison, use strict instead.
	 *
	 * There is a built-in assertSame, but that only strictly compares
	 * the top level structure, not the invidual array values.
	 *
	 * so "array( 'a' => '' )" still equals "array( 'a' => null )"
	 * because empty string equals null in PHP's weak comparison.
	 *
	 * @param mixed $expected
	 * @param mixed $actual
	 */
	protected function assertStrictJsonEquals( $expected, $actual, $message = null ) {
		// Lazy recursive strict comparison: Serialise to JSON and compare that
		// Sort first to ensure key-order
		$expected = json_decode( $expected, /* assoc = */ true );
		$actual = json_decode( $actual, /* assoc = */ true );
		self::ksort( $expected );
		self::ksort( $actual );

		$this->assertEquals(
			FormatJson::encode( $expected, true ),
			FormatJson::encode( $actual, true ),
			$message
		);
	}

	protected function assertTemplateData( array $case ) {
		// Expand defaults
		if ( !isset( $case['status'] ) ) {
			$case['status'] = true;
		}
		if ( !isset( $case['msg'] ) ) {
			$case['msg'] = is_string( $case['status'] ) ? $case['status'] : 'TemplateData assertion';
		}
		if ( !isset( $case['output'] ) ) {
			if ( is_string( $case['status'] ) ) {
				$case['output'] = '{
					"description": null,
					"params": {},
					"sets": [],
					"maps": {},
					"format": null
				}';
			} else {
				$case['output'] = $case['input'];
			}
		}

		$t = TemplateDataBlob::newFromJSON( $this->db, $case['input'] );
		$actual = $t->getJSON();
		$status = $t->getStatus();
		if ( !$status->isGood() ) {
			$this->assertEquals(
				$case['status'],
				self::getStatusText( $status ),
				'Status: ' . $case['msg']
			);
		} else {
			$this->assertEquals(
				$case['status'],
				$status->isGood(),
				'Status: ' . $case['msg']
			);
		}

		$this->assertStrictJsonEquals(
			$case['output'],
			$actual,
			$case['msg']
		);

		// Assert this case roundtrips properly by running through the output as input.

		$t = TemplateDataBlob::newFromJSON( $this->db, $case['output'] );

		$status = $t->getStatus();
		if ( !$status->isGood() ) {
			$this->assertEquals(
				$case['status'],
				self::getStatusText( $status ),
				'Roundtrip status: ' . $case['msg']
			);
		}

		$this->assertStrictJsonEquals(
			$case['output'],
			$t->getJSON(),
			'Roundtrip: ' . $case['msg']
		);
	}

	/**
	 * @dataProvider provideParse
	 */
	public function testParse( array $case ) {
		$this->assertTemplateData( $case );
	}

	/**
	 * MySQL breaks if the input is too large even after compression
	 */
	public function testParseLongString() {
		if ( $this->db->getType() === 'mysql' ) {
			$this->assertTemplateData(
				[
					// Should be long enough to trigger this condition after gzipping.
					'input' => '{
						"description": "' . self::generatePseudorandomString( 100000, 42 ) . '",
						"params": {}
					}',
					'status' => 'Data too large to save (75,217 bytes, limit is 65,535)'
				]
			);
		} else {
			$this->markTestSkipped( 'long compressed strings break on MySQL only' );
		}
	}

	/**
	 * Verify we can gzdecode() which came in PHP 5.4.0. Mediawiki needs a
	 * fallback function for it.
	 * If this test fail, we are most probably attempting to use gzdecode()
	 * with PHP before 5.4.
	 *
	 * @see bug T56058
	 *
	 * Some databases will not be able to store compressed data cleanly
	 * but the object will be initialized properly even if compressed
	 * data are provided
	 *
	 * @see bug T203850
	 */
	public function testGetJsonForDatabase() {
		// Compress JSON to trigger the code pass in newFromDatabase that ends
		// up calling gzdecode().
		$gzJson = gzencode( '{}' );
		$templateData = TemplateDataBlob::newFromDatabase( $this->db, $gzJson );
		$this->assertInstanceOf( 'TemplateDataBlob', $templateData );
	}

	public static function provideGetDataInLanguage() {
		$cases = [
			[
				'input' => '{
					"description": {
						"de": "German",
						"nl": "Dutch",
						"en": "English",
						"de-formal": "German (formal address)"
					},
					"params": {}
				}
				',
				'output' => '{
					"description": "German",
					"params": {},
					"sets": [],
					"format": null,
					"maps" : {}
				}
				',
				'lang' => 'de',
				'msg' => 'Simple description'
			],
			[
				'input' => '{
					"description": "Hi",
					"params": {}
				}
				',
				'output' => '{
					"description": "Hi",
					"params": {},
					"sets": [],
					"format": null,
					"maps" : {}
				}
				',
				'lang' => 'fr',
				'msg' => 'Non multi-language value returned as is (expands to { "en": value } for' .
					' content-lang, "fr" falls back to "en")'
			],
			[
				'input' => '{
					"description": {
						"nl": "Dutch",
						"de": "German"
					},
					"params": {}
				}
				',
				'output' => '{
					"description": "Dutch",
					"params": {},
					"sets": [],
					"format": null,
					"maps" : {}
				}
				',
				'lang' => 'fr',
				'msg' => 'Try content language before giving up on user language and fallbacks'
			],
			[
				'input' => '{
					"description": {
						"es": "Spanish",
						"de": "German"
					},
					"params": {}
				}
				',
				'output' => '{
					"description": null,
					"params": {},
					"sets": [],
					"format": null,
					"maps" : {}
				}
				',
				'lang' => 'fr',
				'msg' => 'Description is optional, use null if no suitable fallback'
			],
			[
				'input' => '{
					"description": {
						"de": "German",
						"nl": "Dutch",
						"en": "English"
					},
					"params": {}
				}
				',
				'output' => '{
					"description": "German",
					"params": {},
					"sets": [],
					"format": null,
					"maps" : {}
				}
				',
				'lang' => 'de-formal',
				'msg' => '"de-formal" falls back to "de"'
			],
			[
				'input' => '{
					"params": {
						"foo": {
							"label": {
								"fr": "French",
								"en": "English"
							}
						}
					}
				}
				',
				'output' => '{
					"description": null,
					"params": {
						"foo": {
							"label": "French",
							"required": false,
							"example": null,
							"suggested": false,
							"description": null,
							"deprecated": false,
							"aliases": [],
							"default": null,
							"type": "unknown",
							"autovalue": null
						}
					},
					"sets": [],
					"format": null,
					"maps" : {}
				}
				',
				'lang' => 'fr',
				'msg' => 'Simple parameter label'
			],
			[
				'input' => '{
					"params": {
						"foo": {
							"default": {
								"fr": "French",
								"en": "English"
							}
						}
					}
				}
				',
				'output' => '{
					"description": null,
					"params": {
						"foo": {
							"default": "French",
							"required": false,
							"suggested": false,
							"description": null,
							"deprecated": false,
							"aliases": [],
							"label": null,
							"type": "unknown",
							"autovalue": null,
							"example": null
						}
					},
					"sets": [],
					"format": null,
					"maps" : {}
				}
				',
				'lang' => 'fr',
				'msg' => 'Simple parameter default value'
			],
			[
				'input' => '{
					"params": {
						"foo": {
							"label": {
								"es": "Spanish",
								"de": "German"
							}
						}
					}
				}
				',
				'output' => '{
					"description": null,
					"params": {
						"foo": {
							"label": null,
							"required": false,
							"suggested": false,
							"description": null,
							"example": null,
							"deprecated": false,
							"aliases": [],
							"default": null,
							"type": "unknown",
							"autovalue": null
						}
					},
					"sets": [],
					"format": null,
					"maps" : {}
				}
				',
				'lang' => 'fr',
				'msg' => 'Parameter label is optional, use null if no matching fallback'
			],
			[
				'input' => '{
					"params": {
						"foo": {}
					},
					"sets": [
						{
							"label": {
								"es": "Spanish",
								"de": "German"
							},
							"params": ["foo"]
						}
					]
				}
				',
				'output' => '{
					"description": null,
					"params": {
						"foo": {
							"label": null,
							"required": false,
							"suggested": false,
							"description": null,
							"example": null,
							"deprecated": false,
							"aliases": [],
							"default": null,
							"type": "unknown",
							"autovalue": null
						}
					},
					"sets": [
						{
							"label": "Spanish",
							"params": ["foo"]
						}
					],
					"format": null,
					"maps": {}
				}
				',
				'lang' => 'fr',
				'msg' => 'Set label is not optional, choose first available key as final fallback'
			],
		];
		$calls = [];
		foreach ( $cases as $case ) {
			$calls[] = [ $case ];
		}
		return $calls;
	}

	/**
	 * @dataProvider provideGetDataInLanguage
	 */
	public function testGetDataInLanguage( array $case ) {
		// Change content-language to be non-English so we can distinguish between the
		// last 'en' fallback and the content language in our tests
		$this->setContentLang( 'nl' );

		if ( !isset( $case['msg'] ) ) {
			$case['msg'] = is_string( $case['status'] ) ? $case['status'] : 'TemplateData assertion';
		}

		$t = TemplateDataBlob::newFromJSON( $this->db, $case['input'] );
		$status = $t->getStatus();

		$this->assertTrue(
			$status->isGood() ?: self::getStatusText( $status ),
			'Status is good: ' . $case['msg']
		);

		$actual = $t->getDataInLanguage( $case['lang'] );
		$this->assertJsonStringEqualsJsonString(
			$case['output'],
			json_encode( $actual ),
			$case['msg']
		);
	}

	public static function provideParamOrder() {
		$cases = [
			[
				'input' => '{
					"params": {
						"foo": {},
						"bar": {},
						"baz": {}
					}
				}
				',
				'output' => '{
					"description": null,
					"params": {
						"foo": {
							"label": null,
							"required": false,
							"suggested": false,
							"description": null,
							"example": null,
							"deprecated": false,
							"aliases": [],
							"default": null,
							"type": "unknown",
							"autovalue": null
						},
						"bar": {
							"label": null,
							"required": false,
							"suggested": false,
							"description": null,
							"example": null,
							"deprecated": false,
							"aliases": [],
							"default": null,
							"type": "unknown",
							"autovalue": null
						},
						"baz": {
							"label": null,
							"required": false,
							"suggested": false,
							"description": null,
							"example": null,
							"deprecated": false,
							"aliases": [],
							"default": null,
							"type": "unknown",
							"autovalue": null
						}
					},
					"sets": [],
					"format": null,
					"maps" : {}
				}
				',
				'msg' => 'Normalisation adds paramOrder'
			],
			[
				'input' => '{
					"params": {
						"foo": {},
						"bar": {},
						"baz": {}
					},
					"paramOrder": ["baz", "foo", "bar"]
				}
				',
				'output' => '{
					"description": null,
					"params": {
						"foo": {
							"label": null,
							"required": false,
							"suggested": false,
							"description": null,
							"example": null,
							"deprecated": false,
							"aliases": [],
							"default": null,
							"type": "unknown",
							"autovalue": null
						},
						"bar": {
							"label": null,
							"required": false,
							"suggested": false,
							"description": null,
							"example": null,
							"deprecated": false,
							"aliases": [],
							"default": null,
							"type": "unknown",
							"autovalue": null
						},
						"baz": {
							"label": null,
							"required": false,
							"suggested": false,
							"description": null,
							"example": null,
							"deprecated": false,
							"aliases": [],
							"default": null,
							"type": "unknown",
							"autovalue": null
						}
					},
					"paramOrder": ["baz", "foo", "bar"],
					"sets": [],
					"format": null,
					"maps" : {}
				}
				',
				'msg' => 'Custom paramOrder'
			],
			[
				'input' => '{
					"params": {
						"foo": {},
						"bar": {},
						"baz": {}
					},
					"paramOrder": ["foo", "bar"]
				}
				',
				'status' => 'Required property "paramOrder[2]" not found.',
				'msg' => 'Incomplete paramOrder'
			],
			[
				'input' => '{
					"params": {}
				}
				',
				'output' => '{
					"description": null,
					"params": {},
					"sets": [],
					"format": null,
					"maps" : {}
				}
				',
				'msg' => 'Empty parameter object produces empty array paramOrder'
			],
			[
				'input' => '{
					"params": {
						"foo": {},
						"bar": {},
						"baz": {}
					},
					"paramOrder": ["foo", "bar", "baz", "quux"]
				}
				',
				'status' => 'Invalid value for property "paramOrder[3]".',
				'msg' => 'Unknown params in paramOrder'
			],
			[
				'input' => '{
					"params": {
						"foo": {},
						"bar": {},
						"baz": {}
					},
					"paramOrder": ["foo", "bar", "baz", "bar"]
				}
				',
				'status' => 'Property "paramOrder[3]" ("bar") is a duplicate of ' .
					'"paramOrder[1]".',
				'msg' => 'Duplicate params in paramOrder'
			],
		];
		$calls = [];
		foreach ( $cases as $case ) {
			$calls[] = [ $case ];
		}
		return $calls;
	}

	/**
	 * @dataProvider provideParamOrder
	 */
	public function testParamOrder( array $case ) {
		$this->assertTemplateData( $case );
	}

	/**
	 * @dataProvider provideGetRawParams
	 */
	public function testGetRawParams( $inputWikitext, $expectedParams ) {
		$params = TemplateDataBlob::getRawParams( $inputWikitext );
		$this->assertArrayEquals( $expectedParams, $params, true, true );
	}

	public function provideGetRawParams() {
		return [
			'No params' => [
				'Lorem ipsum {{tpl}}.',
				[]
			],
			'Two plain params' => [
				'Lorem {{{name}}} ipsum {{{surname}}}',
				[ 'name' => [], 'surname' => [] ]
			],
			'Param with multiple casing and default value' => [
				'Lorem {{{name|{{{Name|Default name}}}}}} ipsum',
				[ 'name' => [] ]
			],
			'Param name contains comment' => [
				'Lorem {{{name<!-- comment -->}}} ipsum',
				[ 'name' => [] ]
			],
			'Letter-case and underscore-space normalization' => [
				'Lorem {{{First name|{{{first_name}}}}}} ipsum {{{first-Name}}}',
				[ 'First name' => [] ]
			],
			'Dynamic param name' => [
				'{{{{{#if:{{{nominee|}}}|nominee|candidate}}|}}}',
				[ 'nominee' => [] ]
			],
			'More complicated dynamic param name' => [
				'{{{party{{#if:{{{party_election||}}}|_election||}}|}}}',
				[ 'party_election' => [] ]
			],
			'Bang in a param name' => [
				'{{{!}}} {{{foo!}}}',
				[ '!' => [], 'foo!' => [] ]
			],
			'Bang as a magic word in a table construct' => [
				'{{{!}} class=""',
				[]
			],
		];
	}

	public static function provideGetHtml() {
		// phpcs:disable Generic.Files.LineLength.TooLong
		yield 'No params' => [
			[ 'params' => [ (object)[] ] ],
			<<<HTML
<div class="mw-templatedata-doc-wrap">
<p class="mw-templatedata-doc-desc mw-templatedata-doc-muted">(templatedata-doc-desc-empty)</p>
<table class="wikitable mw-templatedata-doc-params">
	<caption><p>(templatedata-doc-params)</p></caption>
	<thead><tr><th colspan="2">(templatedata-doc-param-name)</th><th>(templatedata-doc-param-desc)</th><th>(templatedata-doc-param-type)</th><th>(templatedata-doc-param-status)</th></tr></thead>
	<tbody>
		<tr>
			<td class="mw-templatedata-doc-muted" colspan="7">(templatedata-doc-no-params-set)</td>
		</tr>
	</tbody>
</table>
</div>
HTML
		];
		yield 'Basic params' => [
			[ 'params' => [ 'foo' => (object)[], 'bar' => [ 'required' => true ] ] ],
			<<<HTML
<div class="mw-templatedata-doc-wrap">
<p class="mw-templatedata-doc-desc mw-templatedata-doc-muted">(templatedata-doc-desc-empty)</p>
<table class="wikitable mw-templatedata-doc-params sortable">
	<caption><p>(templatedata-doc-params)</p></caption>
	<thead><tr><th colspan="2">(templatedata-doc-param-name)</th><th>(templatedata-doc-param-desc)</th><th>(templatedata-doc-param-type)</th><th>(templatedata-doc-param-status)</th></tr></thead>
	<tbody>
		<tr>
			<th>Foo</th>
			<td class="mw-templatedata-doc-param-name"><code>foo</code></td>
			<td class="mw-templatedata-doc-muted"><p>(templatedata-doc-param-desc-empty)</p><dl></dl></td>
			<td class="mw-templatedata-doc-param-type mw-templatedata-doc-muted">(templatedata-doc-param-type-unknown)</td>
			<td>(templatedata-doc-param-status-optional)</td>
		</tr>
		<tr>
			<th>Bar</th>
			<td class="mw-templatedata-doc-param-name"><code>bar</code></td>
			<td class="mw-templatedata-doc-muted"><p>(templatedata-doc-param-desc-empty)</p><dl></dl></td>
			<td class="mw-templatedata-doc-param-type mw-templatedata-doc-muted">(templatedata-doc-param-type-unknown)</td>
			<td class="mw-templatedata-doc-param-status-required">(templatedata-doc-param-status-required)</td>
		</tr>
	</tbody>
</table>
</div>
HTML
		];
	}

	/**
	 * @dataProvider provideGetHtml
	 */
	public function testGetHtml( array $data, $expected ) {
		$t = TemplateDataBlob::newFromJSON( $this->db, json_encode( $data ) );
		$actual = $t->getHtml( Language::factory( 'qqx' ) );
		$linedActual = preg_replace( '/>\s*</', ">\n<", $actual );

		$linedExpected = preg_replace( '/>\s*</', ">\n<", trim( $expected ) );

		$this->assertEquals( $linedExpected, $linedActual, 'html' );
	}
}
