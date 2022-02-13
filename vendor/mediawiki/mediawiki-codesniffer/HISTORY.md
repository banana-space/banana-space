# MediaWiki-Codesniffer release history #

## 34.0.0 / 2020-12-05

### New and changed sniffs ###
* Add `FinalPrivateSniff` (DannyS712)
* `FunctionCommentSniff`: Check `object` and `object[]` on union type and after fixes (Umherirrender)
* `PropertyDocumentationSniff`: Allow `@inheritDoc` to be valid documentation of class properties (Umherirrender)
* `PropertyDocumentationSniff`: Move `EmptySees` check to `EmptyTagSniff` (Umherirrender)
* Use lowercase `callable`/`callable[]` type hints in `@param`/`@return`/`@var` (Umherirrender)

### Documentation, dependencies and build changes ###
* Update `composer/semver` constraints (Reedy)
* build: Updating mediawiki/mediawiki-phan-config to 0.10.4 (Umherirrender)
* Fix rule name in HISTORY.md (Lucas Werkmeister)


## 33.0.0 / 2020-10-30

### New and changed sniffs ###
* Re-disable `PSR2.ControlStructures.SwitchDeclaration` (enabled in v32.0.0) due to poor fixer (James D. Forrester)
* Add `MediaWiki.Commenting.ClassLevelLicense` from WikibaseCodeSniffer (Thiemo Kreuz)
* `FunctionCommentSniff`: Expand to error on `object[]` typehints (DannyS712)


## 32.0.0 / 2020-10-26

### New and changed sniffs ###
* Enable `PSR12.Functions.NullableTypeDeclaration` (Umherirrender)
* Enable `PSR2.ControlStructures.SwitchDeclaration` (Ed Sanders)
* Enable `Generic.ControlStructures.DisallowYodaConditions` (Ed Sanders)
* Add RedundantVarNameSniff from WikibaseCodeSniffer (Thiemo Kreuz)
* Add AlphabeticArraySortSniff based on presence of `@phpcs-require-sorted-array` tags (Umherirrender)
* Add UnaryMinusSpacing to remove spaces after unary minus (DannyS712)
* `RedundantVarNameSniff`: Add understanding of legacy "var" keyword (Thiemo Kreuz)
* `NullableTypeSniff`: Fix handling of nullable type with default null (Arlo Breault)
* `UnusedGlobalVariablesSniff`: add support for closures (Umherirrender)
* `ExtendClassUsageSniff`: Expand subclasses of ContextSource and SpecialPage (DannyS712)
* `ForbiddenFunctionsSniff`: Disallow is_resource() (Kunal Mehta)
* `ForbiddenFunctionsSniff`: Disallow diskfreespace(), ini_alter(), and strchr() (Thiemo Kreuz)
* `FullQualifiedClassNameSniff`: Optional sniff that enforces using `use` statements (Thiemo Kreuz)
* `FunctionCommentSniff`: Handle empty type when adding null default (Umherirrender)
* `FunctionCommentSniff`: Fix handling of nullable doc with nullable type and default null (Arlo Breault)
* `FunctionCommentSniff`: Enforce lowercase primitive types (DannyS712)
* `FunctionCommentSniff`: Error on `object` typehints (DannyS712)
* Move FunctionCommentSniff annotation checks to FunctionAnnotationSniff (DannyS712)
* Move FunctionCommentSniff EmptySees check to new EmptyTagSniff (DannyS712)
* `FunctionAnnotationsSniff`: Normalize @exception to @throws (Umherirrender)
* `FunctionAnnotationsSniff`: Allow `@beforeClass` and `@afterClass` (Timo Tijhof)
* `FunctionAnnotationsSniff`: Allow @noinspection (DannyS712)
* `IsNullSniff`: handle backslash-prefixed is_null usage (Michael Moll)
* `MisleadingGlobalNamesSniff`: Add a sniff for `$wg*` variables that aren't globals (DannyS712)
* `PHPUnitAssertEqualsSniff`: Report particularly confusing assertNotEquals( false ) (Thiemo Kreuz)
* `PropertyDocumentationSniff`: Add to validate @var on class properties (Umherirrender)
* `UnusedUseStatementSniff`: Recognize used classes even if variable/type in a tag are flipped (Thiemo Kreuz)
* `UnusedUseStatementSniff`: Detect uses in @see tags (Gergő Tisza)
* Support PHP 8's T_NULLSAFE_OBJECT_OPERATOR (Umherirrender)

### Code cleanup and testing ###
* Simplify IfElseStructureSniff (Umherirrender)
* Fix possible index error in ParenthesesAroundKeywordSniff (Thiemo Kreuz)
* Fix possible index error in SpaceyParenthesisSniff (Thiemo Kreuz)
* Fix possible index error in SpaceAfterControlStructureSniff (Thiemo Kreuz)
* Fix possible index error in ReferenceThisSniff (Thiemo Kreuz)
* Fix ValidGlobalNameSniff possibly running in an endless loop (Thiemo Kreuz)
* Fix one more index error in ParenthesesAroundKeywordSniff (Thiemo Kreuz)
* Significant simplification of UnusedGlobalVariables sniff (Thiemo Kreuz)
* Full rewrite of the UnsortedUseStatementsSniff (Thiemo Kreuz)
* Set tab width correctly in test runner (Ed Sanders)
* Fix handling of comment string for MissingReturnType (Umherirrender)

### Documentation, dependencies and build changes ###
* Relax composer/semver constraint (Reedy)
* Replaced jakub-onderka/php-parallel-lint with php-parallel-lint/php-parallel-lint and updated to 1.2.0 (Umherirrender)
* Replaced jakub-onderka/php-console-highlighter with php-parallel-lint/php-console-highlighter and updated to 0.5.0 (Umherirrender)
* Update mediawiki/minus-x to 1.1.0 (Umherirrender)
* Update squizlabs/php_codesniffer to 3.5.8 (Umherirrender)
* Update mediawiki/mediawiki-phan-config to 0.10.3 (libraryupgrader)
* Declare type `phpcodesniffer-standard` in composer.json (DannyS712)


## 31.0.0 / 2020-06-22

### New and changed sniffs ###
* Add `MediaWiki.Commenting.FunctionComment.NoParamType` to ensure parameter type before parameter name (Umherirrender)
* Add `MediaWiki.Commenting.FunctionComment.NotParenthesisParamName` to make sure param name is not wrapped in parentheses (Umherirrender)
* Add `PEAR.Functions.ValidDefaultValue.NotAtEnd` to prohibit required parameters after optional ones (Max Semenik)
* Add `PSR12.Traits.UseDeclaration` to clean up the whitespace in use statements for traits (Umherirrender)
* `Generic.Files.LineLength` sniff: Add tab-width=4 and increase line length to 120 (Sam Wilson)
* `DeprecatedGlobalVariables`: Expand to cover `$wgMemc` (Daimona Eaytoy)
* `DeprecatedGlobalVariables`: Expand to cover `$wgUser` (DannyS712)
* `DeprecatedGlobalVariables`: Expand to cover `$wgVersion` (DannyS712)
* `DocComment`: Ignore self-closing doc comments `/***/` (Umherirrender)
* `FunctionAnnotations`: Update UnrecognizedAnnotation sniff for stable interface policy tags (DannyS712)
* `FunctionComment`: Ignore anon classes when looking for return (Umherirrender)
* `FunctionComment`: Reduce code duplication about short type checks (Umherirrender)
* `FunctionComment`: Sniff for boolean/integer should not be case sensitive (DannyS712)
* `LowerCamelFunctionsName`: Allow __serialize and __unserialize magic methods (addshore)
* `PHPUnitAssertEquals`: Add problematic values 1 and 1.0 (Thiemo Kreuz)
* `PlusStringConcat`: Include `+=` as well as `+` (Umherirrender)
* `SpacyParenthesis`: Fix newline detection (Umherirrender)
* `UnsortedUseStatements`: Fix failure with leading backslashes (Thiemo Kreuz)
* `UnsortedUseStatements`: Remove extra php7.4 code, fixed upstream (Umherirrender)
* `UnusedUseStatement`: Add `@property-read` and `@property-write`, per PSR 19 (Daimona Eaytoy)
* `UnusedUseStatement`: Also count uses in `@method` (DannyS712)
* `UnusedUseStatement`: Fix for complex `@phan-var` (Umherirrender)
* `UnusedUseStatement`: Fix unnecessary detection for const in same namespace (Umherirrender)
* `UnusedUseStatement`: Recognize uses in `@phan-var` comments (DannyS712)
* `UnusedUseStatement`: Update for deprecation of `@expectedException` (DannyS712)

### Code cleanup and testing ###
* `AssignmentInReturn`: Fix possible index error (Thiemo Kreuz)
* `FunctionComment`: Reduce code duplication about parenthesis check (Umherirrender)
* `FunctionComment`: Reduce code duplication about punctation check (Umherirrender)
* `FunctionComment`: Streamline a few minor details in FunctionComment sniff (Thiemo Kreuz)
* `is_null`: Add test case for `!is_null` showing that parentheses are kept (Umherirrender)
* `SpaceAfterControlStructure`: Add test (Umherirrender)
* `SpaceBeforeControlStructureBrace`: Use `T_OPEN_CURLY_BRACKET` to check for '{' (Umherirrender)
* `UnsortedUseStatements`: Fix minor performance issue (Thiemo Kreuz)
* `UnusedUseStatement`: Full rewrite; 4x faster (Thiemo Kreuz)
* `VariadicArgument`: Check index and avoid some local vars (Umherirrender)
* Split standalone `SuperfluousVariadicArgComment` out of `FunctionComment` (DannyS712)
* general: Remove unneeded parenthesis around additions (Umherirrender)
* general: Remove unused variable and fields (Umherirrender)
* general: Remove calls to File::recordMetric (Umherirrender)
* general: Use class constant instead of class properties (Umherirrender)
* general: Use parameter when building sniff message (Umherirrender)

### Documentation, dependencies and build changes ###
* Update PHPUnit to 8.5 (Umherirrender)
* Update squizlabs/php_codesniffer to 3.5.5 (Umherirrender)
* Allow composer/semver version 2.0.0 (Reedy)
* docs: Use https for http links in documentation where possible (DannyS712)
* build: Add mediawiki/mediawiki-phan-config (Umherirrender)
* build: Relax composer/spdx-licenses to ~1.5.2 (Reedy)


## 30.0.0 / 2020-02-18 ##
* `ForbiddenFunctions` sniff: Remove little bits of unused code (Thiemo Kreuz)
* `FunctionComment` sniff: Detect missing `&` for `ParamNameNoMatch` error code and autofix (Umherirrender)
* `FunctionComment` sniff: Remove a few pieces of unused code (Thiemo Kreuz)
* `FunctionComment` sniff: Simplify code utilizing isset() (Thiemo Kreuz)
* `FunctionComment` sniff: Fix sniff destroying comments mentioning other variables (Thiemo Kreuz)
* `FunctionComment` sniff: Add another test case for `@param` comments containing an `&` (Thiemo Kreuz)
* `InArrayUsage` sniff: Improve performance (Thiemo Kreuz)
* `MultipleEmptyLines` sniff: Optimize for performance (again) (Thiemo Kreuz)
* `MultipleEmptyLines` sniff: Rewrite for performance (Thiemo Kreuz)
* `MultipleEmptyLines` sniff: Detect multiple empty lines after single line comment or php open (Umherirrender)
* `UnsortedUseStatement` sniff: Fix handling other content between use statements (mainframe98)
* `UnsortedUseStatements` sniff: Consolidate duplicate code (Thiemo Kreuz)
* general: Fix wrong type of error message parameters (Thiemo Kreuz)
* general: Inline mostly meaningless `$error`/`$data` variables in many sniffs (Thiemo Kreuz)
* general: Let two use related sniffs skip non top-level scopes (Thiemo Kreuz)
* build: Remove `| sort` from `gen-changelog.sh` (Kunal Mehta)
* build: Updating composer dependencies (libraryupgrader)
* dependencies: Remove space from php version constraint in composer.json (Reedy)
* dependencies: Update composer/semver from 1.5.0 to 1.5.1 (Reedy)
* HISTORY.md: Add backticks (Ricordisamoa)


## 29.0.0 / 2020-01-07 ##
* Enforce docblock on private methods (Daimona Eaytoy)
* Treat "mixed" as already including null (Kunal Mehta)
* phpunit deprecations: Handle assertType() as well (Daimona Eaytoy)
* build: Updating mediawiki/minus-x to 0.3.2 (libraryupgrader)
* Upgrade to PHPUnit 8 (mainframe98)
* Fix inconsistent sorting in UnsortedUseStatements sniff (Thiemo Kreuz)
* Update composer/spdx-licenses from 1.5.1 to 1.5.2 (Reedy)
* Update squizlabs/php_codesniffer to 3.5.3 (Umherirrender)
* Avoid use of isset( ..., ..., [...] ) function construct / syntax (Umherirrender)
* Improve sniff for doc comments (Umherirrender)
* Minor fixups for the AssertEquals sniff (Thiemo Kreuz)
* Fix code coverage reporting for this repo (Adam Wight)
* Create PHPUnitAssertEquals sniff to find problematic assertEquals() (Thiemo Kreuz)
* Add a sniff for methods deprecated in PHPUnit 8 (Daimona Eaytoy)
* Create new sniff for doc comments (Umherirrender)
* build: Update squizlabs/php_codesniffer to 3.5.2 (Umherirrender)
* Add chop() to the list of forbidden functions (Thiemo Kreuz)
* Add a sniff to detect and autosort use statements (mainframe98)
* Allow @testWith and @doesNotPerformAssertions (Aryeh Gregor)
* Also require return type for setUpBeforeClass() and tearDownAfterClass() (Max Semenik)
* Forbid usage of is_null() (Prateek Saxena)
* Use severity instead of excludes to allow local overrides (Thiemo Kreuz)
* FunctionAnnotationsSniff: whitelist @before (Max Semenik)
* Temporarily disable the sniff for assertArraySubset (Daimona Eaytoy)


## 28.0.0 / 2019-10-09 ##
* Add a sniff to ensure that setUp and tearDown have :void typehints (Daimona Eaytoy)
* Improve checks for variargs (Daimona Eaytoy)
* Add rules for PHP71 nullable types (Daimona Eaytoy)
* Enable PSR12.Files.ImportStatement (Umherirrender)
* Enable PSR12.Properties.ConstantVisibility (Umherirrender)
* Forbid PHPUnit @expectedException* annotations (Daimona Eaytoy)
* Enable Generic.WhiteSpace.SpreadOperatorSpacingAfter (Umherirrender)
* Don't suggest to use a temporary variable for &$this (Daimona Eaytoy)
* Require PHP 7.2+ in composer.json (Daimona Eaytoy)
* Remove prohibitions on new PHP features (Max Semenik)
* Require PHPUnit 6+ (Daimona Eaytoy)


## 27.0.0 / 2019-10-03 ##
* Update squizlabs/php_codesniffer to 3.5.0 (Umherirrender)
* List @phan-assert as allowed annotation in functions (Umherirrender)
* Enforce floatval() instead of the doubleval() alias (Thiemo Kreuz)
* Add @property to work with UnusedUseStatementSniff (Umherirrender)
* Also allow @phan-template as alias of @template (Daimona Eaytoy)
* Allow @template annotations (Daimona Eaytoy)
* Allow consecutive single-line comments not to start with a single space (Daimona Eaytoy)
* Allow @slowThreshold annotation in tests (Max Semenik)


## 26.0.0 / 2019-0511 ##
* Update composer/spdx-licenses from 1.4.0 to 1.5.1 (Reedy)
* Update composer/semver from 1.4.2 to 1.5.0 (Reedy)
* Enable sniff to check for newlines between functions (Umherirrender)
* Upgrade PHP_CodeSniffer to 3.4.2 (Umherirrender)


## 25.0.0 / 2019-04-05 ##
* Prohibit aliases is_long, is_double and is_real (mainframe98)
* Streamline PHPDoc comment parsing in UnusedUseStatement sniff (Thiemo Kreuz)
* Upgrade PHP_CodeSniffer to 3.4.1 (Kunal Mehta)
* Whitelist more phan annotations (Kunal Mehta)


## 24.0.0 / 2019-02-05 ##
* Whitelist @after and @before phpunit annotations (Umherirrender)
* Update PHP_CodeSniffer to 3.4.0 (Kunal Mehta)
* Enable new Generic.VersionControl.GitMergeConflict sniff (Kunal Mehta)
* Copyedit comments (Max Semenik)
* Exclude methods in anonymous classes from the nested_functions sniff (mainframe98)
* Disallow use of @access (Umherirrender)
* Add $wgLang and $wgOut to ExtendClassUsageSniff (Umherirrender)
* Enable Generic.WhiteSpace.IncrementDecrementSpacing (Umherirrender)
* Enable Generic.Formatting.SpaceAfterNot with spacing 0 (Umherirrender)
* Require mbstring since it is needed for FunctionAnnotationsSniff (Mark A. Hershberger)
* Enable Generic.CodeAnalysis.EmptyPHPStatement (Umherirrender)
* Fix UnusedUseStatementSniff to find more unused statements (Umherirrender)
* Remove ForLoopWithTestFunctionCall (Tim Starling)
* Add a sniff to replace !! with a cast to boolean (mainframe98)
* Adjust warning text for PhpunitAnnotations.NotClassTrait sniff (Umherirrender)
* Expand ExtendClassUsageSniff to check for config globals (Umherirrender)
* Also exclude anonymous classes in AssignmentInReturnSniff (mainframe98)
* Replace sniff for forbidden globals by deprecated globals (Umherirrender)


## 23.0.0 / 2018-11-14 ##
* Add comment why @private and @protected are okay (Umherirrender)
* Add sniff to detect + for string concat (Umherirrender)
* Add sniff to detect `__METHOD__` in closures (Umherirrender)
* Fix deprecation check for compounded licenses (Umherirrender)
* Recognize MediaWikiTestCaseBase as test class (Aryeh Gregor)
* Remove [optional] from types in @param (Umherirrender)
* Update message to talk about "top level" instead of "file comment" (Thiemo Kreuz)
* Upgrade squizlabs/php_codesniffer to 3.3.2 (Kunal Mehta)


## 22.0.0 / 2018-09-02 ##
* Detect nesting of inline ternary statements without parentheses (Kevin Israel)
* Disable 'Generic.PHP.DeprecatedFunctions' sniff (Kunal Mehta)
* Enable PSR2.Classes.ClassDeclaration (Umherirrender)
* Enable Squiz.Functions.FunctionDeclarationArgumentSpacing (Umherirrender)
* Enable Squiz.PHP.NonExecutableCodeSniff (Matěj Suchánek)
* Enable Squiz.Strings.ConcatenationSpacing (Umherirrender)
* Enable Squiz.WhiteSpace.ObjectOperatorSpacing (Umherirrender)
* Fix documentation of ExtendClassUsageSniff (Matěj Suchánek)
* Cleanups to the UnusedUseStatement sniff (Thiemo Kreuz)
* Update DB_REPLICA's last version to 1.27.3 (Kunal Mehta)
* Upgrade squizlabs/php_codesniffer to 3.3.1 (Reedy)
* Whitelist @param-taint and @return-taint (Umherirrender)


## 21.0.0 / 2018-07-26 ##
* Add FunctionAnnotations checking tags in function comments only (Thiemo Kreuz)
* Add "Generic.PHP.LowerCaseType" to ruleset (Kunal Mehta)
* Add InArrayUsageSniff from Wikibase CodeSniffer (Kunal Mehta)
* Add possibility to change allowed prefixes (MGChecker)
* Automatically fix `@gropu` to `@group` (Kunal Mehta)
* Enforce format of PHP 7 UTF-8 codepoint escapes (Kevin Israel)
* ForbiddenFunctionsSniff: fix typo of "passthru" (Kevin Israel)
* Prohibit nested functions (Max Semenik)
* Update composer/spdx-licenses from 1.3.0 to 1.4.0 (Reedy)
* Upgrade squizlabs/php_codesniffer to 3.3.0 (Kunal Mehta)
* Use "PSR12.Keywords.ShortFormTypeKeywords" in place of custom sniff (Kunal Mehta)


## 20.0.0 / 2018-05-24 ##
* Require PHP 7 or HHVM to run (Kunal Mehta)
* Document why we still need to keep ScalarTypeHintUsageSniff (Kunal Mehta)
* Drop PHP7UnicodeSyntaxSniff sniff (Kunal Mehta)


## 19.0.0 / 2018-05-24 ##
Note: This will be the final release with PHP 5.5 and 5.6 support.

* Add break and continue to ParenthesesAroundKeywordSniff (Umherirrender)
* Check if the default of null is in the type list of @param (Umherirrender)
* Do not enforce name for traits with phpunit annotations (Umherirrender)
* Don't allow 'iterable' type hint (Kunal Mehta)
* Prevent usage of nullable and void type hints (Kunal Mehta)
* Prohibit PHP's vanilla execution (Max Semenik)
* Reorganize PHP 7.0 compatibility sniffs into a category (Kunal Mehta)


## 18.0.0 / 2018-04-13 ##
* Add common autofix replacements for invalid license tag sniff (Kunal Mehta)
* Add test for 'You must use "/**" style comments for a function' (Thiemo Kreuz)
* Allow @dataProvider annotations in traits (Thiemo Kreuz)
* Automatically replace DO_MAINTENANCE (Kunal Mehta)
* Don't report forbidden tags as "should be used inside test classes" (Thiemo Kreuz)
* Don't require documenting self-explaining parameter-less functions (Thiemo Kreuz)
* Fix IllegalSingleLineComment sniff fix for unclosed comments (Thiemo Kreuz)
* Make unused global variables sniff much more robust (Thiemo Kreuz)
* Minor performance optimizations to the UnusedUseStatement sniff (Thiemo Kreuz)
* Optimize PHPUnitClassUsage sniff for performance (Thiemo Kreuz)
* Optimize PrefixedGlobalFunctions sniff for performance (Thiemo Kreuz)
* Optimize ShortCastSyntax sniff for performance (Thiemo Kreuz)
* Scan for return tags from the end of the function scope (Thiemo Kreuz)
* Shorten out earlier in the DbrQueryUsage sniff (Thiemo Kreuz)
* Shorten out earlier in the FunctionComment sniff (Thiemo Kreuz)


## 17.0.0 / 2018-03-28 ##
* Allow globals to start with numbers (Umherirrender)
* Check for close parenthesis first and shorten out earlier (Thiemo Kreuz)
* Check for use statement with non-compound name (Umherirrender)
* Don't require @covers from abstract tests (Max Semenik)
* Forbid usage of assert() (Kunal Mehta)
* Improve ClassMatchesFilenameSniff::isMaintenanceScript (Umherirrender)
* Improve performance of PrefixedGlobalFunctionsSniff (Umherirrender)
* Optimize ClassMatchesFilename sniff for performance (Thiemo Kreuz)
* Optimize DirUsage sniff for performance (Thiemo Kreuz)
* Optimize MultipleEmptyLines sniff for performance (Thiemo Kreuz)
* Remove warning about "missing" @param comments (Thiemo Kreuz)
* Simplify UnusedGlobalVariables sniff (Thiemo Kreuz)
* Skip closing parentheses in "( )" and "[ ]" instead of rechecking (Thiemo Kreuz)
* Skip empty () and [], not processing closing token a second time (Thiemo Kreuz)
* Update squizlabs/php_codesniffer to 3.2.3 (Kunal Mehta)
* Use faster array_key_exists instead of in_array in 2 sniffs (Thiemo Kreuz)
* Use faster strcasecmp() instead of strtolower() for comparisons (Thiemo Kreuz)
* Use File::findExtendedClassName to get extends name (Umherirrender)
* Use File::getDeclarationName to get the function name (Umherirrender)
* Use File::getMethodProperties to get visibility (Umherirrender)
* Validate @license against SPDX (Umherirrender)
* Validate phpunit annotations (Umherirrender)


## 16.0.1 / 2018-02-24 ##
* Fix SpaceBeforeControlStructureBraceSniff (Thiemo Kreuz)
* Grade errors about duplicate spaces in PHPDoc tags down to warnings (Thiemo Kreuz)
* Remove unused code and function arguments from three sniffs (Thiemo Kreuz)
* Replace substr_compare with substr (Umherirrender)
* Streamline SpaceBeforeControlStructureBraceSniff implementation (Thiemo Kreuz)


## 16.0.0 / 2018-02-14 ##
* Add sniff to find tests without @covers tags (Kunal Mehta)
* Add sniff to use namespaced PHPUnit\Framework\TestCase (Kunal Mehta)
* Avoid assignment in return statements (Umherirrender)
* Be aware of extension MediaWiki compatibility (Kunal Mehta)
* Detect variadic arguments in function comments (Umherirrender)
* Disallow PHP 7.2+ `object` type-hint (Kunal Mehta)
* Downgrade "Missing parameter comment" to warning (Reedy)
* Expand sniff to replace some php aliases with main function (Umherirrender)
* Fix SpaceyParenthesisSniff comment detection for ignore statements (Umherirrender)
* Fix Undefined index: scope_opener in IfElseStructureSniff (Umherirrender)
* Forbid parse_str() without a second argument (Umherirrender)
* Remove direction from @param (Umherirrender)
* Remove unneeded closing declaration comments (Umherirrender)
* Remove unneeded @codingStandardsIgnoreLine from test (Umherirrender)
* Skip __construct on checking for @return tags (Umherirrender)
* Use SPDX 3.0 license identifier (Kunal Mehta)
* Warn on usage of each() (Kunal Mehta)


## 15.0.0 / 2017-12-29 ##
* Add sniff for using is_int over is_integer (Kunal Mehta)
* Allow _ in unit test method names (Gergő Tisza)
* Check function definitions for the same variable name (Kunal Mehta)
* Fix handling of alternative if in IfElseStructureSniff (Umherirrender)
* Forbid usage of extract() (Kunal Mehta)
* Ignore maintenance scripts in ClassMatchesFilenameSniff (Kunal Mehta)
* Improve PHPDoc classname parsing (Gergő Tisza)
* Move phpcs.xml to .phpcs.xml (Umherirrender)
* Remove WhiteSpace.SpaceBeforeSingleLineComment.EmptyComment (Gergő Tisza)
* Replace PEAR with Packagist in README.md link (Ricordisamoa)
* Require that an explicit visiblity is set on methods and properties (Kunal Mehta)
* Rework ExtendClassUageSniff to avoid private class member (Umherirrender)
* Skip inner functions in FunctionCommentSniff::processReturn (Umherirrender)
* Update PHP_CodeSniffer to 3.2.2 (Ricordisamoa, Kunal Mehta)
* Use backticks in HISTORY.md (Ricordisamoa)
* Use only PSR2.Files.EndFileNewline (Kunal Mehta)
* Use upstream Generic.Files.OneObjectStructurePerFile sniff (Kunal Mehta)
* Use upstream Generic.PHP.DiscourageGoto (Kunal Mehta)
* Warn on usage of create_function() (Kunal Mehta)


## 14.1.0 / 2017-10-20 ##
* Update PHP_CodeSniffer to 3.1.1 (Paladox)


## 14.0.0 / 2017-10-20 ##
* Add sniff for @params instead of @param (Umherirrender)
* Better distinguish "one space before brace" and "brace on same line" (Florian Schmidt)
* Typo fix in docs (MarcoAurelio)
* Unwrap types in function docs from {} (Umherirrender)
* Update PHP_CodeSniffer to 3.1.0 from 3.0.2 (Paladox)
* Validate doc syntax (Umherirrender)


## 13.0.0 / 2017-09-23 ##
* Add sniff for @cover instead of @covers (James D. Forrester)
* Add sniff to find and replace deprecated constants (Kunal Mehta)
* Add sniff to find unused "use" statements (Kunal Mehta)
* Add space after keyword require_once, if needed (Umherirrender)
* Fix @returns and @throw in function docs (Umherirrender)
* Prohibit some globals (Max Semenik)
* Skip function comments with @deprecated (Umherirrender)
* Sniff & fix lowercase @inheritdoc (Gergő Tisza)


## 0.12.0 / 2017-08-29 ##
* Add sniff to ensure floats have a leading `0` if necessary (Kunal Mehta)
* Add sniff to ensure the class name matches the filename (Kunal Mehta)
* Change bootstrap-ci.php to match PHP CodeSniffer 3.0.0 (Umherirrender)
* Check for unneeded punctation in @param and @return (Umherirrender)
* Check spacing after type in @return (Umherirrender)
* Check spacing before type in @param and @return (Umherirrender)
* Clean up test helpers (Kunal Mehta)
* Do not mess long function comments on composer fix (Umherirrender)
* Enforce "short" type definitions in multi types in function comments (Umherirrender)
* Make it easier to figure out which test failed (Kunal Mehta)
* phpunit: replace deprecated strict=true (Umherirrender)
* Remove GoatSniffer integration (Kunal Mehta)
* Remove unmatched @codingStandardsIgnoreEnd (Umherirrender)
* Rename OpeningKeywordBracketSniff to OpeningKeywordParenthesisSniff (Reedy)
* Use local OneClassPerFile sniff for only one class/interface/trait (Kunal Mehta)


## 0.11.1 / 2017-08-13 ##
* Add GoatSniffer ASCII art (Kunal Mehta)


## 0.11.0 / 2017-08-10 ##
* Added OpeningKeywordBraceSniff (Umherirrender)
* Add sniff to forbid PHP 7 scalar type hints (Kunal Mehta)
* Enable Squiz.WhiteSpace.OperatorSpacing (Umherirrender)
* Enforce "short" type definitions on @param in comments (Umherirrender)
* Fix phpunit test on windows (Umherirrender)
* Fix Undefined offset in FunctionCommentSniff (Umherirrender)


## 0.10.1 / 2017-07-22 ##
* Add .gitattributes (Umherirrender)
* Add Squiz.Classes.SelfMemberReference to ruleset (Kunal Mehta)
* build: Added php-console-highlighter (Umherirrender)
* Don't ignore files or paths with "git" in them, only .git (Kunal Mehta)
* Fix exclude of common folders (Umherirrender)
* Fix "Undefined index: scope_opener" in SpaceBeforeClassBraceSniff (Reedy)
* Forbid backtick operator (Matthew Flaschen)
* Ignore returns in closures for MissingReturn sniff (Kunal Mehta)
* PHP CodeSniffer on CI should only lint HEAD (Antoine Musso)
* Reduce false positives in ReferenceThisSniff (Kunal Mehta)
* Sniff that the short type form is used in @return tags (Kunal Mehta)
* Swap isset() === false to !isset() (Reedy)
* track=1 rather than defaultbranch (Reedy)
* Update PHP_CodeSniffer to 3.0.2 (Kunal Mehta)


## 0.10.0 / 2017-07-01 ##
* Add sniff to prevent against using PHP 7's Unicode escape syntax (Kunal Mehta)
* Add sniff to verify type-casts use the short form (bool, int) (Kunal Mehta)
* Add sniff for `&$this` that causes warnings in PHP 7.1 (Kunal Mehta)
* Clean up DbrQueryUsageSniff (Umherirrender)
* Ensure all FunctionComment sniff codes are standard (Kunal Mehta)
* Exclude common folders (Umherirrender)
* Fix handling of nested parenthesis in ParenthesesAroundKeywordSniff (Kunal Mehta)
* IllegalSingleLineCommentSniff: Check return value of strrpos strictly (Kunal Mehta)
* Improve handling of multi-line class declarations (Kunal Mehta)
* Include sniff warning/error codes in test output (Kunal Mehta)
* Make DisallowEmptyLineFunctionsSniff apply to closures too (Kunal Mehta)
* Use correct notation for UTF-8 (Umherirrender)


## 0.9.0 / 2017-06-19 ##
* Add sniff to enforce "function (" for closures (Kunal Mehta)
* Add usage of && in generic_pass (addshore)
* Disallow `and` and `or` (Kunal Mehta)
* Don't require documentation for constructors without parameters (Kunal Mehta)
* Don't require documentation for '__toString' (Kunal Mehta)
* Don't require return/throws/param for doc blocks with @inheritDoc (Kunal Mehta)
* Expand list of standard methods that don't need documentation (Kunal Mehta)
* Fix FunctionComment.Missing sniff code (Kunal Mehta)
* Fix indentation (Umherirrender)
* Fix WhiteSpace/SpaceAfterClosureSniff (Antoine Musso)
* Make sure all files end with a newline (Kunal Mehta)
* test: ensure consistent report width (Antoine Musso)
* Update for CodeSniffer 3.0 (Kunal Mehta)
* Update squizlabs/PHP_CodeSniffer to 3.0.1 (Reedy)
* Use upstream CharacterBeforePHPOpeningTag sniff (Kunal Mehta)


## 0.8.0 / 2017-05-03 ##
* Add sniff for cast operator spacing (Sam Wilson)
* Allow filtering documentation requirements based on visibility (Kunal Mehta)
* Don't require documentation for test cases (Kunal Mehta)
* Don't require @return annotations for plain "return;" (Kunal Mehta)
* Explicitly check for method structure before using (Sam Wilson)
* Fix test result parsing, and correct new errors that were exposed (Sam Wilson)
* Prevent abstract functions being marked eligible (Sam Wilson)
* PHP_CodeSniffer to 2.9.0 (Paladox)


## 0.8.0-alpha.1 / 2016-09-21 ##
* Add detection for calling global functions in target classes. (Tao Xie)
* Add function commenting sniff. (Lethexie)
* Add .idea directory to .gitignore (Florian Schmidt)
* Add sniff to confirm function name using lower camel case. (Lethexie)
* Add test to verify SpaceBeforeClassBraceSniff handles extends (Kunal Mehta)
* Add the SpaceBeforeClassBraceSniff (Lethe)
* Add the SpaceBeforeControlStructureBraceSniff (Lethexie)
* Add usage to forbid superglobals like $_GET,$_POST (Lethe)
* Comments should start with new line. (Lethe)
* Disallow parenthesis around keywords like clone or require (Florian)
* Enable PSR2.Methods.FunctionClosingBrace sniff (Kunal Mehta)
* Fix reference parameters warning and no return function need return tag (Lethe)
* Fix single space expected on single line comment. (Lethexie)
* Make sure no empty line at the begin of the function. (Lethexie)
* Put failed examples and passed examples into a file. (Lethexie)
* Report warnings when $dbr->query() is used instead of $dbr->select(). (Tao Xie)
* Single Line comments no multiple '*'. (Lethe)
* Update squizlabs/php_codesniffer to 2.7.0 (Paladox)


## 0.7.2 / 2016-05-27 ##
* SpaceyParenthesisSniff: Don't remove last argument or array element (Kevin Israel)
* Expect specific output from sniffs (Erik Bernhardson)
* Assert fixers do as intended (Erik Bernhardson)


## 0.7.1 / 2016-05-06 ##
* Fix typo in IfElseStructureSniff (addshore)


## 0.7.0 / 2016-05-06 ##
* Also check for space after elseif in SpaceAfterControlStructureSniff (Lethexie)
* Factor our tokenIsNamespaced method (addshore)
* Make IfElseStructureSniff can detect and fix multiple white spaces after else (Lethexie)
* Make SpaceyParenthesisSniff can fix multiple white spaces between parentheses (Lethexie)
* Make spacey parenthesis sniff work with short array syntax (Kunal Mehta)
* Speed up PrefixedGlobalFunctionsSniff (addshore)
* Update squizlabs/php_codesniffer to 2.6.0 (Paladox)


## 0.6.0 / 2016-02-17 ##
* Add Generic.Arrays.DisallowLongArraySyntax to ruleset, autofix this repo (Kunal Mehta)
* Add sniff to detect consecutive empty lines in a file (Vivek Ghaisas)
* Disable Generic.Functions.CallTimePassByReference.NotAllowed (Kunal Mehta)
* Update squizlabs/php_codesniffer to 2.5.1 (Paladox)


## 0.5.1 / 2015-12-28 ##
* Avoid in_array for performance reasons (Thiemo Kreuz)
* build: Pass -s to phpcs for easier debugging (Kunal Mehta)
* Remove dead code from SpaceBeforeSingleLineCommentSniff (Thiemo Kreuz)
* Revert "CharacterBeforePHPOpeningTagSniff: Support T_HASHBANG for HHVM >=3.5,<3.7" (Legoktm)
* Simplify existing regular expressions (Thiemo Kreuz)
* build: Update phpunit to 4.8.18 (Paladox)
* Update squizlabs/php_codesniffer to 2.5.0 (Paladox)


## 0.5.0 / 2015-10-23 ##
* Add Generic.ControlStructures.InlineControlStructure to ruleset (Kunal Mehta)
* Add IfElseStructureSniff to handle else structures (TasneemLo)
* Handle multiple # comments in Space Before Comment (TasneemLo)
* Sniff to check assignment in while & if (TasneemLo)
* Sniff to warn when using `dirname(__FILE__)` (TasneemLo)


## 0.4.0 / 2015-09-26 ##
* Use upstream codesniffer 2.3.4 (Kunal Mehta & Paladox)
* Sniff to check for space in single line comments (Smriti.Singh)
* Automatically fix warnings caught by SpaceyParenthesisSniff (Kunal Mehta)
* Automatically fix warnings caught by SpaceAfterControlStructureSniff (Kunal Mehta)
* Add ignore list to PrefixedGlobalFunctionsSniff (Vivek Ghaisas)
* Add ignore list to ValidGlobalNameSniff (Vivek Ghaisas)
* Update jakub-onderka/php-parallel-lint to 0.9.* (Paladox)
* Automatically fix warnings caught by SpaceBeforeSingleLineCommentSniff (Kunal Mehta)


## 0.3.0 / 2015-06-19 ##
* Update README.md code formatting (Vivek Ghaisas)
* Don't require "wf" prefix on functions that are namespaced (Kunal Mehta)
* Simplify PHPUnit boostrap, require usage of composer for running tests (Kunal Mehta)
* SpaceyParenthesis: Check for space before opening parenthesis (Vivek Ghaisas)
* SpaceyParenthesesSniff: Search for extra/unnecessary space (Vivek Ghaisas)
* CharacterBeforePHPOpeningTagSniff: Support T_HASHBANG for HHVM >=3.5,<3.7 (Kunal Mehta)


## 0.2.0 / 2015-06-02 ##
* Fixed sniff that checks globals have a "wg" prefix (Divya)
* New sniff to detect unused global variables (Divya)
* New sniff to detect text before first opening php tag (Sumit Asthana)
* New sniff to detect alternative syntax such as "endif" (Vivek Ghaisas)
* New sniff to detect unprefixed global functions (Vivek Ghaisas)
* New sniff to detect "goto" usage (Harshit Harchani)
* Update ignore with some emacs files. (Mark A. Hershberger)
* Use upstream codesniffer 2.3.0 (Kunal Mehta)
* Make mediawiki/tools/codesniffer pass phpcs (Vivek Ghaisas)
* New sniff to check for spacey use of parentheses (Kunal Mehta)
* Modify generic pass test with a case of not-spacey parentheses (Vivek Ghaisas)
* Make failing tests fail only for specific respective reasons (Vivek Ghaisas)
* Change certain errors to warnings (Vivek Ghaisas)
* Update ExtraCharacters Sniff to allow shebang (Harshit Harchani)


## 0.1.0 / 2015-01-05 ##

* Initial tagged release
