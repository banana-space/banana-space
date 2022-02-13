<?php
/**
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
 * @file
 */

$IP = getenv( 'MW_INSTALL_PATH' ) !== false
	// Replace \\ by / for windows users to let exclude work correctly
	? str_replace( '\\', '/', getenv( 'MW_INSTALL_PATH' ) )
	: '../..';

$VP = getenv( 'MW_VENDOR_PATH' ) !== false
	// Replace \\ by / for windows users to let exclude work correctly
	? str_replace( '\\', '/', getenv( 'MW_VENDOR_PATH' ) )
	: $IP;

/**
 * This configuration will be read and overlayed on top of the
 * default configuration. Command line arguments will be applied
 * after this file is read.
 *
 * @see src/Phan/Config.php
 * See Config for all configurable options.
 *
 * A Note About Paths
 * ==================
 *
 * Files referenced from this file should be defined as
 *
 * ```
 *   Config::projectPath('relative_path/to/file')
 * ```
 *
 * where the relative path is relative to the root of the
 * project which is defined as either the working directory
 * of the phan executable or a path passed in via the CLI
 * '-d' flag.
 */
$baseCfg = [
	/**
	 * A list of individual files to include in analysis
	 * with a path relative to the root directory of the
	 * project. directory_list won't find .inc files so
	 * we augment it here.
	 */
	'file_list' => defined( 'MSG_EOR' ) ? [] : [ __DIR__ . '/stubs/sockets.windows.php' ],

	/**
	 * A list of directories that should be parsed for class and
	 * method information. After excluding the directories
	 * defined in exclude_analysis_directory_list, the remaining
	 * files will be statically analyzed for errors.
	 *
	 * Thus, both first-party and third-party code being used by
	 * your application should be included in this list.
	 */
	'directory_list' => array_filter( [
		'includes/',
		'src/',
		'maintenance/',
		'.phan/stubs/',
		$IP . '/includes',
		$IP . '/languages',
		$IP . '/maintenance',
		$IP . '/.phan/stubs/',
		$VP . '/vendor',
	], 'file_exists' ),

	/**
	 * A file list that defines files that will be excluded
	 * from parsing and analysis and will not be read at all.
	 *
	 * This is useful for excluding hopelessly unanalyzable
	 * files that can't be removed for whatever reason.
	 */
	'exclude_file_list' => [
	],

	/**
	 * A list of directories holding code that we want
	 * to parse, but not analyze. Also works for individual
	 * files.
	 */
	"exclude_analysis_directory_list" => [
		'.phan/stubs/',
		$IP . '/includes',
		$IP . '/languages',
		$IP . '/maintenance',
		$IP . '/.phan/stubs/',
		$VP . '/vendor',
	],

	/**
	 * A regular expression to match files to be excluded
	 * from parsing and analysis and will not be read at all.
	 *
	 * This is useful for excluding groups of test or example
	 * directories/files, unanalyzable files, or files that
	 * can't be removed for whatever reason.
	 * (e.g. '@Test\.php$@', or '@vendor/.*\/(tests|Tests)/@')
	 */
	'exclude_file_regex' =>
		'@vendor/(' .
			// Exclude known dev dependencies
			'(' . implode( '|', [
				'composer/installers',
				'jakub-onderka/php-console-color',
				'jakub-onderka/php-console-highlighter',
				'jakub-onderka/php-parallel-lint',
				'mediawiki/mediawiki-codesniffer',
				'microsoft/tolerant-php-parser',
				'phan/phan',
				'phpunit/php-code-coverage',
				'squizlabs/php_codesniffer',
			] ) . ')' .
		'|' .
			// Also exclude tests folder from dependencies
			'.*/[Tt]ests?' .
		')/@',

	/**
	 * Backwards Compatibility Checking. This is slow
	 * and expensive, but you should consider running
	 * it before upgrading your version of PHP to a
	 * new version that has backward compatibility
	 * breaks.
	 */
	'backward_compatibility_checks' => false,

	/**
	 * A set of fully qualified class-names for which
	 * a call to parent::__construct() is required
	 */
	'parent_constructor_required' => [
	],

	/**
	 * Run a quick version of checks that takes less
	 * time at the cost of not running as thorough
	 * an analysis. You should consider setting this
	 * to true only when you wish you had more issues
	 * to fix in your code base.
	 *
	 * In quick-mode the scanner doesn't rescan a function
	 * or a method's code block every time a call is seen.
	 * This means that the problem here won't be detected:
	 *
	 * ```php
	 * <?php
	 * function test($arg):int {
	 * 	return $arg;
	 * }
	 * test("abc");
	 * ```
	 *
	 * This would normally generate:
	 *
	 * ```sh
	 * test.php:3 TypeError return string but `test()` is declared to return int
	 * ```
	 *
	 * The initial scan of the function's code block has no
	 * type information for `$arg`. It isn't until we see
	 * the call and rescan test()'s code block that we can
	 * detect that it is actually returning the passed in
	 * `string` instead of an `int` as declared.
	 */
	'quick_mode' => false,

	/**
	 * If enabled, check all methods that override a
	 * parent method to make sure its signature is
	 * compatible with the parent's. This check
	 * can add quite a bit of time to the analysis.
	 */
	'analyze_signature_compatibility' => true,

	// Emit all issues. They are then suppressed via
	// suppress_issue_types, rather than a minimum
	// severity.
	"minimum_severity" => 0,

	/**
	 * If true, missing properties will be created when
	 * they are first seen. If false, we'll report an
	 * error message if there is an attempt to write
	 * to a class property that wasn't explicitly
	 * defined.
	 */
	'allow_missing_properties' => false,

	/**
	 * Allow null to be cast as any type and for any
	 * type to be cast to null. Setting this to true
	 * will cut down on false positives.
	 */
	'null_casts_as_any_type' => false,

	/**
	 * If enabled, scalars (int, float, bool, string, null)
	 * are treated as if they can cast to each other.
	 *
	 * MediaWiki is pretty lax and uses many scalar
	 * types interchangably, hence single repos may choose to set this to true.
	 */
	'scalar_implicit_cast' => false,

	/**
	 * If true, seemingly undeclared variables in the global
	 * scope will be ignored. This is useful for projects
	 * with complicated cross-file globals that you have no
	 * hope of fixing.
	 */
	'ignore_undeclared_variables_in_global_scope' => false,

	/**
	 * Set to true in order to attempt to detect dead
	 * (unreferenced) code. Keep in mind that the
	 * results will only be a guess given that classes,
	 * properties, constants and methods can be referenced
	 * as variables (like `$class->$property` or
	 * `$class->$method()`) in ways that we're unable
	 * to make sense of.
	 */
	'dead_code_detection' => false,

	/**
	 * If true, the dead code detection rig will
	 * prefer false negatives (not report dead code) to
	 * false positives (report dead code that is not
	 * actually dead) which is to say that the graph of
	 * references will create too many edges rather than
	 * too few edges when guesses have to be made about
	 * what references what.
	 */
	'dead_code_detection_prefer_false_negative' => true,

	/**
	 * If disabled, Phan will not read docblock type
	 * annotation comments (such as for @return, @param,
	 * @var, @suppress, @deprecated) and only rely on
	 * types expressed in code.
	 */
	'read_type_annotations' => true,

	/**
	 * Set to true in order to ignore issue suppression.
	 * This is useful for testing the state of your code, but
	 * unlikely to be useful outside of that.
	 */
	'disable_suppression' => false,

	/**
	 * If set to true, we'll dump the AST instead of
	 * analyzing files
	 */
	'dump_ast' => false,

	/**
	 * If set to a string, we'll dump the fully qualified lowercase
	 * function and method signatures instead of analyzing files.
	 */
	'dump_signatures_file' => null,

	// Include a progress bar in the output
	'progress_bar' => false,

	/**
	 * The number of processes to fork off during the analysis
	 * phase.
	 */
	'processes' => 1,

	/**
	 * Add any issue types (such as 'PhanUndeclaredMethod')
	 * to this black-list to inhibit them from being reported.
	 */
	'suppress_issue_types' => [
		'PhanDeprecatedFunction',
		'PhanDeprecatedClass',
		'PhanDeprecatedClassConstant',
		'PhanDeprecatedFunctionInternal',
		'PhanDeprecatedInterface',
		'PhanDeprecatedProperty',
		'PhanDeprecatedTrait',
		'PhanUnreferencedUseNormal',

		// https://github.com/phan/phan/issues/3420
		'PhanAccessClassConstantInternal',
		'PhanAccessClassInternal',
		'PhanAccessConstantInternal',
		'PhanAccessMethodInternal',
		'PhanAccessPropertyInternal',
	],

	/**
	 * If empty, no filter against issues types will be applied.
	 * If this white-list is non-empty, only issues within the list
	 * will be emitted by Phan.
	 */
	'whitelist_issue_types' => [
	],

	/**
	 * Override to hardcode existence and types of (non-builtin) globals in the global scope.
	 * Class names must be prefixed with '\\'.
	 * (E.g. ['_FOO' => '\\FooClass', 'page' => '\\PageClass', 'userId' => 'int'])
	 */
	'globals_type_map' => [
	],

	// Emit issue messages with markdown formatting
	'markdown_issue_messages' => false,

	/**
	 * Enable or disable support for generic templated
	 * class types.
	 */
	'generic_types_enabled' => true,

	// Enable class_alias() support
	'enable_class_alias_support' => true,

	// A list of plugin files to execute
	'plugins' => [
		'PregRegexCheckerPlugin',
		'UnusedSuppressionPlugin',
		'DuplicateExpressionPlugin',
	],
	'plugin_config' => [],

	/**
	 * Set to true in order to attempt to detect redundant and impossible conditions.
	 *
	 * This has some false positives involving loops,
	 * variables set in branches of loops, and global variables.
	 */
	'redundant_condition_detection' => true
];

// Hacky variable to quickly disable taint-check if something explodes.
// @note This is **NOT** a stable feature. It's only for BC and could be removed or changed
// without prior notice.
if ( !isset( $disableTaintCheck ) ) {
	$taintCheckPath = __DIR__ . "/../../phan-taint-check-plugin/MediaWikiSecurityCheckPlugin.php";
	if ( !file_exists( $taintCheckPath ) ) {
		$taintCheckPath = "$VP/vendor/mediawiki/phan-taint-check-plugin/MediaWikiSecurityCheckPlugin.php";
	}
	$baseCfg['plugins'][] = $taintCheckPath;
	// Taint-check specific settings. NOTE: don't remove these lines, even if they duplicate some of
	// the settings above. taint-check may fail hard if one of these settings goes missing.
	$baseCfg['quick_mode'] = false;
	$baseCfg['record_variable_context_and_scope'] = true;
	$baseCfg['suppress_issue_types'] = array_merge(
		$baseCfg['suppress_issue_types'],
		[
			// We obviously don't want to report false positives
			'SecurityCheck-LikelyFalsePositive',
			// This one still has a lot of false positives
			'SecurityCheck-PHPSerializeInjection',
		]
	);
} else {
	// BC code to avoid failures in case of emergency when taint-check is disabled.
	$baseCfg['plugin_config']['unused_suppression_ignore_list'] = [
		'SecurityCheck-DoubleEscaped',
		'SecurityCheck-OTHER',
		'SecurityCheck-SQLInjection',
		'SecurityCheck-XSS',
	];
}
return $baseCfg;
