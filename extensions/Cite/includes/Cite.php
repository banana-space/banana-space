<?php

/**
 * A parser extension that adds two tags, <ref> and <references> for adding
 * citations to pages
 *
 * @ingroup Extensions
 *
 * Documentation
 * @link http://www.mediawiki.org/wiki/Extension:Cite/Cite.php
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

use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\Database;

/**
 * WARNING: MediaWiki core hardcodes this class name to check if the
 * Cite extension is installed. See T89151.
 */
class Cite {

	/**
	 * @todo document
	 */
	const DEFAULT_GROUP = '';

	/**
	 * Maximum storage capacity for pp_value field of page_props table
	 * @todo Find a way to retrieve this information from the DBAL
	 */
	const MAX_STORAGE_LENGTH = 65535; // Size of MySQL 'blob' field

	/**
	 * Key used for storage in parser output's ExtensionData and ObjectCache
	 */
	const EXT_DATA_KEY = 'Cite:References';

	/**
	 * Version number in case we change the data structure in the future
	 */
	const DATA_VERSION_NUMBER = 1;

	/**
	 * Cache duration set when parsing a page with references
	 */
	const CACHE_DURATION_ONPARSE = 3600; // 1 hour

	/**
	 * Cache duration set when fetching references from db
	 */
	const CACHE_DURATION_ONFETCH = 18000; // 5 hours

	/**
	 * Datastructure representing <ref> input, in the format of:
	 * <code>
	 * [
	 * 	'user supplied' => [
	 *		'text' => 'user supplied reference & key',
	 *		'count' => 1, // occurs twice
	 * 		'number' => 1, // The first reference, we want
	 * 		               // all occourances of it to
	 * 		               // use the same number
	 *	],
	 *	0 => 'Anonymous reference',
	 *	1 => 'Another anonymous reference',
	 *	'some key' => [
	 *		'text' => 'this one occurs once'
	 *		'count' => 0,
	 * 		'number' => 4
	 *	],
	 *	3 => 'more stuff'
	 * ];
	 * </code>
	 *
	 * This works because:
	 * * PHP's datastructures are guaranteed to be returned in the
	 *   order that things are inserted into them (unless you mess
	 *   with that)
	 * * User supplied keys can't be integers, therefore avoiding
	 *   conflict with anonymous keys
	 *
	 * @var array[]
	 */
	private $mRefs = [];

	/**
	 * Count for user displayed output (ref[1], ref[2], ...)
	 *
	 * @var int
	 */
	private $mOutCnt = 0;

	/**
	 * @var int[]
	 */
	private $mGroupCnt = [];

	/**
	 * Counter to track the total number of (useful) calls to either the
	 * ref or references tag hook
	 *
	 * @var int
	 */
	private $mCallCnt = 0;

	/**
	 * The backlinks, in order, to pass as $3 to
	 * 'cite_references_link_many_format', defined in
	 * 'cite_references_link_many_format_backlink_labels
	 *
	 * @var string[]
	 */
	private $mBacklinkLabels;

	/**
	 * The links to use per group, in order.
	 *
	 * @var array
	 */
	private $mLinkLabels = [];

	/**
	 * @var Parser
	 */
	private $mParser;

	/**
	 * True when the ParserAfterParse hook has been called.
	 * Used to avoid doing anything in ParserBeforeTidy.
	 *
	 * @var boolean
	 */
	private $mHaveAfterParse = false;

	/**
	 * True when a <ref> tag is being processed.
	 * Used to avoid infinite recursion
	 *
	 * @var boolean
	 */
	public $mInCite = false;

	/**
	 * True when a <references> tag is being processed.
	 * Used to detect the use of <references> to define refs
	 *
	 * @var boolean
	 */
	public $mInReferences = false;

	/**
	 * Error stack used when defining refs in <references>
	 *
	 * @var string[]
	 */
	private $mReferencesErrors = [];

	/**
	 * Group used when in <references> block
	 *
	 * @var string
	 */
	private $mReferencesGroup = '';

	/**
	 * <ref> call stack
	 * Used to cleanup out of sequence ref calls created by #tag
	 * See description of function rollbackRef.
	 *
	 * @var array
	 */
	private $mRefCallStack = [];

	/**
	 * @var bool
	 */
	private $mBumpRefData = false;

	/**
	 * Did we install us into $wgHooks yet?
	 * @var Boolean
	 */
	private static $hooksInstalled = false;

	/**
	 * Callback function for <ref>
	 *
	 * @param string|null $str Raw content of the <ref> tag.
	 * @param string[] $argv Arguments
	 * @param Parser $parser
	 * @param PPFrame $frame
	 *
	 * @return string
	 */
	public function ref( $str, array $argv, Parser $parser, PPFrame $frame ) {
		if ( $this->mInCite ) {
			return htmlspecialchars( "<ref>$str</ref>" );
		}

		$this->mCallCnt++;
		$this->mInCite = true;

		$ret = $this->guardedRef( $str, $argv, $parser );

		$this->mInCite = false;

		$parserOutput = $parser->getOutput();
		$parserOutput->addModules( 'ext.cite.a11y' );
		$parserOutput->addModuleStyles( 'ext.cite.styles' );

		if ( is_callable( [ $frame, 'setVolatile' ] ) ) {
			$frame->setVolatile();
		}

		// new <ref> tag, we may need to bump the ref data counter
		// to avoid overwriting a previous group
		$this->mBumpRefData = true;

		return $ret;
	}

	/**
	 * @param string|null $str Raw content of the <ref> tag.
	 * @param string[] $argv Arguments
	 * @param Parser $parser
	 * @param string $default_group
	 *
	 * @throws Exception
	 * @return string
	 */
	private function guardedRef(
		$str,
		array $argv,
		Parser $parser,
		$default_group = self::DEFAULT_GROUP
	) {
		$this->mParser = $parser;

		# The key here is the "name" attribute.
		list( $key, $group, $follow ) = $this->refArg( $argv );

		# Split these into groups.
		if ( $group === null ) {
			if ( $this->mInReferences ) {
				$group = $this->mReferencesGroup;
			} else {
				$group = $default_group;
			}
		}

		/*
		 * This section deals with constructions of the form
		 *
		 * <references>
		 * <ref name="foo"> BAR </ref>
		 * </references>
		 */
		if ( $this->mInReferences ) {
			$isSectionPreview = $parser->getOptions()->getIsSectionPreview();
			if ( $group != $this->mReferencesGroup ) {
				# <ref> and <references> have conflicting group attributes.
				$this->mReferencesErrors[] =
					$this->error(
						'cite_error_references_group_mismatch',
						Sanitizer::safeEncodeAttribute( $group )
					);
			} elseif ( $str !== '' ) {
				if ( !$isSectionPreview && !isset( $this->mRefs[$group] ) ) {
					# Called with group attribute not defined in text.
					$this->mReferencesErrors[] =
						$this->error(
							'cite_error_references_missing_group',
							Sanitizer::safeEncodeAttribute( $group )
						);
				} elseif ( $key === null || $key === '' ) {
					# <ref> calls inside <references> must be named
					$this->mReferencesErrors[] =
						$this->error( 'cite_error_references_no_key' );
				} elseif ( !$isSectionPreview && !isset( $this->mRefs[$group][$key] ) ) {
					# Called with name attribute not defined in text.
					$this->mReferencesErrors[] =
						$this->error( 'cite_error_references_missing_key', Sanitizer::safeEncodeAttribute( $key ) );
				} else {
					if (
						isset( $this->mRefs[$group][$key]['text'] ) &&
						$str !== $this->mRefs[$group][$key]['text']
					) {
						// two refs with same key and different content
						// add error message to the original ref
						$this->mRefs[$group][$key]['text'] .= ' ' . $this->error(
							'cite_error_references_duplicate_key', $key, 'noparse'
						);
					} else {
						# Assign the text to corresponding ref
						$this->mRefs[$group][$key]['text'] = $str;
					}
				}
			} else {
				# <ref> called in <references> has no content.
				$this->mReferencesErrors[] =
					$this->error( 'cite_error_empty_references_define', Sanitizer::safeEncodeAttribute( $key ) );
			}
			return '';
		}

		if ( $str === '' ) {
			# <ref ...></ref>.  This construct is  invalid if
			# it's a contentful ref, but OK if it's a named duplicate and should
			# be equivalent <ref ... />, for compatability with #tag.
			if ( is_string( $key ) && $key !== '' ) {
				$str = null;
			} else {
				$this->mRefCallStack[] = false;

				return $this->error( 'cite_error_ref_no_input' );
			}
		}

		if ( $key === false ) {
			# TODO: Comment this case; what does this condition mean?
			$this->mRefCallStack[] = false;
			return $this->error( 'cite_error_ref_too_many_keys' );
		}

		if ( $str === null && $key === null ) {
			# Something like <ref />; this makes no sense.
			$this->mRefCallStack[] = false;
			return $this->error( 'cite_error_ref_no_key' );
		}

		if ( is_string( $key ) && preg_match( '/^[0-9]+$/', $key ) ||
			is_string( $follow ) && preg_match( '/^[0-9]+$/', $follow )
		) {
			# Numeric names mess up the resulting id's, potentially produ-
			# cing duplicate id's in the XHTML.  The Right Thing To Do
			# would be to mangle them, but it's not really high-priority
			# (and would produce weird id's anyway).

			$this->mRefCallStack[] = false;
			return $this->error( 'cite_error_ref_numeric_key' );
		}

		if ( preg_match(
			'/<ref\b[^<]*?>/',
			preg_replace( '#<([^ ]+?).*?>.*?</\\1 *>|<!--.*?-->#', '', $str )
		) ) {
			# (bug T8199) This most likely implies that someone left off the
			# closing </ref> tag, which will cause the entire article to be
			# eaten up until the next <ref>.  So we bail out early instead.
			# The fancy regex above first tries chopping out anything that
			# looks like a comment or SGML tag, which is a crude way to avoid
			# false alarms for <nowiki>, <pre>, etc.

			# Possible improvement: print the warning, followed by the contents
			# of the <ref> tag.  This way no part of the article will be eaten
			# even temporarily.

			$this->mRefCallStack[] = false;
			return $this->error( 'cite_error_included_ref' );
		}

		if ( is_string( $key ) || is_string( $str ) ) {
			# We don't care about the content: if the key exists, the ref
			# is presumptively valid.  Either it stores a new ref, or re-
			# fers to an existing one.  If it refers to a nonexistent ref,
			# we'll figure that out later.  Likewise it's definitely valid
			# if there's any content, regardless of key.

			return $this->stack( $str, $key, $group, $follow, $argv );
		}

		# Not clear how we could get here, but something is probably
		# wrong with the types.  Let's fail fast.
		throw new Exception( 'Invalid $str and/or $key: ' . serialize( [ $str, $key ] ) );
	}

	/**
	 * Parse the arguments to the <ref> tag
	 *
	 *  "name" : Key of the reference.
	 *  "group" : Group to which it belongs. Needs to be passed to <references /> too.
	 *  "follow" : If the current reference is the continuation of another, key of that reference.
	 *
	 * @param string[] $argv The argument vector
	 * @return mixed false on invalid input, a string on valid
	 *               input and null on no input
	 */
	private function refArg( array $argv ) {
		$cnt = count( $argv );
		$group = null;
		$key = null;
		$follow = null;

		if ( $cnt > 2 ) {
			// There should only be one key or follow parameter, and one group parameter
			// FIXME : this looks inconsistent, it should probably return a tuple
			return false;
		} elseif ( $cnt >= 1 ) {
			if ( isset( $argv['name'] ) && isset( $argv['follow'] ) ) {
				return [ false, false, false ];
			}
			if ( isset( $argv['name'] ) ) {
				// Key given.
				$key = trim( $argv['name'] );
				unset( $argv['name'] );
				--$cnt;
			}
			if ( isset( $argv['follow'] ) ) {
				// Follow given.
				$follow = trim( $argv['follow'] );
				unset( $argv['follow'] );
				--$cnt;
			}
			if ( isset( $argv['group'] ) ) {
				// Group given.
				$group = $argv['group'];
				unset( $argv['group'] );
				--$cnt;
			}

			if ( $cnt === 0 ) {
				return [ $key, $group, $follow ];
			} else {
				// Invalid key
				return [ false, false, false ];
			}
		} else {
			// No key
			return [ null, $group, false ];
		}
	}

	/**
	 * Populate $this->mRefs based on input and arguments to <ref>
	 *
	 * @param string $str Input from the <ref> tag
	 * @param string|null $key Argument to the <ref> tag as returned by $this->refArg()
	 * @param string $group
	 * @param string|null $follow
	 * @param string[] $call
	 *
	 * @throws Exception
	 * @return string
	 */
	private function stack( $str, $key, $group, $follow, array $call ) {
		if ( !isset( $this->mRefs[$group] ) ) {
			$this->mRefs[$group] = [];
		}
		if ( !isset( $this->mGroupCnt[$group] ) ) {
			$this->mGroupCnt[$group] = 0;
		}
		if ( $follow != null ) {
			if ( isset( $this->mRefs[$group][$follow] ) && is_array( $this->mRefs[$group][$follow] ) ) {
				// add text to the note that is being followed
				$this->mRefs[$group][$follow]['text'] .= ' ' . $str;
			} else {
				// insert part of note at the beginning of the group
				$groupsCount = count( $this->mRefs[$group] );
				for ( $k = 0; $k < $groupsCount; $k++ ) {
					if ( !isset( $this->mRefs[$group][$k]['follow'] ) ) {
						break;
					}
				}
				array_splice( $this->mRefs[$group], $k, 0, [ [
					'count' => -1,
					'text' => $str,
					'key' => ++$this->mOutCnt,
					'follow' => $follow
				] ] );
				array_splice( $this->mRefCallStack, $k, 0,
					[ [ 'new', $call, $str, $key, $group, $this->mOutCnt ] ] );
			}
			// return an empty string : this is not a reference
			return '';
		}

		if ( $key === null ) {
			// No key
			// $this->mRefs[$group][] = $str;
			$this->mRefs[$group][] = [
				'count' => -1,
				'text' => $str,
				'key' => ++$this->mOutCnt
			];
			$this->mRefCallStack[] = [ 'new', $call, $str, $key, $group, $this->mOutCnt ];

			return $this->linkRef( $group, $this->mOutCnt );
		}
		if ( !is_string( $key ) ) {
			throw new Exception( 'Invalid stack key: ' . serialize( $key ) );
		}

		// Valid key
		if ( !isset( $this->mRefs[$group][$key] ) || !is_array( $this->mRefs[$group][$key] ) ) {
			// First occurrence
			$this->mRefs[$group][$key] = [
				'text' => $str,
				'count' => 0,
				'key' => ++$this->mOutCnt,
				'number' => ++$this->mGroupCnt[$group]
			];
			$this->mRefCallStack[] = [ 'new', $call, $str, $key, $group, $this->mOutCnt ];

			return $this->linkRef(
				$group,
				$key,
				$this->mRefs[$group][$key]['key'] . "-" . $this->mRefs[$group][$key]['count'],
				$this->mRefs[$group][$key]['number'],
				"-" . $this->mRefs[$group][$key]['key']
			);
		}

		// We've been here before
		if ( $this->mRefs[$group][$key]['text'] === null && $str !== '' ) {
			// If no text found before, use this text
			$this->mRefs[$group][$key]['text'] = $str;
			$this->mRefCallStack[] = [ 'assign', $call, $str, $key, $group,
				$this->mRefs[$group][$key]['key'] ];
		} else {
			if ( $str != null && $str !== '' && $str !== $this->mRefs[$group][$key]['text'] ) {
				// two refs with same key and different content
				// add error message to the original ref
				$this->mRefs[$group][$key]['text'] .= ' ' . $this->error(
					'cite_error_references_duplicate_key', $key, 'noparse'
				);
			}
			$this->mRefCallStack[] = [ 'increment', $call, $str, $key, $group,
				$this->mRefs[$group][$key]['key'] ];
		}
		return $this->linkRef(
			$group,
			$key,
			$this->mRefs[$group][$key]['key'] . "-" . ++$this->mRefs[$group][$key]['count'],
			$this->mRefs[$group][$key]['number'],
			"-" . $this->mRefs[$group][$key]['key']
		);
	}

	/**
	 * Partially undoes the effect of calls to stack()
	 *
	 * Called by guardedReferences()
	 *
	 * The option to define <ref> within <references> makes the
	 * behavior of <ref> context dependent.  This is normally fine
	 * but certain operations (especially #tag) lead to out-of-order
	 * parser evaluation with the <ref> tags being processed before
	 * their containing <reference> element is read.  This leads to
	 * stack corruption that this function works to fix.
	 *
	 * This function is not a total rollback since some internal
	 * counters remain incremented.  Doing so prevents accidentally
	 * corrupting certain links.
	 *
	 * @param string $type
	 * @param string|null $key
	 * @param string $group
	 * @param int $index
	 */
	private function rollbackRef( $type, $key, $group, $index ) {
		if ( !isset( $this->mRefs[$group] ) ) {
			return;
		}

		if ( $key === null ) {
			foreach ( $this->mRefs[$group] as $k => $v ) {
				if ( $this->mRefs[$group][$k]['key'] === $index ) {
					$key = $k;
					break;
				}
			}
		}

		// Sanity checks that specified element exists.
		if ( $key === null ) {
			return;
		}
		if ( !isset( $this->mRefs[$group][$key] ) ) {
			return;
		}
		if ( $this->mRefs[$group][$key]['key'] != $index ) {
			return;
		}

		switch ( $type ) {
		case 'new':
			# Rollback the addition of new elements to the stack.
			unset( $this->mRefs[$group][$key] );
			if ( $this->mRefs[$group] === [] ) {
				unset( $this->mRefs[$group] );
				unset( $this->mGroupCnt[$group] );
			}
			break;
		case 'assign':
			# Rollback assignment of text to pre-existing elements.
			$this->mRefs[$group][$key]['text'] = null;
			# continue without break
		case 'increment':
			# Rollback increase in named ref occurrences.
			$this->mRefs[$group][$key]['count']--;
			break;
		}
	}

	/**
	 * Callback function for <references>
	 *
	 * @param string|null $str Raw content of the <references> tag.
	 * @param string[] $argv Arguments
	 * @param Parser $parser
	 * @param PPFrame $frame
	 *
	 * @return string
	 */
	public function references( $str, array $argv, Parser $parser, PPFrame $frame ) {
		if ( $this->mInCite || $this->mInReferences ) {
			if ( is_null( $str ) ) {
				return htmlspecialchars( "<references/>" );
			}
			return htmlspecialchars( "<references>$str</references>" );
		}
		$this->mCallCnt++;
		$this->mInReferences = true;
		$ret = $this->guardedReferences( $str, $argv, $parser );
		$this->mInReferences = false;
		if ( is_callable( [ $frame, 'setVolatile' ] ) ) {
			$frame->setVolatile();
		}
		return $ret;
	}

	/**
	 * @param string|null $str Raw content of the <references> tag.
	 * @param string[] $argv
	 * @param Parser $parser
	 * @param string $group
	 *
	 * @return string
	 */
	private function guardedReferences(
		$str,
		array $argv,
		Parser $parser,
		$group = self::DEFAULT_GROUP
	) {
		global $wgCiteResponsiveReferences;

		$this->mParser = $parser;

		if ( isset( $argv['group'] ) ) {
			$group = $argv['group'];
			unset( $argv['group'] );
		}

		if ( strval( $str ) !== '' ) {
			$this->mReferencesGroup = $group;

			# Detect whether we were sent already rendered <ref>s.
			# Mostly a side effect of using #tag to call references.
			# The following assumes that the parsed <ref>s sent within
			# the <references> block were the most recent calls to
			# <ref>.  This assumption is true for all known use cases,
			# but not strictly enforced by the parser.  It is possible
			# that some unusual combination of #tag, <references> and
			# conditional parser functions could be created that would
			# lead to malformed references here.
			$count = substr_count( $str, Parser::MARKER_PREFIX . "-ref-" );
			$redoStack = [];

			# Undo effects of calling <ref> while unaware of containing <references>
			for ( $i = 1; $i <= $count; $i++ ) {
				if ( !$this->mRefCallStack ) {
					break;
				}

				$call = array_pop( $this->mRefCallStack );
				$redoStack[] = $call;
				if ( $call !== false ) {
					list( $type, $ref_argv, $ref_str,
						$ref_key, $ref_group, $ref_index ) = $call;
					$this->rollbackRef( $type, $ref_key, $ref_group, $ref_index );
				}
			}

			# Rerun <ref> call now that mInReferences is set.
			for ( $i = count( $redoStack ) - 1; $i >= 0; $i-- ) {
				$call = $redoStack[$i];
				if ( $call !== false ) {
					list( $type, $ref_argv, $ref_str,
						$ref_key, $ref_group, $ref_index ) = $call;
					$this->guardedRef( $ref_str, $ref_argv, $parser );
				}
			}

			# Parse $str to process any unparsed <ref> tags.
			$parser->recursiveTagParse( $str );

			# Reset call stack
			$this->mRefCallStack = [];
		}

		if ( isset( $argv['responsive'] ) ) {
			$responsive = $argv['responsive'] !== '0';
			unset( $argv['responsive'] );
		} else {
			$responsive = $wgCiteResponsiveReferences;
		}

		// There are remaining parameters we don't recognise
		if ( $argv ) {
			return $this->error( 'cite_error_references_invalid_parameters' );
		}

		$s = $this->referencesFormat( $group, $responsive );

		# Append errors generated while processing <references>
		if ( $this->mReferencesErrors ) {
			$s .= "\n" . implode( "<br />\n", $this->mReferencesErrors );
			$this->mReferencesErrors = [];
		}
		return $s;
	}

	/**
	 * Make output to be returned from the references() function
	 *
	 * @param string $group
	 * @param bool $responsive
	 * @return string HTML ready for output
	 */
	private function referencesFormat( $group, $responsive ) {
		if ( !$this->mRefs || !isset( $this->mRefs[$group] ) ) {
			return '';
		}

		$ent = [];
		foreach ( $this->mRefs[$group] as $k => $v ) {
			$ent[] = $this->referencesFormatEntry( $k, $v );
		}

		// Add new lines between the list items (ref entires) to avoid confusing tidy (bug 13073).
		// Note: This builds a string of wikitext, not html.
		$parserInput = Html::rawElement( 'ol', [ 'class' => [ 'references' ] ],
			"\n" . implode( "\n", $ent ) . "\n"
		);

		// Live hack: parse() adds two newlines on WM, can't reproduce it locally -ævar
		$ret = rtrim( $this->mParser->recursiveTagParse( $parserInput ), "\n" );

		if ( $responsive ) {
			// Use a DIV wrap because column-count on a list directly is broken in Chrome.
			// See https://bugs.chromium.org/p/chromium/issues/detail?id=498730.
			$wrapClasses = [ 'mw-references-wrap' ];
			if ( count( $this->mRefs[$group] ) > 10 ) {
				$wrapClasses[] = 'mw-references-columns';
			}
			$ret = Html::rawElement( 'div', [ 'class' => $wrapClasses ], $ret );
		}

		if ( !$this->mParser->getOptions()->getIsPreview() ) {
			// save references data for later use by LinksUpdate hooks
			$this->saveReferencesData( $group );
		}

		// done, clean up so we can reuse the group
		unset( $this->mRefs[$group] );
		unset( $this->mGroupCnt[$group] );

		return $ret;
	}

	/**
	 * Format a single entry for the referencesFormat() function
	 *
	 * @param string $key The key of the reference
	 * @param mixed $val The value of the reference, string for anonymous
	 *                   references, array for user-suppplied
	 * @return string Wikitext
	 */
	private function referencesFormatEntry( $key, $val ) {
		// Anonymous reference
		if ( !is_array( $val ) ) {
			return wfMessage(
					'cite_references_link_one',
					$this->normalizeKey(
						self::getReferencesKey( $key )
					),
					$this->normalizeKey(
						$this->refKey( $key )
					),
					$this->referenceText( $key, $val )
				)->inContentLanguage()->plain();
		}
		$text = $this->referenceText( $key, $val['text'] );
		if ( isset( $val['follow'] ) ) {
			return wfMessage(
					'cite_references_no_link',
					$this->normalizeKey(
						self::getReferencesKey( $val['follow'] )
					),
					$text
				)->inContentLanguage()->plain();
		}
		if ( !isset( $val['count'] ) ) {
			// this handles the case of section preview for list-defined references
			return wfMessage( 'cite_references_link_many',
					$this->normalizeKey(
						self::getReferencesKey( $key . "-" . ( isset( $val['key'] ) ? $val['key'] : '' ) )
					),
					'',
					$text
				)->inContentLanguage()->plain();
		}
		if ( $val['count'] < 0 ) {
			return wfMessage(
					'cite_references_link_one',
					$this->normalizeKey(
						self::getReferencesKey( $val['key'] )
					),
					$this->normalizeKey(
						# $this->refKey( $val['key'], $val['count'] )
						$this->refKey( $val['key'] )
					),
					$text
				)->inContentLanguage()->plain();
			// Standalone named reference, I want to format this like an
			// anonymous reference because displaying "1. 1.1 Ref text" is
			// overkill and users frequently use named references when they
			// don't need them for convenience
		}
		if ( $val['count'] === 0 ) {
			return wfMessage(
					'cite_references_link_one',
					$this->normalizeKey(
						self::getReferencesKey( $key . "-" . $val['key'] )
					),
					$this->normalizeKey(
						# $this->refKey( $key, $val['count'] ),
						$this->refKey( $key, $val['key'] . "-" . $val['count'] )
					),
					$text
				)->inContentLanguage()->plain();
		// Named references with >1 occurrences
		}
		$links = [];
		// for group handling, we have an extra key here.
		for ( $i = 0; $i <= $val['count']; ++$i ) {
			$links[] = wfMessage(
					'cite_references_link_many_format',
					$this->normalizeKey(
						$this->refKey( $key, $val['key'] . "-$i" )
					),
					$this->referencesFormatEntryNumericBacklinkLabel( $val['number'], $i, $val['count'] ),
					$this->referencesFormatEntryAlternateBacklinkLabel( $i )
			)->inContentLanguage()->plain();
		}

		$list = $this->listToText( $links );

		return wfMessage( 'cite_references_link_many',
				$this->normalizeKey(
					self::getReferencesKey( $key . "-" . $val['key'] )
				),
				$list,
				$text
			)->inContentLanguage()->plain();
	}

	/**
	 * Returns formatted reference text
	 * @param String $key
	 * @param String $text
	 * @return String
	 */
	private function referenceText( $key, $text ) {
		if ( !isset( $text ) || $text === '' ) {
			if ( $this->mParser->getOptions()->getIsSectionPreview() ) {
				return $this->warning( 'cite_warning_sectionpreview_no_text', $key, 'noparse' );
			}
			return $this->error( 'cite_error_references_no_text', $key, 'noparse' );
		}
		return '<span class="reference-text">' . rtrim( $text, "\n" ) . "</span>\n";
	}

	/**
	 * Generate a numeric backlink given a base number and an
	 * offset, e.g. $base = 1, $offset = 2; = 1.2
	 * Since bug #5525, it correctly does 1.9 -> 1.10 as well as 1.099 -> 1.100
	 *
	 * @static
	 *
	 * @param int $base
	 * @param int $offset
	 * @param int $max Maximum value expected.
	 * @return string
	 */
	private function referencesFormatEntryNumericBacklinkLabel( $base, $offset, $max ) {
		global $wgContLang;
		$scope = strlen( $max );
		$ret = $wgContLang->formatNum(
			sprintf( "%s.%0{$scope}s", $base, $offset )
		);
		return $ret;
	}

	/**
	 * Generate a custom format backlink given an offset, e.g.
	 * $offset = 2; = c if $this->mBacklinkLabels = [ 'a',
	 * 'b', 'c', ...]. Return an error if the offset > the # of
	 * array items
	 *
	 * @param int $offset
	 *
	 * @return string
	 */
	private function referencesFormatEntryAlternateBacklinkLabel( $offset ) {
		if ( !isset( $this->mBacklinkLabels ) ) {
			$this->genBacklinkLabels();
		}
		if ( isset( $this->mBacklinkLabels[$offset] ) ) {
			return $this->mBacklinkLabels[$offset];
		} else {
			// Feed me!
			return $this->error( 'cite_error_references_no_backlink_label', null, 'noparse' );
		}
	}

	/**
	 * Generate a custom format link for a group given an offset, e.g.
	 * the second <ref group="foo"> is b if $this->mLinkLabels["foo"] =
	 * [ 'a', 'b', 'c', ...].
	 * Return an error if the offset > the # of array items
	 *
	 * @param int $offset
	 * @param string $group The group name
	 * @param string $label The text to use if there's no message for them.
	 *
	 * @return string
	 */
	private function getLinkLabel( $offset, $group, $label ) {
		$message = "cite_link_label_group-$group";
		if ( !isset( $this->mLinkLabels[$group] ) ) {
			$this->genLinkLabels( $group, $message );
		}
		if ( $this->mLinkLabels[$group] === false ) {
			// Use normal representation, ie. "$group 1", "$group 2"...
			return $label;
		}

		if ( isset( $this->mLinkLabels[$group][$offset - 1] ) ) {
			return $this->mLinkLabels[$group][$offset - 1];
		} else {
			// Feed me!
			return $this->error( 'cite_error_no_link_label_group', [ $group, $message ], 'noparse' );
		}
	}

	/**
	 * Return an id for use in wikitext output based on a key and
	 * optionally the number of it, used in <references>, not <ref>
	 * (since otherwise it would link to itself)
	 *
	 * @static
	 *
	 * @param string $key
	 * @param int $num The number of the key
	 * @return string A key for use in wikitext
	 */
	private function refKey( $key, $num = null ) {
		$prefix = wfMessage( 'cite_reference_link_prefix' )->inContentLanguage()->text();
		$suffix = wfMessage( 'cite_reference_link_suffix' )->inContentLanguage()->text();
		if ( isset( $num ) ) {
			$key = wfMessage( 'cite_reference_link_key_with_num', $key, $num )
				->inContentLanguage()->plain();
		}

		return "$prefix$key$suffix";
	}

	/**
	 * Return an id for use in wikitext output based on a key and
	 * optionally the number of it, used in <ref>, not <references>
	 * (since otherwise it would link to itself)
	 *
	 * @static
	 *
	 * @param string $key
	 * @return string A key for use in wikitext
	 */
	public static function getReferencesKey( $key ) {
		$prefix = wfMessage( 'cite_references_link_prefix' )->inContentLanguage()->text();
		$suffix = wfMessage( 'cite_references_link_suffix' )->inContentLanguage()->text();

		return "$prefix$key$suffix";
	}

	/**
	 * Generate a link (<sup ...) for the <ref> element from a key
	 * and return XHTML ready for output
	 *
	 * @param string $group
	 * @param string $key The key for the link
	 * @param int $count The index of the key, used for distinguishing
	 *                   multiple occurrences of the same key
	 * @param int $label The label to use for the link, I want to
	 *                   use the same label for all occourances of
	 *                   the same named reference.
	 * @param string $subkey
	 *
	 * @return string
	 */
	private function linkRef( $group, $key, $count = null, $label = null, $subkey = '' ) {
		global $wgContLang;
		$label = is_null( $label ) ? ++$this->mGroupCnt[$group] : $label;

		return $this->mParser->recursiveTagParse(
				wfMessage(
					'cite_reference_link',
					$this->normalizeKey(
						$this->refKey( $key, $count )
					),
					$this->normalizeKey(
						self::getReferencesKey( $key . $subkey )
					),
					Sanitizer::safeEncodeAttribute(
						$this->getLinkLabel( $label, $group,
							( ( $group === self::DEFAULT_GROUP ) ? '' : "$group " ) . $wgContLang->formatNum( $label ) )
					)
				)->inContentLanguage()->plain()
			);
	}

	/**
	 * Normalizes and sanitizes a reference key
	 *
	 * @param string $key
	 * @return string
	 */
	private function normalizeKey( $key ) {
		$key = Sanitizer::escapeIdForAttribute( $key );
		$key = Sanitizer::safeEncodeAttribute( $key );

		return $key;
	}

	/**
	 * This does approximately the same thing as
	 * Language::listToText() but due to this being used for a
	 * slightly different purpose (people might not want , as the
	 * first separator and not 'and' as the second, and this has to
	 * use messages from the content language) I'm rolling my own.
	 *
	 * @static
	 *
	 * @param array $arr The array to format
	 * @return string
	 */
	private function listToText( $arr ) {
		$cnt = count( $arr );

		$sep = wfMessage( 'cite_references_link_many_sep' )->inContentLanguage()->plain();
		$and = wfMessage( 'cite_references_link_many_and' )->inContentLanguage()->plain();

		if ( $cnt === 1 ) {
			// Enforce always returning a string
			return (string)$arr[0];
		} else {
			$t = array_slice( $arr, 0, $cnt - 1 );
			return implode( $sep, $t ) . $and . $arr[$cnt - 1];
		}
	}

	/**
	 * Generate the labels to pass to the
	 * 'cite_references_link_many_format' message, the format is an
	 * arbitrary number of tokens separated by [\t\n ]
	 */
	private function genBacklinkLabels() {
		$text = wfMessage( 'cite_references_link_many_format_backlink_labels' )
			->inContentLanguage()->plain();
		$this->mBacklinkLabels = preg_split( '#[\n\t ]#', $text );
	}

	/**
	 * Generate the labels to pass to the
	 * 'cite_reference_link' message instead of numbers, the format is an
	 * arbitrary number of tokens separated by [\t\n ]
	 *
	 * @param string $group
	 * @param string $message
	 */
	private function genLinkLabels( $group, $message ) {
		$text = false;
		$msg = wfMessage( $message )->inContentLanguage();
		if ( $msg->exists() ) {
			$text = $msg->plain();
		}
		$this->mLinkLabels[$group] = ( !$text ) ? false : preg_split( '#[\n\t ]#', $text );
	}

	/**
	 * Gets run when Parser::clearState() gets run, since we don't
	 * want the counts to transcend pages and other instances
	 *
	 * @param Parser &$parser
	 *
	 * @return bool
	 */
	public function clearState( Parser &$parser ) {
		if ( $parser->extCite !== $this ) {
			return $parser->extCite->clearState( $parser );
		}

		# Don't clear state when we're in the middle of parsing
		# a <ref> tag
		if ( $this->mInCite || $this->mInReferences ) {
			return true;
		}

		$this->mGroupCnt = [];
		$this->mOutCnt = 0;
		$this->mCallCnt = 0;
		$this->mRefs = [];
		$this->mReferencesErrors = [];
		$this->mRefCallStack = [];

		return true;
	}

	/**
	 * Gets run when the parser is cloned.
	 *
	 * @param Parser $parser
	 *
	 * @return bool
	 */
	public function cloneState( Parser $parser ) {
		if ( $parser->extCite !== $this ) {
			return $parser->extCite->cloneState( $parser );
		}

		$parser->extCite = clone $this;
		$parser->setHook( 'ref', [ $parser->extCite, 'ref' ] );
		$parser->setHook( 'references', [ $parser->extCite, 'references' ] );

		// Clear the state, making sure it will actually work.
		$parser->extCite->mInCite = false;
		$parser->extCite->mInReferences = false;
		$parser->extCite->clearState( $parser );

		return true;
	}

	/**
	 * Called at the end of page processing to append a default references
	 * section, if refs were used without a main references tag. If there are references
	 * in a custom group, and there is no references tag for it, show an error
	 * message for that group.
	 * If we are processing a section preview, this adds the missing
	 * references tags and does not add the errors.
	 *
	 * @param bool $afterParse True if called from the ParserAfterParse hook
	 * @param Parser &$parser
	 * @param string &$text
	 *
	 * @return bool
	 */
	public function checkRefsNoReferences( $afterParse, &$parser, &$text ) {
		global $wgCiteResponsiveReferences;
		if ( is_null( $parser->extCite ) ) {
			return true;
		}
		if ( $parser->extCite !== $this ) {
			return $parser->extCite->checkRefsNoReferences( $afterParse, $parser, $text );
		}

		if ( $afterParse ) {
			$this->mHaveAfterParse = true;
		} elseif ( $this->mHaveAfterParse ) {
			return true;
		}

		if ( !$parser->getOptions()->getIsPreview() ) {
			// save references data for later use by LinksUpdate hooks
			if ( $this->mRefs && isset( $this->mRefs[self::DEFAULT_GROUP] ) ) {
				$this->saveReferencesData();
			}
			$isSectionPreview = false;
		} else {
			$isSectionPreview = $parser->getOptions()->getIsSectionPreview();
		}

		$s = '';
		foreach ( $this->mRefs as $group => $refs ) {
			if ( !$refs ) {
				continue;
			}
			if ( $group === self::DEFAULT_GROUP || $isSectionPreview ) {
				$s .= $this->referencesFormat( $group, $wgCiteResponsiveReferences );
			} else {
				$s .= "\n<br />" .
					$this->error(
						'cite_error_group_refs_without_references',
						Sanitizer::safeEncodeAttribute( $group )
					);
			}
		}
		if ( $isSectionPreview && $s !== '' ) {
			// provide a preview of references in its own section
			$text .= "\n" . '<div class="mw-ext-cite-cite_section_preview_references" >';
			$headerMsg = wfMessage( 'cite_section_preview_references' );
			if ( !$headerMsg->isDisabled() ) {
				$text .= '<h2 id="mw-ext-cite-cite_section_preview_references_header" >'
				. $headerMsg->escaped()
				. '</h2>';
			}
			$text .= $s . '</div>';
		} else {
			$text .= $s;
		}
		return true;
	}

	/**
	 * Saves references in parser extension data
	 * This is called by each <references/> tag, and by checkRefsNoReferences
	 * Assumes $this->mRefs[$group] is set
	 *
	 * @param string $group
	 */
	private function saveReferencesData( $group = self::DEFAULT_GROUP ) {
		global $wgCiteStoreReferencesData;
		if ( !$wgCiteStoreReferencesData ) {
			return;
		}
		$savedRefs = $this->mParser->getOutput()->getExtensionData( self::EXT_DATA_KEY );
		if ( $savedRefs === null ) {
			// Initialize array structure
			$savedRefs = [
				'refs' => [],
				'version' => self::DATA_VERSION_NUMBER,
			];
		}
		if ( $this->mBumpRefData ) {
			// This handles pages with multiple <references/> tags with <ref> tags in between.
			// On those, a group can appear several times, so we need to avoid overwriting
			// a previous appearance.
			$savedRefs['refs'][] = [];
			$this->mBumpRefData = false;
		}
		$n = count( $savedRefs['refs'] ) - 1;
		// save group
		$savedRefs['refs'][$n][$group] = $this->mRefs[$group];

		$this->mParser->getOutput()->setExtensionData( self::EXT_DATA_KEY, $savedRefs );
	}

	/**
	 * Hook for the InlineEditor extension.
	 * If any ref or reference reference tag is in the text,
	 * the entire page should be reparsed, so we return false in that case.
	 *
	 * @param string &$output
	 *
	 * @return bool
	 */
	public function checkAnyCalls( &$output ) {
		global $wgParser;
		/* InlineEditor always uses $wgParser */
		return ( $wgParser->extCite->mCallCnt <= 0 );
	}

	/**
	 * Initialize the parser hooks
	 *
	 * @param Parser $parser
	 *
	 * @return bool
	 */
	public static function setHooks( Parser $parser ) {
		global $wgHooks;

		$parser->extCite = new self();

		if ( !self::$hooksInstalled ) {
			$wgHooks['ParserClearState'][] = [ $parser->extCite, 'clearState' ];
			$wgHooks['ParserCloned'][] = [ $parser->extCite, 'cloneState' ];
			$wgHooks['ParserAfterParse'][] = [ $parser->extCite, 'checkRefsNoReferences', true ];
			$wgHooks['ParserBeforeTidy'][] = [ $parser->extCite, 'checkRefsNoReferences', false ];
			$wgHooks['InlineEditorPartialAfterParse'][] = [ $parser->extCite, 'checkAnyCalls' ];
			self::$hooksInstalled = true;
		}
		$parser->setHook( 'ref', [ $parser->extCite, 'ref' ] );
		$parser->setHook( 'references', [ $parser->extCite, 'references' ] );

		return true;
	}

	/**
	 * Return an error message based on an error ID
	 *
	 * @param string $key   Message name for the error
	 * @param string[]|string|null $param Parameter to pass to the message
	 * @param string $parse Whether to parse the message ('parse') or not ('noparse')
	 * @return string XHTML or wikitext ready for output
	 */
	private function error( $key, $param = null, $parse = 'parse' ) {
		# For ease of debugging and because errors are rare, we
		# use the user language and split the parser cache.
		$lang = $this->mParser->getOptions()->getUserLangObj();
		$dir = $lang->getDir();

		# We rely on the fact that PHP is okay with passing unused argu-
		# ments to functions.  If $1 is not used in the message, wfMessage will
		# just ignore the extra parameter.
		$msg = wfMessage(
			'cite_error',
			wfMessage( $key, $param )->inLanguage( $lang )->plain()
		)
			->inLanguage( $lang )
			->plain();

		$this->mParser->addTrackingCategory( 'cite-tracking-category-cite-error' );

		$ret = Html::rawElement(
			'span',
			[
				'class' => 'error mw-ext-cite-error',
				'lang' => $lang->getHtmlCode(),
				'dir' => $dir,
			],
			$msg
		);

		if ( $parse === 'parse' ) {
			$ret = $this->mParser->recursiveTagParse( $ret );
		}

		return $ret;
	}

	/**
	 * Return a warning message based on a warning ID
	 *
	 * @param string $key   Message name for the warning. Name should start with cite_warning_
	 * @param string|null $param Parameter to pass to the message
	 * @param string $parse Whether to parse the message ('parse') or not ('noparse')
	 * @return string XHTML or wikitext ready for output
	 */
	private function warning( $key, $param = null, $parse = 'parse' ) {
		# For ease of debugging and because errors are rare, we
		# use the user language and split the parser cache.
		$lang = $this->mParser->getOptions()->getUserLangObj();
		$dir = $lang->getDir();

		# We rely on the fact that PHP is okay with passing unused argu-
		# ments to functions.  If $1 is not used in the message, wfMessage will
		# just ignore the extra parameter.
		$msg = wfMessage(
			'cite_warning',
			wfMessage( $key, $param )->inLanguage( $lang )->plain()
		)
			->inLanguage( $lang )
			->plain();

		$key = preg_replace( '/^cite_warning_/', '', $key ) . '';
		$ret = Html::rawElement(
			'span',
			[
				'class' => 'warning mw-ext-cite-warning mw-ext-cite-warning-' .
					Sanitizer::escapeClass( $key ),
				'lang' => $lang->getHtmlCode(),
				'dir' => $dir,
			],
			$msg
		);

		if ( $parse === 'parse' ) {
			$ret = $this->mParser->recursiveTagParse( $ret );
		}

		return $ret;
	}

	/**
	 * Fetch references stored for the given title in page_props
	 * For performance, results are cached
	 *
	 * @param Title $title
	 * @return array|false
	 */
	public static function getStoredReferences( Title $title ) {
		global $wgCiteStoreReferencesData;
		if ( !$wgCiteStoreReferencesData ) {
			return false;
		}
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$key = $cache->makeKey( self::EXT_DATA_KEY, $title->getArticleID() );
		return $cache->getWithSetCallback(
			$key,
			self::CACHE_DURATION_ONFETCH,
			function ( $oldValue, &$ttl, array &$setOpts ) use ( $title ) {
				$dbr = wfGetDB( DB_REPLICA );
				$setOpts += Database::getCacheSetOptions( $dbr );
				return self::recursiveFetchRefsFromDB( $title, $dbr );
			},
			[
				'checkKeys' => [ $key ],
				'lockTSE' => 30,
			]
		);
	}

	/**
	 * Reconstructs compressed json by successively retrieving the properties references-1, -2, etc
	 * It attempts the next step when a decoding error occurs.
	 * Returns json_decoded uncompressed string, with validation of json
	 *
	 * @param Title $title
	 * @param IDatabase $dbr
	 * @param string $string
	 * @param int $i
	 * @return array|false
	 */
	private static function recursiveFetchRefsFromDB( Title $title, IDatabase $dbr,
		$string = '', $i = 1 ) {
		$id = $title->getArticleID();
		$result = $dbr->selectField(
			'page_props',
			'pp_value',
			[
				'pp_page' => $id,
				'pp_propname' => 'references-' . $i
			],
			__METHOD__
		);
		if ( $result !== false ) {
			$string .= $result;
			$decodedString = gzdecode( $string );
			if ( $decodedString !== false ) {
				$json = json_decode( $decodedString, true );
				if ( json_last_error() === JSON_ERROR_NONE ) {
					return $json;
				}
				// corrupted json ?
				// shouldn't happen since when string is truncated, gzdecode should fail
				wfDebug( "Corrupted json detected when retrieving stored references for title id $id" );
			}
			// if gzdecode fails, try to fetch next references- property value
			return self::recursiveFetchRefsFromDB( $title, $dbr, $string, ++$i );

		} else {
			// no refs stored in page_props at this index
			if ( $i > 1 ) {
				// shouldn't happen
				wfDebug( "Failed to retrieve stored references for title id $id" );
			}
			return false;
		}
	}

}
