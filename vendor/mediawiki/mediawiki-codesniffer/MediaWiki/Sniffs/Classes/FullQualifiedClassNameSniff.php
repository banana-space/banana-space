<?php

namespace MediaWiki\Sniffs\Classes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Custom sniff that disallows full qualified class names outside of the "use …" section.
 * Exceptions:
 * - Full qualified class names in the main namespace (e.g. \Title) are allowed, by default. This is
 *   a very common code style in MediaWiki-related codebases. Removing these backslash characters
 *   doesn't really make code better readable.
 * - Full qualified class names in "extends" and "implements" are allowed, by default. This is a
 *   very common code style, especially in tests. Since these can only appear once per class, and
 *   are guaranteed to be at the very top, moving them to "use" statements doesn't relly make code
 *   better readable.
 * - Function calls like \Wikimedia\suppressWarnings() are allowed, by default.
 *
 * Note this sniff is disabled by default. You need to increase it's severity in your .phpcs.xml to
 * enable it.
 *
 * Example configuration:
 * <rule ref="MediaWiki.Classes.FullQualifiedClassName">
 *     <severity>5</severity>
 *     <properties>
 *         <property name="allowMainNamespace" value="false" />
 *         <property name="allowInheritance" value="false" />
 *         <property name="allowFunctions" value="false" />
 *     </properties>
 * </rule>
 *
 * Note this sniff currently does not check class names mentioned in PHPDoc comments.
 *
 * @author Thiemo Kreuz
 */
class FullQualifiedClassNameSniff implements Sniff {

	/**
	 * @var bool Allows full qualified class names in the main namespace, e.g. \Title
	 */
	public $allowMainNamespace = true;

	/**
	 * @var bool Allows to use full qualified class names in "extends" and "implements"
	 */
	public $allowInheritance = true;

	/**
	 * @var bool Allows function calls like \Wikimedia\suppressWarnings()
	 */
	public $allowFunctions = true;

	/**
	 * @inheritDoc
	 */
	public function register() {
		return [ T_NS_SEPARATOR ];
	}

	/**
	 * @inheritDoc
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();

		if ( !isset( $tokens[$stackPtr + 2] )
			// The current backslash is not the last one in the full qualified class name
			|| $tokens[$stackPtr + 2]['code'] === T_NS_SEPARATOR
			// Some unexpected backslash that's not part of a class name
			|| $tokens[$stackPtr + 1]['code'] !== T_STRING
		) {
			return;
		}

		if ( $this->allowMainNamespace
			&& $tokens[$stackPtr - 1]['code'] !== T_STRING
		) {
			return;
		}

		if ( $this->allowFunctions
			&& $tokens[$stackPtr + 2]['code'] === T_OPEN_PARENTHESIS
		) {
			return;
		}

		$skip = Tokens::$emptyTokens;
		$skip[] = T_STRING;
		$skip[] = T_NS_SEPARATOR;
		$prev = $phpcsFile->findPrevious( $skip, $stackPtr - 2, null, true );
		if ( !$prev ) {
			return;
		}
		$prev = $tokens[$prev]['code'];

		// "namespace" and "use" statements must use full qualified class names
		if ( $prev === T_NAMESPACE || $prev === T_USE ) {
			return;
		}

		if ( $this->allowInheritance
			&& ( $prev === T_EXTENDS || $prev === T_IMPLEMENTS )
		) {
			return;
		}

		$phpcsFile->addError(
			'Full qualified class name "%s\\%s" found, please utilize "use …"',
			$stackPtr,
			'Found',
			[
				$tokens[$stackPtr - 1]['code'] === T_STRING ? '…' : '',
				$tokens[$stackPtr + 1]['content'],
			]
		);
	}

}
