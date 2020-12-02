<?php

/**
 * A parser extension that adds two tags, <ref> and <references> for adding
 * citations to pages
 *
 * @ingroup Extensions
 *
 * Documentation
 * @link https://www.mediawiki.org/wiki/Extension:Cite/Cite.php
 *
 * <cite> definition in HTML
 * @link http://www.w3.org/TR/html4/struct/text.html#edef-CITE
 *
 * <cite> definition in XHTML 2.0
 * @link http://www.w3.org/TR/2005/WD-xhtml2-20050527/mod-text.html#edef_text_cite
 *
 * @bug https://phabricator.wikimedia.org/T6579
 *
 * @author Ævar Arnfjörð Bjarmason <avarab@gmail.com>
 * @copyright Copyright © 2005, Ævar Arnfjörð Bjarmason
 * @license GPL-2.0-or-later
 */

namespace Cite;

use Html;
use LogicException;
use Parser;
use Sanitizer;
use StatusValue;

class Cite {

	public const DEFAULT_GROUP = '';

	/**
	 * Wikitext attribute name for Book Referencing.
	 */
	public const BOOK_REF_ATTRIBUTE = 'extends';

	/**
	 * Page property key for the Book Referencing `extends` attribute.
	 */
	public const BOOK_REF_PROPERTY = 'ref-extends';

	/**
	 * @var bool
	 */
	private $isPagePreview;

	/**
	 * @var bool
	 */
	private $isSectionPreview;

	/**
	 * @var FootnoteMarkFormatter
	 */
	private $footnoteMarkFormatter;

	/**
	 * @var ReferencesFormatter
	 */
	private $referencesFormatter;

	/**
	 * @var ErrorReporter
	 */
	private $errorReporter;

	/**
	 * True when a <ref> tag is being processed.
	 * Used to avoid infinite recursion
	 *
	 * @var bool
	 */
	private $mInCite = false;

	/**
	 * @var null|string The current group name while parsing nested <ref> in <references>. Null when
	 *  parsing <ref> outside of <references>. Warning, an empty string is a valid group name!
	 */
	private $inReferencesGroup = null;

	/**
	 * Error stack used when defining refs in <references>
	 *
	 * @var string[]
	 */
	private $mReferencesErrors = [];

	/**
	 * @var ReferenceStack
	 */
	private $referenceStack;

	/**
	 * @param Parser $parser
	 */
	public function __construct( Parser $parser ) {
		$this->isPagePreview = $parser->getOptions()->getIsPreview();
		$this->isSectionPreview = $parser->getOptions()->getIsSectionPreview();
		$messageLocalizer = new ReferenceMessageLocalizer( $parser->getContentLanguage() );
		$this->errorReporter = new ErrorReporter( $messageLocalizer );
		$this->referenceStack = new ReferenceStack( $this->errorReporter );
		$anchorFormatter = new AnchorFormatter( $messageLocalizer );
		$this->footnoteMarkFormatter = new FootnoteMarkFormatter(
			$this->errorReporter,
			$anchorFormatter,
			$messageLocalizer
		);
		$this->referencesFormatter = new ReferencesFormatter(
			$this->errorReporter,
			$anchorFormatter,
			$messageLocalizer
		);
	}

	/**
	 * Callback function for <ref>
	 *
	 * @param Parser $parser
	 * @param ?string $text Raw, untrimmed wikitext content of the <ref> tag, if any
	 * @param string[] $argv Arguments as given in <ref name=…>, already trimmed
	 *
	 * @return string|false False in case a <ref> tag is not allowed in the current context
	 */
	public function ref( Parser $parser, ?string $text, array $argv ) {
		if ( $this->mInCite ) {
			return false;
		}

		$this->mInCite = true;
		$ret = $this->guardedRef( $parser, $text, $argv );
		$this->mInCite = false;

		return $ret;
	}

	/**
	 * @param ?string $text
	 * @param string $group
	 * @param ?string $name
	 * @param ?string $extends
	 * @param ?string $follow
	 * @param ?string $dir
	 *
	 * @return StatusValue
	 */
	private function validateRef(
		?string $text,
		string $group,
		?string $name,
		?string $extends,
		?string $follow,
		?string $dir
	) : StatusValue {
		if ( ctype_digit( (string)$name )
			|| ctype_digit( (string)$extends )
			|| ctype_digit( (string)$follow )
		) {
			// Numeric names mess up the resulting id's, potentially producing
			// duplicate id's in the XHTML.  The Right Thing To Do
			// would be to mangle them, but it's not really high-priority
			// (and would produce weird id's anyway).
			return StatusValue::newFatal( 'cite_error_ref_numeric_key' );
		}

		if ( $extends ) {
			// Temporary feature flag until mainstreamed, see T236255
			global $wgCiteBookReferencing;
			if ( !$wgCiteBookReferencing ) {
				return StatusValue::newFatal( 'cite_error_ref_too_many_keys' );
			}

			$groupRefs = $this->referenceStack->getGroupRefs( $group );
			if ( isset( $groupRefs[$name] ) && !isset( $groupRefs[$name]['extends'] ) ) {
				// T242141: A top-level <ref> can't be changed into a sub-reference
				return StatusValue::newFatal( 'cite_error_references_duplicate_key', $name );
			} elseif ( isset( $groupRefs[$extends]['extends'] ) ) {
				// A sub-reference can not be extended a second time (no nesting)
				return StatusValue::newFatal( 'cite_error_ref_nested_extends', $extends,
					$groupRefs[$extends]['extends'] );
			}
		}

		if ( $follow && ( $name || $extends ) ) {
			// TODO: Introduce a specific error for this case.
			return StatusValue::newFatal( 'cite_error_ref_too_many_keys' );
		}

		if ( $dir !== null && !in_array( strtolower( $dir ), [ 'ltr', 'rtl' ] ) ) {
			return StatusValue::newFatal( 'cite_error_ref_invalid_dir', $dir );
		}

		return $this->inReferencesGroup === null ?
			$this->validateRefOutsideOfReferences( $text, $name ) :
			$this->validateRefInReferences( $text, $group, $name );
	}

	/**
	 * @param ?string $text
	 * @param ?string $name
	 *
	 * @return StatusValue
	 */
	private function validateRefOutsideOfReferences(
		?string $text,
		?string $name
	) : StatusValue {
		if ( !$name ) {
			if ( $text === null ) {
				// Completely empty ref like <ref /> is forbidden.
				return StatusValue::newFatal( 'cite_error_ref_no_key' );
			} elseif ( trim( $text ) === '' ) {
				// Must have content or reuse another ref by name.
				return StatusValue::newFatal( 'cite_error_ref_no_input' );
			}
		}

		if ( $text !== null && preg_match(
			'/<ref(erences)?\b[^>]*+>/i',
			preg_replace( '#<(\w++)[^>]*+>.*?</\1\s*>|<!--.*?-->#s', '', $text )
		) ) {
			// (bug T8199) This most likely implies that someone left off the
			// closing </ref> tag, which will cause the entire article to be
			// eaten up until the next <ref>.  So we bail out early instead.
			// The fancy regex above first tries chopping out anything that
			// looks like a comment or SGML tag, which is a crude way to avoid
			// false alarms for <nowiki>, <pre>, etc.
			//
			// Possible improvement: print the warning, followed by the contents
			// of the <ref> tag.  This way no part of the article will be eaten
			// even temporarily.
			return StatusValue::newFatal( 'cite_error_included_ref' );
		}

		return StatusValue::newGood();
	}

	/**
	 * @param ?string $text
	 * @param string $group
	 * @param ?string $name
	 *
	 * @return StatusValue
	 */
	private function validateRefInReferences(
		?string $text,
		string $group,
		?string $name
	) : StatusValue {
		if ( $group !== $this->inReferencesGroup ) {
			// <ref> and <references> have conflicting group attributes.
			return StatusValue::newFatal( 'cite_error_references_group_mismatch',
				Sanitizer::safeEncodeAttribute( $group ) );
		}

		if ( !$name ) {
			// <ref> calls inside <references> must be named
			return StatusValue::newFatal( 'cite_error_references_no_key' );
		}

		if ( $text === null || trim( $text ) === '' ) {
			// <ref> called in <references> has no content.
			return StatusValue::newFatal(
				'cite_error_empty_references_define',
				Sanitizer::safeEncodeAttribute( $name )
			);
		}

		// Section previews are exempt from some rules.
		if ( !$this->isSectionPreview ) {
			if ( !$this->referenceStack->hasGroup( $group ) ) {
				// Called with group attribute not defined in text.
				return StatusValue::newFatal( 'cite_error_references_missing_group',
					Sanitizer::safeEncodeAttribute( $group ) );
			}

			$groupRefs = $this->referenceStack->getGroupRefs( $group );

			if ( !isset( $groupRefs[$name] ) ) {
				// No such named ref exists in this group.
				return StatusValue::newFatal( 'cite_error_references_missing_key',
					Sanitizer::safeEncodeAttribute( $name ) );
			}
		}

		return StatusValue::newGood();
	}

	/**
	 * @param Parser $parser
	 * @param ?string $text Raw, untrimmed wikitext content of the <ref> tag, if any
	 * @param string[] $argv Arguments as given in <ref name=…>, already trimmed
	 *
	 * @return string HTML
	 */
	private function guardedRef(
		Parser $parser,
		?string $text,
		array $argv
	) : string {
		// Tag every page where Book Referencing has been used, whether or not the ref tag is valid.
		// This code and the page property will be removed once the feature is stable.  See T237531.
		if ( array_key_exists( self::BOOK_REF_ATTRIBUTE, $argv ) ) {
			$parser->getOutput()->setProperty( self::BOOK_REF_PROPERTY, true );
		}

		$status = $this->parseArguments(
			$argv,
			[ 'group', 'name', self::BOOK_REF_ATTRIBUTE, 'follow', 'dir' ]
		);
		$arguments = $status->getValue();
		// Use the default group, or the references group when inside one.
		if ( $arguments['group'] === null ) {
			$arguments['group'] = $this->inReferencesGroup ?? self::DEFAULT_GROUP;
		}

		$status->merge( $this->validateRef( $text, ...array_values( $arguments ) ) );

		if ( !$status->isGood() && $this->inReferencesGroup !== null ) {
			foreach ( $status->getErrors() as $error ) {
				$this->mReferencesErrors[] = $this->errorReporter->halfParsed(
					$parser,
					$error['message'],
					...$error['params']
				);
			}
			return '';
		}

		// Validation cares about the difference between null and empty, but from here on we don't
		if ( $text !== null && trim( $text ) === '' ) {
			$text = null;
		}
		[ 'group' => $group, 'name' => $name ] = $arguments;

		if ( $this->inReferencesGroup !== null ) {
			$groupRefs = $this->referenceStack->getGroupRefs( $group );
			// In preview mode, it's possible to reach this with the ref *not* being known
			if ( $text === null || !isset( $groupRefs[$name] ) ) {
				return '';
			}

			if ( !isset( $groupRefs[$name]['text'] ) ) {
				$this->referenceStack->appendText( $group, $name, $text );
			} elseif ( $groupRefs[$name]['text'] !== $text ) {
				// two refs with same key and different content
				// adds error message to the original ref
				// TODO: report these errors the same way as the others, rather than a
				//  special case to append to the second one's content.
				$this->referenceStack->appendText(
					$group,
					$name,
					' ' . $this->errorReporter->plain(
						$parser,
						'cite_error_references_duplicate_key',
						$name
					)
				);
			}
			return '';
		}

		if ( !$status->isGood() ) {
			$this->referenceStack->pushInvalidRef();

			// FIXME: If we ever have multiple errors, these must all be presented to the user,
			//  so they know what to correct.
			// TODO: Make this nicer, see T238061
			$error = $status->getErrors()[0];
			return $this->errorReporter->halfParsed( $parser, $error['message'], ...$error['params'] );
		}

		$ref = $this->referenceStack->pushRef(
			$parser, $parser->getStripState(), $text, $argv, ...array_values( $arguments ) );
		return $ref
			? $this->footnoteMarkFormatter->linkRef( $parser, $group, $ref )
			: '';
	}

	/**
	 * @param string[] $argv The argument vector
	 * @param string[] $allowedAttributes Allowed attribute names
	 *
	 * @return StatusValue Either an error, or has a value with the dictionary of field names and
	 * parsed or default values.  Missing attributes will be `null`.
	 */
	private function parseArguments( array $argv, array $allowedAttributes ) : StatusValue {
		$maxCount = count( $allowedAttributes );
		$allValues = array_merge( array_fill_keys( $allowedAttributes, null ), $argv );
		$status = StatusValue::newGood( array_slice( $allValues, 0, $maxCount ) );

		if ( count( $allValues ) > $maxCount ) {
			// A <ref> must have a name (can be null), but <references> can't have one
			$status->fatal( in_array( 'name', $allowedAttributes )
				? 'cite_error_ref_too_many_keys'
				: 'cite_error_references_invalid_parameters'
			);
		}

		return $status;
	}

	/**
	 * Callback function for <references>
	 *
	 * @param Parser $parser
	 * @param ?string $text Raw, untrimmed wikitext content of the <references> tag, if any
	 * @param string[] $argv Arguments as given in <references …>, already trimmed
	 *
	 * @return string|false False in case a <references> tag is not allowed in the current context
	 */
	public function references( Parser $parser, ?string $text, array $argv ) {
		if ( $this->mInCite || $this->inReferencesGroup !== null ) {
			return false;
		}

		$ret = $this->guardedReferences( $parser, $text, $argv );
		$this->inReferencesGroup = null;

		return $ret;
	}

	/**
	 * Must only be called from references(). Use that to prevent recursion.
	 *
	 * @param Parser $parser
	 * @param ?string $text Raw, untrimmed wikitext content of the <references> tag, if any
	 * @param string[] $argv Arguments as given in <references …>, already trimmed
	 *
	 * @return string HTML
	 */
	private function guardedReferences(
		Parser $parser,
		?string $text,
		array $argv
	) : string {
		$status = $this->parseArguments( $argv, [ 'group', 'responsive' ] );
		[ 'group' => $group, 'responsive' => $responsive ] = $status->getValue();
		$this->inReferencesGroup = $group ?? self::DEFAULT_GROUP;

		if ( $text !== null && trim( $text ) !== '' ) {
			if ( substr_count( $text, Parser::MARKER_PREFIX . "-references-" ) ) {
				return $this->errorReporter->halfParsed( $parser, 'cite_error_included_references' );
			}

			// Detect whether we were sent already rendered <ref>s. Mostly a side effect of using
			// {{#tag:references}}. The following assumes that the parsed <ref>s sent within the
			// <references> block were the most recent calls to <ref>. This assumption is true for
			// all known use cases, but not strictly enforced by the parser. It is possible that
			// some unusual combination of #tag, <references> and conditional parser functions could
			// be created that would lead to malformed references here.
			$count = substr_count( $text, Parser::MARKER_PREFIX . "-ref-" );

			// Undo effects of calling <ref> while unaware of being contained in <references>
			foreach ( $this->referenceStack->rollbackRefs( $count ) as $call ) {
				// Rerun <ref> call with the <references> context now being known
				$this->guardedRef( $parser, ...$call );
			}

			// Parse the <references> content to process any unparsed <ref> tags
			$parser->recursiveTagParse( $text );
		}

		if ( !$status->isGood() ) {
			// Bail out with an error.
			$error = $status->getErrors()[0];
			return $this->errorReporter->halfParsed( $parser, $error['message'], ...$error['params'] );
		}

		$s = $this->formatReferences( $parser, $this->inReferencesGroup, $responsive );

		// Append errors generated while processing <references>
		if ( $this->mReferencesErrors ) {
			$s .= "\n" . implode( "<br />\n", $this->mReferencesErrors );
			$this->mReferencesErrors = [];
		}
		return $s;
	}

	/**
	 * @param Parser $parser
	 * @param string $group
	 * @param string|null $responsive Defaults to $wgCiteResponsiveReferences when not set
	 *
	 * @return string HTML
	 */
	private function formatReferences(
		Parser $parser,
		string $group,
		string $responsive = null
	) : string {
		global $wgCiteResponsiveReferences;

		return $this->referencesFormatter->formatReferences(
			$parser,
			$this->referenceStack->popGroup( $group ),
			$responsive !== null ? $responsive !== '0' : $wgCiteResponsiveReferences,
			$this->isSectionPreview
		);
	}

	/**
	 * Called at the end of page processing to append a default references
	 * section, if refs were used without a main references tag. If there are references
	 * in a custom group, and there is no references tag for it, show an error
	 * message for that group.
	 * If we are processing a section preview, this adds the missing
	 * references tags and does not add the errors.
	 *
	 * @param Parser $parser
	 * @param bool $isSectionPreview
	 *
	 * @return string HTML
	 */
	public function checkRefsNoReferences( Parser $parser, bool $isSectionPreview ) : string {
		$s = '';
		foreach ( $this->referenceStack->getGroups() as $group ) {
			if ( $group === self::DEFAULT_GROUP || $isSectionPreview ) {
				$s .= "\n" . $this->formatReferences( $parser, $group );
			} else {
				$s .= "\n<br />" . $this->errorReporter->halfParsed(
					$parser,
					'cite_error_group_refs_without_references',
					Sanitizer::safeEncodeAttribute( $group )
				);
			}
		}
		if ( $isSectionPreview && $s !== '' ) {
			$headerMsg = wfMessage( 'cite_section_preview_references' );
			if ( !$headerMsg->isDisabled() ) {
				$s = Html::element(
					'h2',
					[ 'id' => 'mw-ext-cite-cite_section_preview_references_header' ],
					$headerMsg->text()
				) . $s;
			}
			// provide a preview of references in its own section
			$s = "\n" . Html::rawElement(
				'div',
				[ 'class' => 'mw-ext-cite-cite_section_preview_references' ],
				$s
			);
		}
		return $s;
	}

	/**
	 * @see https://phabricator.wikimedia.org/T240248
	 */
	public function __clone() {
		throw new LogicException( 'Create a new instance please' );
	}

}
