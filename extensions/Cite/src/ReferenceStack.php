<?php

namespace Cite;

use LogicException;
use Parser;
use StripState;

/**
 * Encapsulates most of Cite state during parsing.  This includes metadata about each ref tag,
 * and a rollback stack to correct confusion caused by lost context when `{{#tag` is used.
 *
 * @license GPL-2.0-or-later
 */
class ReferenceStack {

	/**
	 * Data structure representing all <ref> tags parsed so far, indexed by group name (an empty
	 * string for the default group) and reference name.
	 *
	 * References without a name get a numeric index, starting from 0. Conflicts are avoided by
	 * disallowing numeric names (e.g. <ref name="1">) in {@see Cite::validateRef}.
	 *
	 * Elements (almost all are optional):
	 * - 'name': The original name="…" of a reference (also used as the array key), or null for
	 *       anonymous references.
	 * - 'key': Sequence number for all references, no matter which group, starting from 1. Used to
	 *       generate IDs and anchors.
	 * - 'number': Sequence number per group, starting from 1. To be used in the [1] footnote
	 *       marker.
	 * - 'extendsIndex': Sequence number for sub-references with the same extends="…", starting
	 *       from 1. Used in addition to the 'number' in [1.1] footnote markers.
	 * - 'count': How often a reference is reused. 0 means not reused, i.e. the reference appears
	 *       only one time. -1 for anonymous references that cannot be reused.
	 * - 'extends': Marks a sub-reference. Points to the parent reference by name.
	 * - 'follow': Marks an incomplete follow="…". This is valid e.g. in the Page:… namespace on
	 *       Wikisource.
	 * - '__placeholder__': Temporarily marks an incomplete parent reference that was referenced via
	 *       extends="…" before it exists.
	 * - 'text': The content inside the <ref>…</ref> tag. Null for <ref /> without content. Also
	 *       null for <ref></ref> without any non-whitespace content.
	 * - 'dir': Direction of the text. Should either be "ltr" or "rtl".
	 *
	 * @var array[][]
	 */
	private $refs = [];

	/**
	 * Auto-incrementing sequence number for all <ref>, no matter which group
	 *
	 * @var int
	 */
	private $refSequence = 0;

	/**
	 * Counter for the number of refs in each group.
	 * @var int[]
	 */
	private $groupRefSequence = [];

	/**
	 * @var int[][]
	 */
	private $extendsCount = [];

	/**
	 * <ref> call stack
	 * Used to cleanup out of sequence ref calls created by #tag
	 * See description of function rollbackRef.
	 *
	 * @var (array|false)[]
	 */
	private $refCallStack = [];

	/**
	 * @deprecated We should be able to push this responsibility to calling code.
	 * @var ErrorReporter
	 */
	private $errorReporter;

	/**
	 * @param ErrorReporter $errorReporter
	 */
	public function __construct( ErrorReporter $errorReporter ) {
		$this->errorReporter = $errorReporter;
	}

	/**
	 * Leave a mark in the stack which matches an invalid ref tag.
	 */
	public function pushInvalidRef() {
		$this->refCallStack[] = false;
	}

	/**
	 * Populate $this->refs and $this->refCallStack based on input and arguments to <ref>
	 *
	 * @param Parser $parser
	 * @param StripState $stripState
	 * @param ?string $text Content from the <ref> tag
	 * @param string[] $argv
	 * @param string $group
	 * @param ?string $name
	 * @param ?string $extends
	 * @param ?string $follow Guaranteed to not be a numeric string
	 * @param ?string $dir ref direction
	 *
	 * @return ?array ref structure, or null if no footnote marker should be rendered
	 * @suppress PhanTypePossiblyInvalidDimOffset To many complaints about array indizes
	 */
	public function pushRef(
		Parser $parser,
		StripState $stripState,
		?string $text,
		array $argv,
		string $group,
		?string $name,
		?string $extends,
		?string $follow,
		?string $dir
	) : ?array {
		if ( !isset( $this->refs[$group] ) ) {
			$this->refs[$group] = [];
			$this->groupRefSequence[$group] = 0;
		}

		$ref = [
			'count' => $name ? 0 : -1,
			'dir' => $dir,
			// This assumes we are going to register a new reference, instead of reusing one
			'key' => ++$this->refSequence,
			'name' => $name,
			'text' => $text,
		];

		if ( $follow ) {
			if ( !isset( $this->refs[$group][$follow] ) ) {
				// Mark an incomplete follow="…" as such. This is valid e.g. in the Page:… namespace
				// on Wikisource.
				$this->refs[$group][] = $ref + [ 'follow' => $follow ];
				$this->refCallStack[] = [ 'new', $this->refSequence, $group, $name, $extends, $text,
					$argv ];
			} elseif ( $text !== null ) {
				// We know the parent already, so just perform the follow="…" and bail out
				$this->appendText( $group, $follow, ' ' . $text );
				$this->refSequence--;
			}
			// A follow="…" never gets its own footnote marker
			return null;
		}

		if ( !$name ) {
			// This is an anonymous reference, which will be given a numeric index.
			$this->refs[$group][] = &$ref;
			$action = 'new';
		} elseif ( isset( $this->refs[$group][$name]['__placeholder__'] ) ) {
			// Populate a placeholder.
			unset( $this->refs[$group][$name]['__placeholder__'] );
			unset( $ref['number'] );
			$ref = array_merge( $ref, $this->refs[$group][$name] );
			$this->refs[$group][$name] =& $ref;
			$action = 'new-from-placeholder';
		} elseif ( !isset( $this->refs[$group][$name] ) ) {
			// Valid key with first occurrence
			$this->refs[$group][$name] = &$ref;
			$action = 'new';
		} else {
			// Change an existing entry.
			$ref = &$this->refs[$group][$name];
			$ref['count']++;
			// Rollback the global counter since we won't create a new ref.
			$this->refSequence--;
			if ( $ref['text'] === null && $text !== null ) {
				// If no text was set before, use this text
				$ref['text'] = $text;
				// Use the dir parameter only from the full definition of a named ref tag
				$ref['dir'] = $dir;
				$action = 'assign';
			} else {
				if ( $text !== null
					// T205803 different strip markers might hide the same text
					&& $stripState->unstripBoth( $text )
					!== $stripState->unstripBoth( $ref['text'] )
				) {
					// two refs with same name and different text
					// add error message to the original ref
					// TODO: standardize error display and move to `validateRef`.
					$ref['text'] .= ' ' . $this->errorReporter->plain(
						$parser, 'cite_error_references_duplicate_key', $name
					);
				}
				$action = 'increment';
			}
		}

		$ref['number'] = $ref['number'] ?? ++$this->groupRefSequence[$group];

		// Do not mess with a known parent a second time
		if ( $extends && !isset( $ref['extendsIndex'] ) ) {
			$this->extendsCount[$group][$extends] =
				( $this->extendsCount[$group][$extends] ?? 0 ) + 1;

			$ref['extends'] = $extends;
			$ref['extendsIndex'] = $this->extendsCount[$group][$extends];

			if ( isset( $this->refs[$group][$extends]['number'] ) ) {
				// Adopt the parent's number.
				$ref['number'] = $this->refs[$group][$extends]['number'];
				// Roll back the group sequence number.
				--$this->groupRefSequence[$group];
			} else {
				// Transfer my number to parent ref.
				$this->refs[$group][$extends] = [
					'number' => $ref['number'],
					'__placeholder__' => true,
				];
			}
		} elseif ( $extends && $ref['extends'] !== $extends ) {
			// TODO: Change the error message to talk about "conflicting content or parent"?
			$error = $this->errorReporter->plain( $parser, 'cite_error_references_duplicate_key',
				$name );
			if ( isset( $ref['text'] ) ) {
				$ref['text'] .= ' ' . $error;
			} else {
				$ref['text'] = $error;
			}
		}

		$this->refCallStack[] = [ $action, $ref['key'], $group, $name, $extends, $text, $argv ];
		return $ref;
	}

	/**
	 * Undo the changes made by the last $count ref tags.  This is used when we discover that the
	 * last few tags were actually inside of a references tag.
	 *
	 * @param int $count
	 *
	 * @return array[] Refs to restore under the correct context, as a list of [ $text, $argv ]
	 */
	public function rollbackRefs( int $count ) : array {
		$redoStack = [];
		while ( $count-- && $this->refCallStack ) {
			$call = array_pop( $this->refCallStack );
			if ( $call ) {
				$redoStack[] = $this->rollbackRef( ...$call );
			}
		}

		// Drop unused rollbacks, this group is finished.
		$this->refCallStack = [];

		return array_reverse( $redoStack );
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
	 * @param string $action
	 * @param int $key Autoincrement counter for this ref.
	 * @param string $group
	 * @param ?string $name The name attribute passed in the ref tag.
	 * @param ?string $extends
	 * @param ?string $text
	 * @param array $argv
	 *
	 * @return array [ $text, $argv ] Ref redo item.
	 */
	private function rollbackRef(
		string $action,
		int $key,
		string $group,
		?string $name,
		?string $extends,
		?string $text,
		array $argv
	) : array {
		if ( !$this->hasGroup( $group ) ) {
			throw new LogicException( "Cannot roll back ref with unknown group \"$group\"." );
		}

		$lookup = $name ?: null;
		if ( $lookup === null ) {
			// Find anonymous ref by key.
			foreach ( $this->refs[$group] as $k => $v ) {
				if ( isset( $this->refs[$group][$k]['key'] ) &&
					$this->refs[$group][$k]['key'] === $key
				) {
					$lookup = $k;
					break;
				}
			}
		}

		// Obsessive sanity checks that the specified element exists.
		if ( $lookup === null ) {
			throw new LogicException( "Cannot roll back unknown ref by key $key." );
		} elseif ( !isset( $this->refs[$group][$lookup] ) ) {
			throw new LogicException( "Cannot roll back missing named ref \"$lookup\"." );
		} elseif ( $this->refs[$group][$lookup]['key'] !== $key ) {
			throw new LogicException(
				"Cannot roll back corrupt named ref \"$lookup\" which should have had key $key." );
		}

		if ( $extends ) {
			$this->extendsCount[$group][$extends]--;
		}

		switch ( $action ) {
			case 'new':
				// Rollback the addition of new elements to the stack
				unset( $this->refs[$group][$lookup] );
				if ( !$this->refs[$group] ) {
					$this->popGroup( $group );
				} elseif ( isset( $this->groupRefSequence[$group] ) ) {
					$this->groupRefSequence[$group]--;
				}
				// TODO: Don't we need to rollback extendsCount as well?
				break;
			case 'new-from-placeholder':
				$this->refs[$group][$lookup]['__placeholder__'] = true;
				unset( $this->refs[$group][$lookup]['count'] );
				break;
			case 'assign':
				// Rollback assignment of text to pre-existing elements
				$this->refs[$group][$lookup]['text'] = null;
				$this->refs[$group][$lookup]['count']--;
				break;
			case 'increment':
				// Rollback increase in named ref occurrences
				$this->refs[$group][$lookup]['count']--;
				break;
			default:
				throw new LogicException( "Unknown call stack action \"$action\"" );
		}
		return [ $text, $argv ];
	}

	/**
	 * Clear state for a single group.
	 *
	 * @param string $group
	 *
	 * @return array[] The references from the removed group
	 */
	public function popGroup( string $group ) : array {
		$refs = $this->getGroupRefs( $group );
		unset( $this->refs[$group] );
		unset( $this->groupRefSequence[$group] );
		unset( $this->extendsCount[$group] );
		return $refs;
	}

	/**
	 * Retruns true if the group exists and contains references.
	 *
	 * @param string $group
	 *
	 * @return bool
	 */
	public function hasGroup( string $group ) : bool {
		return isset( $this->refs[$group] ) && $this->refs[$group];
	}

	/**
	 * Returns a list of all groups with references.
	 *
	 * @return string[]
	 */
	public function getGroups() : array {
		$groups = [];
		foreach ( $this->refs as $group => $refs ) {
			if ( $refs ) {
				$groups[] = $group;
			}
		}
		return $groups;
	}

	/**
	 * Return all references for a group.
	 *
	 * @param string $group
	 *
	 * @return array[]
	 */
	public function getGroupRefs( string $group ) : array {
		return $this->refs[$group] ?? [];
	}

	/**
	 * @param string $group
	 * @param string $name
	 * @param string $text
	 */
	public function appendText( string $group, string $name, string $text ) {
		if ( isset( $this->refs[$group][$name]['text'] ) ) {
			$this->refs[$group][$name]['text'] .= $text;
		} else {
			$this->refs[$group][$name]['text'] = $text;
		}
	}

}
