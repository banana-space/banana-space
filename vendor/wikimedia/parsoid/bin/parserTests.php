<?php
require_once __DIR__ . '/../tools/Maintenance.php';

use Wikimedia\Parsoid\ParserTests\Stats;
use Wikimedia\Parsoid\ParserTests\TestRunner;
use Wikimedia\Parsoid\Tools\TestUtils;

// phpcs:ignore MediaWiki.Files.ClassMatchesFilename.WrongCase
class ParserTests extends \Wikimedia\Parsoid\Tools\Maintenance {
	use \Wikimedia\Parsoid\Tools\ExtendedOptsProcessor;

	/** @var array */
	public $processedOptions;

	public function __construct() {
		parent::__construct();
		TestUtils::setupOpts( $this );
		$this->setAllowUnregisteredOptions( false );
	}

	public function finalSetup() {
		parent::finalSetup();
		self::requireTestsAutoloader();
	}

	public function execute(): bool {
		$this->processedOptions = TestUtils::processOptions( $this );

		$testFile = $this->getArg( 0 );
		if ( $testFile ) {
			$testFilePaths = [ realpath( $testFile ) ];
		} else {
			$testFilePaths = [];
			$testFiles = json_decode( file_get_contents( __DIR__ . '/../tests/parserTests.json' ), true );
			foreach ( $testFiles as $f => $info ) {
				$testFilePaths[] = realpath( __DIR__ . '/../tests/' . $f );
			}
		}

		$globalStats = new Stats();
		$knownFailuresChanged = false;
		$exitCode = 0;
		if ( $this->processedOptions['integrated'] ?? false ) {
			// Some methods which are discouraged for normal code throw
			// exceptions unless we declare this is just a test.
			define( 'MW_PARSER_TEST', true );

			$recorder = new \MultiTestRecorder;
			// XXX should call $recorder->addRecorder(...some thunk here...)
			$testrunner = new \MWParsoid\Test\IntegratedTestRunner( $recorder, [
				'norm' => null,
				'regex' => false, // XXXX
				'keep-uploads' => false,
				'run-disabled' => false,
				'disable-save-parse' => false,
				'use-tidy-config' => false,
				'file-backend' => false,
				'upload-dir' => false,
			] );
			$ok = $testrunner->runTestsFromFiles( $testFilePaths );
			if ( !$ok ) {
				$exitCode = 1;
			}
		} else {
			foreach ( $testFilePaths as $testFile ) {
				$testRunner = new TestRunner( $testFile, $this->processedOptions['modes'] );
				$result = $testRunner->run( $this->processedOptions );
				$globalStats->accum( $result['stats'] ); // Sum all stats
				$knownFailuresChanged = $knownFailuresChanged || $result['knownFailuresChanged'];
				$exitCode = $exitCode ?: $result['exitCode'];
				if ( $exitCode !== 0 && $this->processedOptions['exit-unexpected'] ) {
					break;
				}
			}
		}

		$this->processedOptions['reportSummary'](
			[], $globalStats, null, null, $knownFailuresChanged
		);

		return $exitCode === 0;
	}
}

$maintClass = ParserTests::class;
require_once PARSOID_RUN_MAINTENANCE_IF_MAIN;
