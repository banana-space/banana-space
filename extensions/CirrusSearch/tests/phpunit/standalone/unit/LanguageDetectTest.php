<?php

namespace CirrusSearch;

use CirrusSearch\LanguageDetector\LanguageDetectorFactory;
use CirrusSearch\LanguageDetector\TextCat;
use CirrusSearch\Test\MockLanguageDetector;

/**
 * Completion Suggester Tests
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @group CirrusSearch
 * @group Standalone
 * @covers \CirrusSearch\LanguageDetector\LanguageDetectorFactory
 * @covers \CirrusSearch\LanguageDetector\TextCat
 */
class LanguageDetectTest extends CirrusTestCase {

	/**
	 * @var string
	 */
	private $textCatModelBaseDir;

	public function provideTestFactory() {
		return [
			'empty' => [ [ 'CirrusSearchLanguageDetectors' => [] ], [] ],
			'textcat only' => [
				[
					'CirrusSearchLanguageDetectors' => [
						'textcat' => TextCat::class
					]
				],
				[ 'textcat' => TextCat::class ]
			],
			'textcat first' => [
				[
					'CirrusSearchLanguageDetectors' => [
						'textcat' => TextCat::class,
						'mock-lang' => MockLanguageDetector::class,
					]
				],
				[
					'textcat' => TextCat::class,
					'mock-lang' => MockLanguageDetector::class,
				]
			],
			'mock-lang first' => [
				[
					'CirrusSearchLanguageDetectors' => [
						'mock-lang' => MockLanguageDetector::class,
						'textcat' => TextCat::class,
					]
				],
				[
					'mock-lang' => MockLanguageDetector::class,
					'textcat' => TextCat::class,
				]
			],
			'bad setup does not blow-up' => [
				[
					'CirrusSearchLanguageDetectors' => [
						'meh' => 'this.class.does.not.exist.Detector',
						'Title can do many things' => \Title::class,
					],
				],
				[]
			]
		];
	}

	/**
	 * @dataProvider provideTestFactory
	 */
	public function testFactory( $config, $exepected ) {
		$config = $config + [
			'CirrusSearchMockLanguage' => null,
		];
		$factory = new LanguageDetectorFactory( new HashSearchConfig( $config ) );
		$actual = array_map( function ( $v ) {
			return get_class( $v );
		}, $factory->getDetectors() );
		$this->assertEquals( $exepected, $actual );
	}

	/**
	 * data provided is: text, lang1, lang2
	 * lang1 is result with defaults (testTextCatDetector)
	 * lang2 is result with non-defaults (testTextCatDetectorWithParams)
	 *		see notes inline
	 */
	public function getLanguageTexts() {
		return [
			// simple cases
			[ "Welcome to Wikipedia, the free encyclopedia that anyone can edit", "en", "en" ],
			[ "Добро пожаловать в Википедию", "ru", "uk" ],	// ru missing, uk present

			// more query-like cases
			[ "who stars in Breaking Bad?", "en", "en" ],
			[ "Jesenwang flugplatz", "de", "de" ],
			[ "volviendose malo", "es", null ], // en boosted -> too ambiguous
			[ "противоточный теплообменник", "ru", "uk" ], // ru missing, uk present
			[ "שובר שורות", "he", "he" ],
			[ "୨୪ ଅକ୍ଟୋବର", "or", null ],	// or missing, no alternative
			[ "th", "en", null ],	// too short
		];
	}

	public function setUp() : void {
		parent::setUp();
		$tc = new \ReflectionClass( 'TextCat' );
		$classDir = dirname( $tc->getFileName() );
		if ( file_exists( "$classDir/LM" ) ) {
			// pre src move
			$this->textCatModelBaseDir = $classDir;
		} elseif ( file_exists( "$classDir/../LM" ) ) {
			// post moving TextCat class to src/
			$this->textCatModelBaseDir = $classDir . "/..";
		} else {
			throw new \RuntimeException( "Can not locate language model directory" );
		}
	}

	/**
	 * @dataProvider getLanguageTexts
	 * @param string $text
	 * @param string $language
	 * @param string $ignore
	 */
	public function testTextCatDetector( $text, $language, $ignore ) {
		$config = new HashSearchConfig( [
			'CirrusSearchTextcatModel' => [
				$this->textCatModelBaseDir . "/LM-query/",
				$this->textCatModelBaseDir . "/LM/"
			],
			'CirrusSearchTextcatLanguages' => null,
			'CirrusSearchTextcatConfig' => null,
		] );
		$textcat = new TextCat( $config );
		$detect = $textcat->detect( $text );
		$this->assertEquals( $language, $detect );
	}

	/**
	 * @dataProvider getLanguageTexts
	 * @param string $text
	 * @param string $ignore
	 * @param string $language
	 */
	public function testTextCatDetectorWithParams( $text, $ignore, $language ) {
		$config = new HashSearchConfig( [
			// only use one language model directory in old non-array format
			'CirrusSearchTextcatModel' => $this->textCatModelBaseDir . "/LM-query/",
			'CirrusSearchTextcatLanguages' => [ 'en', 'es', 'de', 'he', 'uk' ],
			'CirrusSearchTextcatConfig' => [
				'maxNgrams' => 9000,
				'maxReturnedLanguages' => 1,
				'resultsRatio' => 1.06,
				'minInputLength' => 3,
				'maxProportion' => 0.8,
				'langBoostScore' => 0.15,
				'numBoostedLangs' => 1,
			],
		] );
		$textcat = new TextCat( $config );
		$detect = $textcat->detect( $text );
		$this->assertEquals( $language, $detect );
	}

	public function testTextCatDetectorLimited() {
		$config = new HashSearchConfig( [
			'CirrusSearchTextcatModel' => [
				$this->textCatModelBaseDir . "/LM-query/",
				$this->textCatModelBaseDir . "/LM/"
			],
			'CirrusSearchTextcatLanguages' => [ "en", "ru" ],
			'CirrusSearchTextcatConfig' => null,
		] );
		$textcat = new TextCat( $config );
		$detect = $textcat->detect( "volviendose malo" );
		$this->assertEquals( "en", $detect );
	}

	public function getHttpLangs() {
		return [
			[ "en", [ "en" ], null ],
			[ "en", [ "en-UK", "en-US" ], null ],
			[ "pt", [ "pt-BR", "pt-PT" ], null ],
			[ "en", [ "en-UK", "*" ], null ],
			[ "es", [ "en-UK", "en-US" ], "en" ],
			[ "en", [ "pt-BR", "en-US" ], "pt" ],
			[ "en", [ "en-US", "pt-BR" ], "pt" ],
		];
	}
}
