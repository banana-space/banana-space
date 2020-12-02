<?php

class AutoLoaderStructureTest extends MediaWikiIntegrationTestCase {
	/**
	 * Assert that there were no classes loaded that are not registered with the AutoLoader.
	 *
	 * For example foo.php having class Foo and class Bar but only registering Foo.
	 * This is important because we should not be relying on Foo being used before Bar.
	 */
	public function testAutoLoadConfig() {
		$results = self::checkAutoLoadConf();

		$this->assertEquals(
			$results['expected'],
			$results['actual']
		);
	}

	private static function parseFile( $contents ) {
		// We could use token_get_all() here, but this is faster
		// Note: Keep in sync with ClassCollector
		$matches = [];
		preg_match_all( '/
				^ [\t ]* (?:
					(?:final\s+)? (?:abstract\s+)? (?:class|interface|trait) \s+
					(?P<class> \w+)
				|
					class_alias \s* \( \s*
						([\'"]) (?P<original> [^\'"]+) \g{-2} \s* , \s*
						([\'"]) (?P<alias> [^\'"]+ ) \g{-2} \s*
					\) \s* ;
				|
					class_alias \s* \( \s*
						(?P<originalStatic> [\w\\\\]+)::class \s* , \s*
						([\'"]) (?P<aliasString> [^\'"]+ ) \g{-2} \s*
					\) \s* ;
				)
			/imx', $contents, $matches, PREG_SET_ORDER );

		$namespaceMatch = [];
		preg_match( '/
				^ [\t ]*
					namespace \s+
						(\w+(\\\\\w+)*)
					\s* ;
			/imx', $contents, $namespaceMatch );
		$fileNamespace = $namespaceMatch ? $namespaceMatch[1] . '\\' : '';

		$classesInFile = [];
		$aliasesInFile = [];

		foreach ( $matches as $match ) {
			if ( !empty( $match['class'] ) ) {
				// 'class Foo {}'
				$class = $fileNamespace . $match['class'];
				$classesInFile[$class] = true;
			} elseif ( !empty( $match['original'] ) ) {
				// 'class_alias( "Foo", "Bar" );'
				$aliasesInFile[self::removeSlashes( $match['alias'] )] = $match['original'];
			} else {
				// 'class_alias( Foo::class, "Bar" );'
				$aliasesInFile[self::removeSlashes( $match['aliasString'] )] =
					$fileNamespace . $match['originalStatic'];
			}
		}

		return [ $classesInFile, $aliasesInFile ];
	}

	private static function removeSlashes( $str ) {
		return str_replace( '\\\\', '\\', $str );
	}

	protected static function checkAutoLoadConf() {
		global $wgAutoloadLocalClasses, $wgAutoloadClasses, $IP;

		// wgAutoloadLocalClasses has precedence, just like in includes/AutoLoader.php
		$expected = $wgAutoloadLocalClasses + $wgAutoloadClasses;
		$actual = [];

		$psr4Namespaces = [];
		foreach ( AutoLoader::getAutoloadNamespaces() as $ns => $path ) {
			$psr4Namespaces[rtrim( $ns, '\\' ) . '\\'] = rtrim( $path, '/' );
		}

		foreach ( $expected as $class => $file ) {
			// Only prefix $IP if it doesn't have it already.
			// Generally local classes don't have it, and those from extensions and test suites do.
			if ( substr( $file, 0, 1 ) != '/' && substr( $file, 1, 1 ) != ':' ) {
				$filePath = "$IP/$file";
			} else {
				$filePath = $file;
			}

			if ( !file_exists( $filePath ) ) {
				$actual[$class] = "[file '$filePath' does not exist]";
				continue;
			}

			Wikimedia\suppressWarnings();
			$contents = file_get_contents( $filePath );
			Wikimedia\restoreWarnings();

			if ( $contents === false ) {
				$actual[$class] = "[couldn't read file '$filePath']";
				continue;
			}

			list( $classesInFile, $aliasesInFile ) = self::parseFile( $contents );

			foreach ( $classesInFile as $className => $ignore ) {
				// Skip if it's a PSR4 class
				$parts = explode( '\\', $className );
				for ( $i = count( $parts ) - 1; $i > 0; $i-- ) {
					$ns = implode( '\\', array_slice( $parts, 0, $i ) ) . '\\';
					if ( isset( $psr4Namespaces[$ns] ) ) {
						$expectedPath = $psr4Namespaces[$ns] . '/'
							. implode( '/', array_slice( $parts, $i ) )
							. '.php';
						if ( $filePath === $expectedPath ) {
							continue 2;
						}
					}
				}

				// Nope, add it.
				$actual[$className] = $file;
			}

			// Only accept aliases for classes in the same file, because for correct
			// behavior, all aliases for a class must be set up when the class is loaded
			// (see <https://bugs.php.net/bug.php?id=61422>).
			foreach ( $aliasesInFile as $alias => $class ) {
				if ( isset( $classesInFile[$class] ) ) {
					$actual[$alias] = $file;
				} else {
					$actual[$alias] = "[original class not in $file]";
				}
			}
		}

		return [
			'expected' => $expected,
			'actual' => $actual,
		];
	}

	public function testAutoloadOrder() {
		$path = __DIR__ . '/../../..';
		$oldAutoload = file_get_contents( $path . '/autoload.php' );
		$generator = new AutoloadGenerator( $path, 'local' );
		$generator->setPsr4Namespaces( AutoLoader::getAutoloadNamespaces() );
		$generator->initMediaWikiDefault();
		$newAutoload = $generator->getAutoload( 'maintenance/generateLocalAutoload.php' );

		$this->assertEquals( $oldAutoload, $newAutoload, 'autoload.php does not match' .
			' output of generateLocalAutoload.php script.' );
	}
}
