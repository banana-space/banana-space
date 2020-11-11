<?php

namespace RemexHtml\Tokenizer;

use RemexHtml\HTMLData;
use RemexHtml\PropGuard;

/**
 * HTML 5 tokenizer
 *
 * Based on the W3C recommendation as published 01 November 2016:
 * https://www.w3.org/TR/2016/REC-html51-20161101/
 */
class Tokenizer {
	use PropGuard;

	// States
	const STATE_START = 1;
	const STATE_DATA = 2;
	const STATE_RCDATA = 3;
	const STATE_RAWTEXT = 4;
	const STATE_SCRIPT_DATA = 5;
	const STATE_PLAINTEXT = 6;
	const STATE_EOF = 7;
	const STATE_CURRENT = 8;

	// Match indices for the data state regex
	const MD_END_TAG_OPEN = 1;
	const MD_TAG_NAME = 2;
	const MD_COMMENT = 3;
	const MD_COMMENT_INNER = 4;
	const MD_COMMENT_END = 5;
	const MD_DOCTYPE = 6;
	const MD_DT_NAME_WS = 7;
	const MD_DT_NAME = 8;
	const MD_DT_PUBLIC_WS = 9;
	const MD_DT_PUBLIC_DQ = 10;
	const MD_DT_PUBLIC_SQ = 11;
	const MD_DT_PUBSYS_WS = 12;
	const MD_DT_PUBSYS_DQ = 13;
	const MD_DT_PUBSYS_SQ = 14;
	const MD_DT_SYSTEM_WS = 15;
	const MD_DT_SYSTEM_DQ = 16;
	const MD_DT_SYSTEM_SQ = 17;
	const MD_DT_BOGUS = 18;
	const MD_DT_END = 19;
	const MD_CDATA = 20;
	const MD_BOGUS_COMMENT = 21;

	// Match indices for the character reference regex
	const MC_PREFIX = 1;
	const MC_DECIMAL = 2;
	const MC_HEXDEC = 3;
	const MC_SEMICOLON = 4;
	const MC_HASH = 5;
	const MC_NAMED = 6;
	const MC_SUFFIX = 7;
	const MC_INVALID = 8;

	// Match indices for the attribute regex
	const MA_SLASH = 1;
	const MA_NAME = 2;
	const MA_DQUOTED = 3;
	const MA_SQUOTED = 4;
	const MA_UNQUOTED = 5;

	// Characters
	const REPLACEMENT_CHAR = "\xef\xbf\xbd";
	const BYTE_ORDER_MARK = "\xef\xbb\xbf";

	protected $ignoreErrors;
	protected $ignoreCharRefs;
	protected $ignoreNulls;
	protected $skipPreprocess;
	protected $appropriateEndTag;
	protected $listener;
	protected $state;
	protected $preprocessed;
	protected $text;
	protected $pos;
	protected $length;
	protected $enableCdataCallback;
	protected $fragmentNamespace;
	protected $fragmentName;

	/**
	 * Constructor
	 *
	 * @param TokenHandler $listener The object which receives token events
	 * @param string $text The text to tokenize
	 * @param array $options Associative array of options, including:
	 *   - ignoreErrors: True to improve performance by ignoring errors. The
	 *     token stream should still be the same, except that error() won't be
	 *     called.
	 *   - ignoreCharRefs: True to ignore character references. Character tokens
	 *     will contain the unexpanded character references, and no errors
	 *     related to invalid character references will be raised. Performance
	 *     will be improved. This is not compliant behaviour.
	 *   - ignoreNulls: True to ignore NULL bytes in the input stream, instead
	 *     of raising errors and converting them to U+FFFD as is usually
	 *     required by the spec.
	 *   - skipPreprocess: True to skip the "preprocessing the input stream"
	 *     stage, which normalizes line endings and raises errors on certain
	 *     control characters. Advisable if the input stream is already
	 *     appropriately normalized.
	 */
	public function __construct( TokenHandler $listener, $text, $options ) {
		$this->listener = $listener;
		$this->text = $text;
		$this->pos = 0;
		$this->preprocessed = false;
		$this->length = strlen( $text );
		$this->ignoreErrors = !empty( $options['ignoreErrors'] );
		$this->ignoreCharRefs = !empty( $options['ignoreCharRefs'] );
		$this->ignoreNulls = !empty( $options['ignoreNulls'] );
		$this->skipPreprocess = !empty( $options['skipPreprocess'] );
	}

	public function setEnableCdataCallback( $cb ) {
		$this->enableCdataCallback = $cb;
	}

	/**
	 * Run the tokenizer on the whole input stream. This is the normal entry point.
	 *
	 * @param array $options An associative array of options:
	 *   - state : One of the STATE_* constants, a state in which to start.
	 *   - appropriateEndTag : The "appropriate end tag", which needs to be set
	 *     if entering one of the raw text states.
	 *   - fragmentNamespace : The fragment namespace
	 *   - fragmentName : The fragment tag name
	 */
	public function execute( $options = [] ) {
		if ( isset( $options['state'] ) ) {
			$this->state = $options['state'];
		} else {
			$this->state = self::STATE_START;
		}

		if ( isset( $options['fragmentNamespace'] ) ) {
			$this->setFragmentContext( $options['fragmentNamespace'], $options['fragmentName'] );
		} else {
			$this->fragmentNamespace = null;
			$this->fragmentName = null;
		}
		$this->appropriateEndTag = isset( $options['appropriateEndTag'] ) ?
			$options['appropriateEndTag'] : null;
		$this->preprocess();
		$this->listener->startDocument( $this, $this->fragmentNamespace, $this->fragmentName );

		$this->executeInternal( true );
	}

	/**
	 * Get the preprocessed input text. Source offsets in event parameters are
	 * relative to this string. If skipPreprocess was specified, this will be
	 * the same as the input string.
	 * @return string
	 */
	public function getPreprocessedText() {
		$this->preprocess();
		return $this->text;
	}

	/**
	 * Change the state of the tokenizer during parsing. This for use by the
	 * tree builder to switch the tokenizer into one of the raw text states.
	 *
	 * @param int $state One of the STATE_* constants
	 * @param string $appropriateEndTag The appropriate end tag
	 */
	public function switchState( $state, $appropriateEndTag ) {
		$this->state = $state;
		$this->appropriateEndTag = $appropriateEndTag;
	}

	/**
	 * Initialize the tokenizer for fragment parsing
	 *
	 * @param string $namespace The namespace of the context element
	 * @param string $tagName The name of the context element
	 */
	public function setFragmentContext( $namespace, $tagName ) {
		$this->fragmentNamespace = $namespace;
		$this->fragmentName = $tagName;

		if ( strval( $namespace ) !== '' && $namespace !== HTMLData::NS_HTML ) {
			return;
		}

		switch ( $tagName ) {
		case 'title':
		case 'textarea':
			$this->state = self::STATE_RCDATA;
			break;

		case 'style':
		case 'xmp':
		case 'iframe':
		case 'noembed':
		case 'noframes':
			$this->state = self::STATE_RAWTEXT;
			break;

		case 'script':
			$this->state = self::STATE_SCRIPT_DATA;
			break;

		case 'noscript':
			if ( $this->scriptingFlag ) {
				$this->state = self::STATE_RAWTEXT;
			}
			break;

		case 'plaintext':
			$this->state = self::STATE_PLAINTEXT;
			break;
		}
	}

	/**
	 * Notify the tokenizer that the document will be tokenized by repeated step()
	 * calls. This must be called once only, before the first call to step().
	 */
	public function beginStepping() {
		$this->state = self::STATE_START;
		$this->preprocess();
		$this->listener->startDocument( $this, null, null );
	}

	/**
	 * Tokenize a minimum amount of text from the input stream, and emit the
	 * resulting events.
	 *
	 * @return bool True if the input continues and step() should be called
	 *   again, false on EOF
	 */
	public function step() {
		if ( $this->state === null ) {
			$this->fatal( "beginStepping() must be called before step()" );
		}
		return $this->executeInternal( false );
	}

	/**
	 * Preprocess the input text, if it hasn't been done already.
	 */
	protected function preprocess() {
		if ( $this->preprocessed || $this->skipPreprocess ) {
			return;
		}

		// Normalize line endings
		$this->text = strtr( $this->text, [
			"\r\n" => "\n",
			"\r" => "\n" ] );
		$this->length = strlen( $this->text );

		// Raise parse errors for any control characters
		if ( !$this->ignoreErrors ) {
			$pos = 0;
			$re = '/[' .
				'\x{0001}-\x{0008}' .
				'\x{000E}-\x{001F}' .
				'\x{007F}-\x{009F}' .
				'\x{FDD0}-\x{FDEF}' .
				'\x{000B}' .
				'\x{FFFE}\x{FFFF}' .
				'\x{1FFFE}\x{1FFFF}' .
				'\x{2FFFE}\x{2FFFF}' .
				'\x{3FFFE}\x{3FFFF}' .
				'\x{4FFFE}\x{4FFFF}' .
				'\x{5FFFE}\x{5FFFF}' .
				'\x{6FFFE}\x{6FFFF}' .
				'\x{7FFFE}\x{7FFFF}' .
				'\x{8FFFE}\x{8FFFF}' .
				'\x{9FFFE}\x{9FFFF}' .
				'\x{AFFFE}\x{AFFFF}' .
				'\x{BFFFE}\x{BFFFF}' .
				'\x{CFFFE}\x{CFFFF}' .
				'\x{DFFFE}\x{DFFFF}' .
				'\x{EFFFE}\x{EFFFF}' .
				'\x{FFFFE}\x{FFFFF}' .
				'\x{10FFFE}\x{10FFFF}]/u';
			while ( $pos < $this->length ) {
				$count = preg_match( $re, $this->text, $m, PREG_OFFSET_CAPTURE, $pos );
				if ( $count === false ) {
					$this->fatal( "Invalid UTF-8 sequence given to Tokenizer" );
				} elseif ( !$count ) {
					break;
				}
				$pos = $m[0][1];
				$this->error( "disallowed control character", $pos );
				$pos += strlen( $m[0][0] );
			}
		}
	}

	/**
	 * The main state machine, the common implementation of step() and execute().
	 * @param bool $loop Set to true to loop until finished, false to step once.
	 * @return bool True if the input continues, false on EOF
	 */
	protected function executeInternal( $loop ) {
		$eof = false;

		do {
			switch ( $this->state ) {
			case self::STATE_DATA:
				$this->state = $this->dataState( $loop );
				break;

			case self::STATE_RCDATA:
				$this->state = $this->textElementState( false );
				break;

			case self::STATE_RAWTEXT:
				$this->state = $this->textElementState( true );
				break;

			case self::STATE_SCRIPT_DATA:
				$this->state = $this->scriptDataState();
				break;

			case self::STATE_PLAINTEXT:
				$this->state = $this->plaintextState();
				break;

			case self::STATE_START:
				$this->state = self::STATE_DATA;
				break;

			case self::STATE_EOF:
				$this->listener->endDocument( $this->length );
				$eof = true;
				break 2;

			default:
				$this->fatal( 'invalid state' );
			}
		} while ( $loop );

		return !$eof;
	}

	/**
	 * Consume input text starting from the "data state".
	 *
	 * @param bool $loop True to loop while still in the data state, false to
	 *   process a single less-than sign.
	 * @return int The next state index
	 */
	protected function dataState( $loop ) {
		$re = "~ <
			(?:
				( /? )                        # 1. End tag open

				(                             # 2. Tag name
					# Try to match the ASCII letter required for the start of a start
					# or end tag. If this fails, a slash matched above can be
					# backtracked and then fed into the bogus comment alternative below.
					[a-zA-Z]

					# Then capture the rest of the tag name
					[^\t\n\f />]*
				) |

				# Comment
				!--
				(                             # 3. Comment match detector
					> | -> | # Invalid short close
					(                         # 4. Comment contents
						(?:
							(?! --> )
							(?! --!> )
							(?! --! \\z )
							(?! -- \\z )
							(?! - \\z )
							.
						)*+
					)
					(                         # 5. Comment close
						--> |   # Normal close
						--!> |  # Comment end bang
						--! |   # EOF in comment end bang state
						-- |    # EOF in comment end state
						-  |    # EOF in comment end dash state
						        # EOF in comment state
					)
				) |
				( (?i)                        # 6. Doctype
					! DOCTYPE

					# There must be at least one whitespace character to suppress
					# a parse error, but if there isn't one, this is still a
					# DOCTYPE. There is no way for the DOCTYPE string to end up
					# as a character node, the DOCTYPE subexpression must always
					# wholly match if we matched up to this point.

					( [\t\n\f ]*+ )           # 7. Required whitespace
					( [^\t\n\f >]*+ )         # 8. DOCTYPE name
					[\t\n\f ]*+
					(?:
						# After DOCTYPE name state
						PUBLIC
						( [\t\n\f ]* )            # 9. Required whitespace
						(?:
							\" ( [^\">]* ) \"? |  # 10. Double-quoted identifier
							' ( [^'>]* ) '? |     # 11. Single-quoted identifier
							# Non-match: bogus
						)
						(?:
							# After DOCTYPE public identifier state
							# Assert quoted identifier before here
							(?<= \" | ' )
							( [\t\n\f ]* )            # 12. Required whitespace
							(?:
								\" ( [^\">]* ) \"? |  # 13. Double-quoted identifier
								' ( [^'>]* ) '? |     # 14. Single-quoted identifier
								# Non-match: no system ID
							)
						)?
						|
						SYSTEM
						( [\t\n\f ]* )            # 15. Required whitespace
						(?:
							\" ( [^\">]* ) \"? |  # 16. Double-quoted identifier
							' ( [^'>]* ) '? |     # 17. Single-quoted identifier
							# Non-match: bogus
						)
						|  # No keyword is OK
					)
					[\t\n\f ]*
					( [^>]*+ )                # 18. Bogus DOCTYPE
					( >? )                    # 19. End of DOCTYPE
				) |
				( ! \[CDATA\[ ) |             # 20. CDATA section
				( [!?/] [^>]*+ ) >?           # 21. Bogus comment

				# Anything else: parse error and emit literal less-than sign.
				# We will let the match fail at this position and later check
				# for less-than signs in the resulting text node.
			)
			~xs";

		$nextState = self::STATE_DATA;
		do {
			$count = preg_match( $re, $this->text, $m, PREG_OFFSET_CAPTURE, $this->pos );
			if ( $count === false ) {
				$this->throwPregError();
			} elseif ( !$count ) {
				// Text runs to end
				$this->emitDataRange( $this->pos, $this->length - $this->pos );
				$this->pos = $this->length;
				$nextState = self::STATE_EOF;
				break;
			}

			$startPos = $m[0][1];
			$tagName = isset( $m[self::MD_TAG_NAME] ) ? $m[self::MD_TAG_NAME][0] : '';

			$this->emitDataRange( $this->pos, $startPos - $this->pos );
			$this->pos = $startPos;
			$nextPos = $m[0][1] + strlen( $m[0][0] );

			if ( isset( $m[self::MD_CDATA] ) && $m[self::MD_CDATA][1] >= 0 ) {
				if ( $this->enableCdataCallback ) {
					$isCdata = call_user_func( $this->enableCdataCallback );
				} else {
					$isCdata = false;
				}
				if ( !$isCdata ) {
					$m[self::MD_BOGUS_COMMENT] = $m[self::MD_CDATA];
				}
			} else {
				$isCdata = false;
			}

			if ( strlen( $tagName ) ) {
				// Tag
				$isEndTag = (bool)strlen( $m[self::MD_END_TAG_OPEN][0] );
				if ( !$this->ignoreNulls ) {
					$tagName = $this->handleNulls( $tagName, $m[self::MD_TAG_NAME][1] );
				}
				$tagName = strtolower( $tagName );
				$this->pos = $nextPos;
				$nextState = $this->handleAttribsAndClose( self::STATE_DATA,
					$tagName, $isEndTag, $startPos );
				$nextPos = $this->pos;
				if ( $nextState === self::STATE_EOF ) {
					break;
				}

				// Respect any state switch imposed by the parser
				$nextState = $this->state;

			} elseif ( isset( $m[self::MD_COMMENT] ) && $m[self::MD_COMMENT][1] >= 0 ) {
				// Comment
				$this->interpretCommentMatches( $m );
			} elseif ( isset( $m[self::MD_DOCTYPE] ) && $m[self::MD_DOCTYPE][1] >= 0 ) {
				// DOCTYPE
				$this->interpretDoctypeMatches( $m );
			} elseif ( isset( $m[self::MD_CDATA] ) && $m[self::MD_CDATA][1] >= 0 ) {
				// CDATA
				if ( $this->enableCdataCallback
					&& call_user_func( $this->enableCdataCallback )
				) {
					$this->pos += strlen( $m[self::MD_CDATA][0] ) + 1;
					$endPos = strpos( $this->text, ']]>', $this->pos );
					if ( $endPos === false ) {
						$this->emitCdataRange( $this->pos, $this->length - $this->pos,
							$startPos, $this->length - $startPos );
						$this->pos = $this->length;
						$nextState = self::STATE_EOF;
						break;
					} else {
						$outerEndPos = $endPos + strlen( ']]>' );
						$this->emitCdataRange( $this->pos, $endPos - $this->pos,
							$startPos, $outerEndPos - $startPos );
						$nextPos = $outerEndPos;
					}
				} else {
					// Bogus comment
					$this->error( "unexpected CDATA interpreted as bogus comment" );
					$endPos = strpos( $this->text, '>', $this->pos );
					$bogusPos = $this->pos + 2;
					if ( $endPos === false ) {
						$nextPos = $this->length;
						$contents = substr( $this->text, $bogusPos );
					} else {
						$nextPos = $endPos + 1;
						$contents = substr( $this->text, $bogusPos, $endPos - $bogusPos );
					}
					$contents = $this->handleNulls( $contents, $bogusPos );
					$this->listener->comment( $contents, $this->pos, $endPos - $this->pos );
				}
			} elseif ( isset( $m[self::MD_BOGUS_COMMENT] ) && $m[self::MD_BOGUS_COMMENT][1] >= 0 ) {
				// Bogus comment
				$contents = $m[self::MD_BOGUS_COMMENT][0];
				$bogusPos = $m[self::MD_BOGUS_COMMENT][1];
				if ( $m[0][0] === '</>' ) {
					$this->error( "empty end tag" );
					// No token emitted
				} elseif ( $m[0][0] === '</' ) {
					$this->error( 'EOF in end tag' );
					$this->listener->characters( '</', 0, 2, $m[0][1], 2 );
				} else {
					$this->error( "unexpected <{$contents[0]} interpreted as bogus comment" );
					if ( $contents[0] !== '?' ) {
						// For starting types other than <?, the initial character is
						// not in the tag contents
						$contents = substr( $contents, 1 );
						$bogusPos++;
					}

					$contents = $this->handleNulls( $contents, $bogusPos );
					$this->listener->comment( $contents, $startPos, $nextPos - $startPos );
				}
			} else {
				$this->fatal( 'unexpected data state match' );
			}
			$this->pos = $nextPos;
		} while ( $loop && $nextState === self::STATE_DATA );

		return $nextState;
	}

	/**
	 * Interpret the data state match results for a detected comment, and emit
	 * events as appropriate.
	 *
	 * @param array $m The match array
	 */
	protected function interpretCommentMatches( $m ) {
		$outerStart = $m[0][1];
		$outerLength = strlen( $m[0][0] );
		$innerStart = $outerStart + strlen( '<!--' );
		$innerLength = isset( $m[self::MD_COMMENT_INNER] ) ? strlen( $m[self::MD_COMMENT_INNER][0] ) : 0;
		$contents = $innerLength ? $m[self::MD_COMMENT_INNER][0] : '';

		if ( $m[0][0] === '<!-->' || $m[0][0] === '<!--->' ) {
			// These are special cases in the comment start state
			$this->error( 'not enough dashes in empty comment', $outerStart );
			$this->listener->comment( '', $outerStart, $outerLength );
			return;
		}

		if ( !$this->ignoreNulls ) {
			$contents = $this->handleNulls( $contents, $innerStart );
		}
		$close = $m[self::MD_COMMENT_END][0];
		$closePos = $m[self::MD_COMMENT_END][1];

		if ( !$this->ignoreErrors ) {
			if ( $close === '--!>' ) {
				$this->error( 'invalid comment end bang', $closePos );
			} elseif ( $close === '-' || $close === '--' || $close === '--!' ) {
				$this->error( 'EOF part way through comment close', $closePos );
			} elseif ( $close === '' ) {
				$this->error( 'EOF in comment', $closePos );
			}

			$dashSearchLength = $innerLength;
			while ( $dashSearchLength > 0 && $contents[$dashSearchLength - 1] === '-' ) {
				$this->error( 'invalid extra dash at comment end',
					$innerStart + $dashSearchLength - 1 );
				$dashSearchLength--;
			}

			$offset = 0;
			while ( $offset !== false && $offset < $dashSearchLength ) {
				$offset = strpos( $contents, '--', $offset );
				if ( $offset !== false ) {
					$this->error( 'bare "--" found in comment', $innerStart + $offset );
					$offset += 2;
				}
			}
		}

		$this->listener->comment( $contents, $outerStart, $outerLength );
	}

	/**
	 * Interpret the data state match results for a detected DOCTYPE token,
	 * and emit events as appropriate.
	 *
	 * @param array $m The match array
	 */
	protected function interpretDoctypeMatches( $m ) {
		$igerr = $this->ignoreErrors;
		$name = null;
		$public = null;
		$system = null;
		$quirks = false;

		// Missing ">" can only be caused by EOF
		$eof = !strlen( $m[self::MD_DT_END][0] );

		if ( strlen( $m[self::MD_DT_BOGUS][0] ) ) {
			// Bogus DOCTYPE state
			if ( !$igerr ) {
				$this->error( 'invalid DOCTYPE contents', $m[self::MD_DT_BOGUS][1] );
			}
			// Set quirks mode unless there was a properly quoted SYSTEM identifier
			$haveDq = isset( $m[self::MD_DT_SYSTEM_DQ] ) && $m[self::MD_DT_SYSTEM_DQ][1] >= 0;
			$haveSq = isset( $m[self::MD_DT_SYSTEM_SQ] ) && $m[self::MD_DT_SYSTEM_SQ][1] >= 0;
			if ( !$haveDq && !$haveSq ) {
				$quirks = true;
			}
			// EOF in the bogus state does not set quirks mode (but it is a parse error)
			if ( $eof && !$igerr ) {
				$this->error( 'unterminated DOCTYPE' );
			}
		} elseif ( $eof ) {
			if ( !$igerr ) {
				$this->error( 'unterminated DOCTYPE' );
			}
			$quirks = true;
		}

		if ( !$igerr && !$eof && !strlen( $m[self::MD_DT_NAME_WS][0] ) ) {
			$this->error( 'missing whitespace', $m[self::MD_DT_NAME_WS][1] );
		}

		if ( strlen( $m[self::MD_DT_NAME][0] ) ) {
			// DOCTYPE name
			$name = $this->handleNulls( strtolower( $m[self::MD_DT_NAME][0] ), $m[self::MD_DT_NAME][1] );
		} else {
			if ( !$eof && !$igerr ) {
				$this->error( 'missing DOCTYPE name',
					$m[self::MD_DOCTYPE][1] + strlen( '!DOCTYPE' ) );
			}
			$quirks = true;
		}

		if ( isset( $m[self::MD_DT_PUBLIC_WS] ) && $m[self::MD_DT_PUBLIC_WS][1] >= 0 ) {
			// PUBLIC keyword found
			$public = $this->interpretDoctypeQuoted( $m,
				self::MD_DT_PUBLIC_DQ, self::MD_DT_PUBLIC_SQ, $quirks );
			if ( $public === null ) {
				$quirks = true;
				if ( !$eof && !$igerr ) {
					$this->error( 'missing public identifier', $m[self::MD_DT_PUBLIC_WS][1] );
				}
			} elseif ( !$igerr && !$eof && !strlen( $m[self::MD_DT_PUBLIC_WS][0] ) ) {
				$this->error( 'missing whitespace', $m[self::MD_DT_PUBLIC_WS][1] );
			}

			// Check for a system ID after the public ID
			$haveDq = isset( $m[self::MD_DT_PUBSYS_DQ] ) && $m[self::MD_DT_PUBSYS_DQ][1] >= 0;
			$haveSq = isset( $m[self::MD_DT_PUBSYS_SQ] ) && $m[self::MD_DT_PUBSYS_SQ][1] >= 0;
			if ( $haveDq || $haveSq ) {
				if ( !$igerr && !strlen( $m[self::MD_DT_PUBSYS_WS][0] ) ) {
					$this->error( 'missing whitespace', $m[self::MD_DT_PUBSYS_WS][1] );
				}
				$system = $this->interpretDoctypeQuoted( $m,
					self::MD_DT_PUBSYS_DQ, self::MD_DT_PUBSYS_SQ, $quirks );
			}
		} elseif ( isset( $m[self::MD_DT_SYSTEM_WS] ) && $m[self::MD_DT_SYSTEM_WS][1] >= 0 ) {
			// SYSTEM keyword found
			$system = $this->interpretDoctypeQuoted( $m,
				self::MD_DT_SYSTEM_DQ, self::MD_DT_SYSTEM_SQ, $quirks );
			if ( $system === null ) {
				$quirks = true;
				$this->error( 'missing system identifier', $m[self::MD_DT_SYSTEM_WS][1] );
			} elseif ( !$igerr && !strlen( $m[self::MD_DT_SYSTEM_WS][0] ) ) {
				$this->error( 'missing whitespace', $m[self::MD_DT_SYSTEM_WS][1] );
			}

		}
		$this->listener->doctype( $name, $public, $system, $quirks, $m[0][1], strlen( $m[0][0] ) );
	}

	/**
	 * DOCTYPE helper which interprets a quoted string (or lack thereof)
	 * @param array $m
	 * @param int $dq
	 * @param int $sq
	 * @param bool &$quirks
	 * @return string|null The quoted value, with nulls replaced.
	 */
	protected function interpretDoctypeQuoted( $m, $dq, $sq, &$quirks ) {
		if ( isset( $m[$dq] ) && $m[$dq][1] >= 0 ) {
			$value = $m[$dq][0];
			$startPos = $m[$dq][1];
		} elseif ( isset( $m[$sq] ) && $m[$sq][1] >= 0 ) {
			$value = $m[$sq][0];
			$startPos = $m[$sq][1];
		} else {
			return null;
		}
		$endPos = $startPos + strlen( $value );
		if ( $endPos >= $this->length ) {
			// This is a parse error, but we already emitted a generic EOF error
			$quirks = true;
		} elseif ( $this->text[$endPos] === '>' ) {
			$this->error( 'DOCTYPE identifier terminated by ">"', $endPos );
			$quirks = true;
		}
		$value = $this->handleNulls( $value, $startPos );
		return $value;
	}

	/**
	 * Generic helper for all those points in the spec where U+0000 needs to be
	 * replaced with U+FFFD with a parse error issued.
	 *
	 * @param string $text The text to be converted
	 * @param int $sourcePos The input byte offset from which $text was
	 *   extracted, for error position reporting.
	 * @return string The converted text
	 */
	protected function handleNulls( $text, $sourcePos ) {
		if ( $this->ignoreNulls ) {
			return $text;
		}
		if ( !$this->ignoreErrors ) {
			$offset = 0;
			while ( true ) {
				$nullPos = strpos( $text, "\0", $offset );
				if ( $nullPos === false ) {
					break;
				}
				$this->error( "replaced null character", $sourcePos + $nullPos );
				if ( $nullPos < strlen( $text ) - 1 ) {
					$offset = $nullPos + 1;
				} else {
					break;
				}
			}
		}
		return str_replace( "\0", self::REPLACEMENT_CHAR, $text );
	}

	/**
	 * Generic helper for points in the spec which say that an error should
	 * be issued when certain ASCII characters are seen, with no other action
	 * taken.
	 *
	 * @param string $mask Mask for strcspn
	 * @param string $text The input text
	 * @param int $offset The start of the range within $text to search
	 * @param int $length The length of the range within $text to search
	 * @param int $sourcePos The offset within the input text corresponding
	 *   to $text, for error position reporting.
	 */
	protected function handleAsciiErrors( $mask, $text, $offset, $length, $sourcePos ) {
		while ( $length > 0 ) {
			$validLength = strcspn( $text, $mask, $offset, $length );
			$offset += $validLength;
			$length -= $validLength;
			if ( $length <= 0 ) {
				break;
			}
			$char = $text[$offset];
			$codepoint = ord( $char );
			if ( $codepoint < 0x20 || $codepoint >= 0x7f ) {
				$this->error( sprintf( 'unexpected U+00%02X', $codepoint ), $offset + $sourcePos );
			} else {
				$this->error( "unexpected \"$char\"", $offset + $sourcePos );
			}
			$offset++;
			$length--;
		}
	}

	/**
	 * Expand character references in some text, and emit errors as appropriate.
	 * @param string $text The text to expand
	 * @param int $sourcePos The input position of $text
	 * @param bool $inAttr True if the text is within an attribute value
	 * @param string $additionalAllowedChar An unused string which the spec
	 *   inexplicably spends a lot of space telling you how to derive. It
	 *   suppresses errors in a place where no errors are emitted anyway.
	 * @return string The expanded text
	 */
	protected function handleCharRefs( $text, $sourcePos, $inAttr = false,
		$additionalAllowedChar = ''
	) {
		if ( $this->ignoreCharRefs ) {
			return $text;
		}

		static $re;
		if ( $re === null ) {
			$knownNamed = HTMLData::$namedEntityRegex;
			$re = "~
				( .*? )                      # 1. prefix
				&
				(?:
					\# (?:
						0*(\d+)           |  # 2. decimal
						[xX]0*([0-9A-Fa-f]+) # 3. hexadecimal
					)
					( ; ) ?                  # 4. semicolon
					|
					( \# )                   # 5. bare hash
					|
					($knownNamed)            # 6. known named
					(?:
						(?<! ; )             # Assert no semicolon prior
						( [=a-zA-Z0-9] )     # 7. attribute suffix
					)?
					|
					( [a-zA-Z0-9]+ ; )       # 8. invalid named
				)
				# S = study, for efficient knownNamed
				# A = anchor, to avoid unnecessary movement of the whole pattern on failure
				~xAsS";
		}
		$out = '';
		$pos = 0;
		$length = strlen( $text );
		$matches = [];
		$count = preg_match_all( $re, $text, $matches, PREG_SET_ORDER );
		if ( $count === false ) {
			$this->throwPregError();
		}

		foreach ( $matches as $m ) {
			$out .= $m[self::MC_PREFIX];
			$errorPos = $sourcePos + $pos + strlen( $m[self::MC_PREFIX] );
			$lastPos = $pos;
			$pos += strlen( $m[0] );

			if ( isset( $m[self::MC_HASH] ) && strlen( $m[self::MC_HASH] ) ) {
				// Bare &#
				$this->error( 'Expected digits after &#', $errorPos );
				$out .= '&#';
				continue;
			}

			$knownNamed = isset( $m[self::MC_NAMED] ) ? $m[self::MC_NAMED] : '';
			$attributeSuffix = isset( $m[self::MC_SUFFIX] ) ? $m[self::MC_SUFFIX] : '';

			$haveSemicolon =
				( isset( $m[self::MC_SEMICOLON] ) && strlen( $m[self::MC_SEMICOLON] ) )
				|| ( strlen( $knownNamed ) && $knownNamed[ strlen( $knownNamed ) - 1 ] === ';' )
				|| ( isset( $m[self::MC_INVALID] ) && strlen( $m[self::MC_INVALID] ) );

			if ( $inAttr && !$haveSemicolon ) {
				if ( strlen( $attributeSuffix ) ) {
					if ( !$this->ignoreErrors && $attributeSuffix === '=' ) {
						$this->error( 'invalid equals sign after named character reference' );
					}
					$out .= '&' . $knownNamed . $attributeSuffix;
					continue;
				}
			}

			if ( !$this->ignoreErrors && !$haveSemicolon ) {
				$this->error( 'character reference missing semicolon', $errorPos );
			}

			if ( isset( $m[self::MC_DECIMAL] ) && strlen( $m[self::MC_DECIMAL] ) ) {
				// Decimal
				if ( strlen( $m[self::MC_DECIMAL] ) > 7 ) {
					$this->error( 'invalid numeric reference', $errorPos );
					$out .= self::REPLACEMENT_CHAR;
					continue;
				}
				$codepoint = intval( $m[self::MC_DECIMAL] );
			} elseif ( isset( $m[self::MC_HEXDEC] ) && strlen( $m[self::MC_HEXDEC] ) ) {
				// Hexadecimal
				if ( strlen( $m[self::MC_HEXDEC] ) > 6 ) {
					$this->error( 'invalid numeric reference', $errorPos );
					$out .= self::REPLACEMENT_CHAR;
					continue;
				}
				$codepoint = intval( $m[self::MC_HEXDEC], 16 );
			} elseif ( $knownNamed !== '' ) {
				$out .= HTMLData::$namedEntityTranslations[$knownNamed] . $attributeSuffix;
				continue;
			} elseif ( isset( $m[self::MC_INVALID] ) && strlen( $m[self::MC_INVALID] ) ) {
				if ( !$this->ignoreErrors ) {
					$this->error( 'invalid named reference', $errorPos );
				}
				$out .= '&' . $m[self::MC_INVALID];
				continue;
			} else {
				$this->fatal( 'unable to identify char ref submatch' );
			}

			// Interpret $codepoint
			if ( $codepoint === 0
				|| ( $codepoint >= 0xD800 && $codepoint <= 0xDFFF )
				|| $codepoint > 0x10FFFF
			) {
				if ( !$this->ignoreErrors ) {
					$this->error( 'invalid numeric reference', $errorPos );
				}
				$out .= self::REPLACEMENT_CHAR;
			} elseif ( isset( HTMLData::$legacyNumericEntities[$codepoint] ) ) {
				if ( !$this->ignoreErrors ) {
					$this->error( 'invalid reference to non-ASCII control character', $errorPos );
				}
				$out .= HTMLData::$legacyNumericEntities[$codepoint];
			} else {
				if ( !$this->ignoreErrors ) {
					$disallowedCodepoints = [
						0x000B => true,
						0xFFFE => true, 0xFFFF => true,
						0x1FFFE => true, 0x1FFFF => true,
						0x2FFFE => true, 0x2FFFF => true,
						0x3FFFE => true, 0x3FFFF => true,
						0x4FFFE => true, 0x4FFFF => true,
						0x5FFFE => true, 0x5FFFF => true,
						0x6FFFE => true, 0x6FFFF => true,
						0x7FFFE => true, 0x7FFFF => true,
						0x8FFFE => true, 0x8FFFF => true,
						0x9FFFE => true, 0x9FFFF => true,
						0xAFFFE => true, 0xAFFFF => true,
						0xBFFFE => true, 0xBFFFF => true,
						0xCFFFE => true, 0xCFFFF => true,
						0xDFFFE => true, 0xDFFFF => true,
						0xEFFFE => true, 0xEFFFF => true,
						0xFFFFE => true, 0xFFFFF => true,
						0x10FFFE => true, 0x10FFFF => true ];
					if (
						( $codepoint >= 1 && $codepoint <= 8 ) ||
						( $codepoint >= 0x0d && $codepoint <= 0x1f ) ||
						( $codepoint >= 0x7f && $codepoint <= 0x9f ) ||
						( $codepoint >= 0xfdd0 && $codepoint <= 0xfdef ) ||
						isset( $disallowedCodepoints[$codepoint] )
					) {
						$this->error( 'invalid numeric reference to control character',
							$errorPos );
					}
				}

				$out .= \UtfNormal\Utils::codepointToUtf8( $codepoint );
			}
		}
		if ( $pos < $length ) {
			$out .= substr( $text, $pos );
		}
		return $out;
	}

	/**
	 * Emit a range of the input text as a character token, and emit related
	 * errors, with validity rules as per the data state.
	 *
	 * @param int $pos Offset within the input text
	 * @param int $length The length of the range
	 */
	protected function emitDataRange( $pos, $length ) {
		if ( $length === 0 ) {
			return;
		}
		if ( $this->ignoreCharRefs && $this->ignoreNulls && $this->ignoreErrors ) {
			$this->listener->characters( $this->text, $pos, $length, $pos, $length );
		} else {
			if ( !$this->ignoreErrors ) {
				// Any bare "<" in a data state text node is a parse error.
				// Uniquely to the data state, nulls are just flagged as errors
				// and passed through, they are not replaced.
				$this->handleAsciiErrors( "<\0", $this->text, $pos, $length, 0 );
			}

			$text = substr( $this->text, $pos, $length );
			$text = $this->handleCharRefs( $text, $pos );
			$this->listener->characters( $text, 0, strlen( $text ), $pos, $length );
		}
	}

	/**
	 * Emit a range of characters from the input text, with validity rules as
	 * per the CDATA section state.
	 *
	 * @param int $innerPos The position after the <![CDATA[
	 * @param int $innerLength The length of the string not including the terminating ]]>
	 * @param int $outerPos The position of the start of the <!CDATA[
	 * @param int $outerLength The length of the whole input region being emitted
	 */
	protected function emitCdataRange( $innerPos, $innerLength, $outerPos, $outerLength ) {
		$this->listener->characters( $this->text, $innerPos, $innerLength,
			$outerPos, $outerLength );
	}

	/**
	 * Emit a range of characters from the input text, either from RCDATA,
	 * RAWTEXT, script data or PLAINTEXT. The only difference between these
	 * states is whether or not character references are expanded, so we take
	 * that as a parameter.
	 *
	 * @param bool $ignoreCharRefs
	 * @param int $pos The input position
	 * @param int $length The length of the range to be emitted
	 */
	protected function emitRawTextRange( $ignoreCharRefs, $pos, $length ) {
		if ( $length === 0 ) {
			return;
		}
		$ignoreCharRefs = $ignoreCharRefs || $this->ignoreCharRefs;
		if ( $ignoreCharRefs && $this->ignoreNulls ) {
			$this->listener->characters( $this->text, $pos, $length, $pos, $length );
		} else {
			$text = substr( $this->text, $pos, $length );
			if ( !$ignoreCharRefs ) {
				$text = $this->handleCharRefs( $text, $pos );
			}
			$text = $this->handleNulls( $text, $pos );
			$this->listener->characters( $text, 0, strlen( $text ), $pos, $length );
		}
	}

	/**
	 * The entry point for the RCDATA and RAWTEXT states.
	 * @param bool $ignoreCharRefs True to ignore character references regardless
	 *   of configuration, false to respect the configuration.
	 * @return int The next state index
	 */
	protected function textElementState( $ignoreCharRefs ) {
		if ( $this->appropriateEndTag === null ) {
			$this->emitRawTextRange( $ignoreCharRefs, $this->pos, $this->length - $this->pos );
			$this->pos = $this->length;
			return self::STATE_EOF;
		}

		$re = "~</
			{$this->appropriateEndTag}
			# Assert that the end tag name state is exited appropriately,
			# since the anything else case leads to the tag being treated as
			# a literal
			(?=[\t\n\f />])
			~ix";

		do {
			$count = preg_match( $re, $this->text, $m, PREG_OFFSET_CAPTURE, $this->pos );

			if ( $count === false ) {
				$this->throwPregError();
			} elseif ( !$count ) {
				// Text runs to end
				$this->emitRawTextRange( $ignoreCharRefs, $this->pos, $this->length - $this->pos );
				$this->pos = $this->length;
				return self::STATE_EOF;
			}
			$startPos = $m[0][1];

			// Emit text before tag
			$this->emitRawTextRange( $ignoreCharRefs, $this->pos, $startPos - $this->pos );

			$matchLength = strlen( $m[0][0] );
			$this->pos = $startPos + $matchLength;
			$nextState = $this->handleAttribsAndClose( self::STATE_RCDATA,
				$this->appropriateEndTag, true, $startPos );
		} while ( $nextState === self::STATE_RCDATA );
		return $nextState;
	}

	/**
	 * Advance $this->pos, consuming all tag attributes found at the current
	 * position. The new position will be at the end of the tag or at the end
	 * of the input string.
	 *
	 * To improve performance of consumers which don't need to read the
	 * attribute array, interpretation of the PCRE match results is deferred.
	 *
	 * - @todo: Make deferral configurable.
	 * - @todo: Measure performance improvement, assess whether the LazyAttributes
	 *   feature is warranted.
	 *
	 * @return array Attributes
	 */
	protected function consumeAttribs() {
		$re = '~
			[\t\n\f ]*+  # Ignored whitespace before attribute name
			(?! /> )     # Do not consume self-closing end of tag
			(?! > )      # Do not consume normal closing bracket

			(?:
				# Before attribute name state
				# A bare slash at this point, not part of a self-closing end tag, is
				# consumed and ignored (with a parse error), returning to the before
				# attribute name state.
				( / ) |    # 1. Bare slash

				# Attribute name state
				# Note that the first character can be an equals sign, this is a parse error
				# but still generates an attribute called "=". Thus the only way the match
				# could fail here is due to EOF.

				( [^\t\n\f />] [^\t\n\f =/>]*+ )  # 2. Attribute name

				# After attribute name state
				[\t\n\f ]*

				(?:
					=
					# Before attribute value state
					# Ignore whitespace
					[\t\n\f ]*+
					(?:
						# If an end-quote is omitted, the attribute will run to the end of the
						# string, leaving no closing bracket. So the caller will detect the
						# unexpected EOF and will not emit the tag, which is correct.
						" ( [^"]*+ ) "? |       # 3. Double-quoted attribute value
						\' ( [^\']*+ ) \'? |    # 4. Single-quoted attribute value
						( [^\t\n\f >]*+ )       # 5. Unquoted attribute value
					)
					# Or nothing: an attribute with an empty value. The attribute name was
					# terminated by a slash, closing bracket or EOF
					|
				)
			)
			# The /A modifier causes preg_match_all to give contiguous chunks
			~xA';
		$count = preg_match_all( $re, $this->text, $m,
			PREG_SET_ORDER | PREG_OFFSET_CAPTURE, $this->pos );
		if ( $count === false ) {
			$this->throwPregError();
		} elseif ( $count ) {
			$this->pos = $m[$count - 1][0][1] + strlen( $m[$count - 1][0][0] );
			$attribs = new LazyAttributes( $m, function ( $m ) {
				return $this->interpretAttribMatches( $m );
			} );
		} else {
			$attribs = new PlainAttributes();
		}

		// Consume trailing whitespace. This is strictly part of the before attribute
		// name state, but we didn't consume it in the regex since we used a principle
		// of one match equals one attribute.
		$this->pos += strspn( $this->text, "\t\n\f ", $this->pos );
		return $attribs;
	}

	/**
	 * Interpret the results of the attribute preg_match_all(). Emit errors as
	 * appropriate and return an associative array.
	 *
	 * @param array $matches
	 * @return array
	 */
	protected function interpretAttribMatches( $matches ) {
		$attributes = [];
		foreach ( $matches as $m ) {
			if ( strlen( $m[self::MA_SLASH][0] ) ) {
				$this->error( 'unexpected bare slash', $m[self::MA_SLASH][1] );
				continue;
			}
			$name = $m[self::MA_NAME][0];
			if ( !$this->ignoreErrors ) {
				$this->handleAsciiErrors( "\"'<=", $name, 0, strlen( $name ), $m[self::MA_NAME][1] );
			}
			if ( !$this->ignoreNulls ) {
				$name = $this->handleNulls( $m[self::MA_NAME][0], $m[self::MA_NAME][1] );
			}
			$name = strtolower( $name );
			$additionalAllowedChar = '';
			if ( isset( $m[self::MA_DQUOTED] ) && $m[self::MA_DQUOTED][1] >= 0 ) {
				// Double-quoted attribute value
				$additionalAllowedChar = '"';
				$value = $m[self::MA_DQUOTED][0];
				$pos = $m[self::MA_DQUOTED][1];
			} elseif ( isset( $m[self::MA_SQUOTED] ) && $m[self::MA_SQUOTED][1] >= 0 ) {
				// Single-quoted attribute value
				$additionalAllowedChar = "'";
				$value = $m[self::MA_SQUOTED][0];
				$pos = $m[self::MA_SQUOTED][1];
			} elseif ( isset( $m[self::MA_UNQUOTED] ) && $m[self::MA_UNQUOTED][1] >= 0 ) {
				// Unquoted attribute value
				$value = $m[self::MA_UNQUOTED][0];
				$pos = $m[self::MA_UNQUOTED][1];
				// Search for parse errors
				if ( !$this->ignoreErrors ) {
					if ( $value === '' ) {
						// ">" in the before attribute value state is a parse error
						$this->error( 'empty unquoted attribute', $pos );
					}
					$this->handleAsciiErrors( "\"'<=`", $value, 0, strlen( $value ), $pos );
				}
			} else {
				$value = '';
			}
			if ( $additionalAllowedChar && !$this->ignoreErrors ) {
				// After attribute value (quoted) state
				// Quoted attributes must be followed by a space, "/" or ">"
				$aavPos = $m[0][1] + strlen( $m[0][0] );
				if ( $aavPos < $this->length ) {
					$aavChar = $this->text[$aavPos];
					if ( !preg_match( '~^[\t\n\f />]~', $aavChar ) ) {
						$this->error( 'missing space between attributes', $aavPos );
					}
				}
			}
			if ( $value !== '' ) {
				if ( !$this->ignoreNulls ) {
					$value = $this->handleNulls( $value, $pos );
				}
				if ( !$this->ignoreCharRefs ) {
					$value = $this->handleCharRefs( $value, $pos, true, $additionalAllowedChar );
				}
			}
			if ( isset( $attributes[$name] ) ) {
				$this->error( "duplicate attribute", $m[0][1] );
			} else {
				$attributes[$name] = $value;
			}
		}
		return $attributes;
	}

	/**
	 * Consume attributes, and the closing bracket which follows attributes.
	 * Emit the appropriate tag event, or in the case of broken attributes in
	 * text states, emit characters.
	 *
	 * @param int $state The current state
	 * @param string $tagName The normalized tag name
	 * @param bool $isEndTag True if this is an end tag, false if it is a start tag
	 * @param int $startPos The input position of the start of the current tag.
	 * @return int The next state
	 */
	protected function handleAttribsAndClose( $state, $tagName, $isEndTag, $startPos ) {
		$attribStartPos = $this->pos;
		$attribs = $this->consumeAttribs();
		$pos = $this->pos;

		// Literal characters are emitted on EOF or "anything else" from the
		// end tag substates of the text states.
		// (spec ref 8.2.4 sections 11-19, 25-27)
		$isDataState = $state === self::STATE_DATA;
		$isLiteral = $attribStartPos === $pos && !$isDataState;

		if ( $pos >= $this->length ) {
			$this->error( 'unexpected end of file inside tag' );
			if ( $isLiteral ) {
				$this->listener->characters( $this->text,
					$startPos, $this->length - $startPos,
					$startPos, $this->length - $startPos );
			}
			return self::STATE_EOF;
		}
		if ( $isEndTag && !$this->ignoreErrors && $attribs->count() ) {
			$this->error( 'end tag has an attribute' );
		}

		if ( $this->text[$pos] === '/' && $this->text[$pos + 1] === '>' ) {
			$pos += 2;
			$selfClose = true;
		} elseif ( $this->text[$pos] === '>' ) {
			$pos++;
			$selfClose = false;
		} elseif ( $isLiteral ) {
			$this->listener->characters( $this->text,
				$startPos, $attribStartPos - $startPos,
				$startPos, $attribStartPos - $startPos );
			return $state;
		} else {
			$this->fatal( 'failed to find an already-matched ">"' );
		}
		$this->pos = $pos;
		if ( $isEndTag ) {
			if ( $selfClose ) {
				$this->error( 'self-closing end tag' );
			}
			$this->listener->endTag( $tagName, $startPos, $pos - $startPos );
		} else {
			$this->listener->startTag( $tagName, $attribs, $selfClose,
				$startPos, $pos - $startPos );
		}
		return self::STATE_DATA;
	}

	/**
	 * Process input text in the PLAINTEXT state
	 * @return int The next state index
	 */
	protected function plaintextState() {
		$this->emitRawTextRange( true, $this->pos, $this->length - $this->pos );
		return self::STATE_EOF;
	}

	/**
	 * Process input text in the script data state
	 * @return int The next state index
	 */
	protected function scriptDataState() {
		if ( $this->appropriateEndTag === null ) {
			$this->pos = $this->length;
			return self::STATE_EOF;
		}

		$re = <<<REGEX
~
			(?: # Outer loop start
				# Script data state
				# Stop iteration if we previously matched an appropriate end tag.
				# This is a conditional subpattern: if capture 1 previously
				# matched, then run the pattern /$./ which always fails.
				(?(1) $. )
				.*?
				(?:
					$ |
					(
						</ {$this->appropriateEndTag}
						# If we hit the "anything else" case in the script data
						# end tag name state, don't exit
						(?= [\t\n\f />] )
					) | # 1. Appropriate end tag
					<!--
					# Script data escaped dash dash state
					# Hyphens at this point are consumed without a state transition
					# and so are not part of a comment-end.
					-*+

					(?: # Inner loop start
						# Script data escaped state
						.*?
						(?:
							$ |
							# Stop at, but do not consume, comment-close or end tag.
							# This causes the inner loop to exit, since restarting the
							# inner loop at this input position will cause the loop
							# body to match zero characters. Repeating a zero-character
							# match causes the repeat to terminate.
							(?= --> ) |
							(?= </ {$this->appropriateEndTag} [\t\n\f />] ) |
							<script [\t\n\f />]
							# Script data double escaped state
							.*?
							(?:
								$ |
								# Stop at, but do not consume, comment-close
								(?= --> ) |
								</script [\t\n\f />]
							)
						)
					)*


					# Consume the comment close which exited the inner loop, if any
					(?: --> )?
				)
			)*+
			~xsiA
REGEX;

		do {
			$count = preg_match( $re, $this->text, $m, 0, $this->pos );
			if ( $count === false ) {
				$this->throwPregError();
			} elseif ( !$count ) {
				$this->fatal( 'unexpected regex failure: this pattern can match zero characters' );
			}

			$startPos = $this->pos;
			$matchLength = strlen( $m[0] );
			$endTagLength = isset( $m[1] ) ? strlen( $m[1] ) : 0;
			$textLength = $matchLength - $endTagLength;
			$this->emitRawTextRange( true, $startPos, $textLength );
			$this->pos = $startPos + $matchLength;
			$tagStartPos = $startPos + $textLength;

			if ( $endTagLength ) {
				$nextState = $this->handleAttribsAndClose( self::STATE_SCRIPT_DATA,
					$this->appropriateEndTag, true, $tagStartPos );
			} else {
				$nextState = self::STATE_EOF;
			}
		} while ( $nextState === self::STATE_SCRIPT_DATA );
		return $nextState;
	}

	/**
	 * Emit a parse error event.
	 * @param string $text The error message
	 * @param int|null $pos The error position, or null to use the current position
	 */
	protected function error( $text, $pos = null ) {
		if ( !$this->ignoreErrors ) {
			if ( $pos === null ) {
				$pos = $this->pos;
			}
			$this->listener->error( $text, $pos );
		}
	}

	/**
	 * Throw an exception for a specified reason. This is used for API errors
	 * and assertion-like sanity checks.
	 * @param string $text The error message
	 */
	protected function fatal( $text ) {
		throw new TokenizerError( __CLASS__ . ": " . $text );
	}

	/**
	 * Interpret preg_last_error() and throw a suitable exception. This is
	 * called when preg_match() or similar returns false.
	 *
	 * Notes for users:
	 *
	 * - PCRE internal error: may be due to JIT stack space exhaustion prior
	 *   to PHP 7, due to excessive recursion. Increase stack space.
	 *
	 * - pcre.backtrack_limit exhausted: The backtrack limit should be at least
	 *   double the input size, the defaults are way too small. Increase it in
	 *   configuration.
	 */
	protected function throwPregError() {
		if ( defined( 'PREG_JIT_STACKLIMIT_ERROR' ) ) {
			$PREG_JIT_STACKLIMIT_ERROR = PREG_JIT_STACKLIMIT_ERROR;
		} else {
			$PREG_JIT_STACKLIMIT_ERROR = 'undefined error';
		}
		switch ( preg_last_error() ) {
		case PREG_NO_ERROR:
			$msg = "PCRE returned false but gave PREG_NO_ERROR";
			break;

		case PREG_INTERNAL_ERROR:
			$msg = "PCRE internal error";
			break;

		case PREG_BACKTRACK_LIMIT_ERROR:
			$msg = "pcre.backtrack_limit exhausted";
			break;

		case PREG_RECURSION_LIMIT_ERROR:
			$msg = "pcre.recursion_limit exhausted";
			break;

		case $PREG_JIT_STACKLIMIT_ERROR:
			$msg = "PCRE JIT stack space exhausted";
			break;

		case PREG_BAD_UTF8_ERROR:
		case PREG_BAD_UTF8_OFFSET_ERROR:
		default:
			$msg = "PCRE unexpected error";
		}

		throw new TokenizerError( __CLASS__ . ": $msg" );
	}
}
