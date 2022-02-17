<?php

namespace CirrusSearch\Parser\QueryStringRegex;

use CirrusSearch\Parser\AST\BooleanClause;
use CirrusSearch\Parser\AST\EmptyQueryNode;
use CirrusSearch\Parser\AST\KeywordFeatureNode;
use CirrusSearch\Parser\AST\NamespaceHeaderNode;
use CirrusSearch\Parser\AST\NegatedNode;
use CirrusSearch\Parser\AST\ParsedBooleanNode;
use CirrusSearch\Parser\AST\ParsedNode;
use CirrusSearch\Parser\AST\ParsedQuery;
use CirrusSearch\Parser\AST\ParseWarning;
use CirrusSearch\Parser\AST\PhraseQueryNode;
use CirrusSearch\Parser\AST\Visitor\KeywordNodeVisitor;
use CirrusSearch\Parser\AST\WordsQueryNode;
use CirrusSearch\Parser\NamespacePrefixParser;
use CirrusSearch\Parser\ParsedQueryClassifiersRepository;
use CirrusSearch\Parser\QueryParser;
use CirrusSearch\Query\KeywordFeature;
use CirrusSearch\Query\PrefixFeature;
use CirrusSearch\Search\Escaper;
use CirrusSearch\Util;
use Wikimedia\Assert\Assert;

/**
 * Full text query parser that uses regex to parse its token.
 *
 * Far from being a state of the art parser it detects most of its
 * tokens using regular expression. And make arbitrary decisions
 * at tokenization.
 *
 * The tokenizer will understand few token types:
 * - WHITESPACE: all unicode whitespace and control chars ([\pZ\pC])
 *   the WHITESPACE token is ignored and never presented to the parser
 * - EOF: dummy type used to mark end of string
 * - BOOL_AND/BOOL_OR/BOOL_NOT: explicit boolean opeartors
 * - PARSED_NODE: complex type (usually part of the query)
 *
 * PARSED_NODE is a type that groups:
 * - Keywords
 * - Phrase
 * - Words
 * - Wildcards/Prefix
 *
 * Phrase does not have its own token " and is part the tokenization and is never exposed
 * to the parser.
 * Same for negation prefix (! and -), they are parsed at tokenization time.
 *
 * NOTE that this parser is broken by design:
 * - no lexical context support, we first parse keywords
 * - no support for groupings (parenthesis)
 */
class QueryStringRegexParser implements QueryParser {
	/**
	 * Whitespace regex including unicode and some control chars
	 */
	const WHITESPACE_REGEX = '/\G[\pZ\pC]+/u';

	const QUERY_LEN_HARD_LIMIT = 2048;

	/**
	 * see T66350
	 */
	const GERSHAYIM_REGEX = '/(\p{L}{2,})(?:")(\p{L})(?=[^\p{L}]|$)/u';

	/**
	 * Supported explicit boolean operator
	 *
	 */
	const EXPLICIT_BOOLEAN_OPERATOR = '/\G(?:(?<AND>AND|&&)|(?<OR>OR|\|\|)|(?<NOT>NOT))(?![^\pZ\pC"])/u';

	/**
	 * Keywords which do not count when measuring the length of the the query
	 */
	const UNLIMITED_KEYWORDS = [
		'incategory' => true, // T111694
		'articletopic' => true // T242560
	];

	/**
	 * @var \CirrusSearch\Parser\KeywordRegistry
	 */
	private $keywordRegistry;

	/**
	 * @var Escaper
	 */
	private $escaper;

	/**
	 * @var ParsedQueryClassifiersRepository
	 */
	private $classifierRepository;

	/**
	 * @var string|null user query (null when not yet cleaned up)
	 */
	private $query;

	/**
	 * @var string
	 */
	private $questionMarkStripLevel;

	/**
	 * @var string the raw query as received by the search engine
	 */
	private $rawQuery;

	/**
	 * @var KeywordParser
	 */
	private $keywordParser;

	/**
	 * @var PhraseQueryParser
	 */
	private $phraseQueryParser;

	/**
	 * @var NonPhraseParser
	 */
	private $nonPhraseParser;

	/**
	 * @var OffsetTracker track offsets of parsed keywords
	 */
	private $keywordOffsetsTracker;

	/**
	 * @var ParsedNode[]
	 */
	private $preTaggedNodes = [];

	/**
	 * Token set after calling nextToken
	 * @var Token|null
	 */
	private $token;

	/**
	 * Last token seen (set within nextToken)
	 * @var Token|null
	 */
	private $lookBehind;

	/**
	 * Current offset
	 * NOTE: offset is moved after call advance
	 * @var int
	 */
	private $offset;

	/**
	 * @var bool[] indexed cleanups applied (indexed by the cleanup type)
	 * @see ParsedQuery::hasCleanup()
	 */
	private $queryCleanups = [];

	/**
	 * Errors detected while parsing the query
	 * @var ParseWarning[]
	 */
	private $warnings = [];

	/**
	 * @var NamespaceHeaderNode|null
	 */
	private $namespaceHeader;

	/**
	 * @var NamespacePrefixParser
	 */
	private $namespacePrefixParser;
	/**
	 * Default
	 */
	const DEFAULT_OCCUR = BooleanClause::MUST;

	/**
	 * @var int
	 */
	private $maxQueryLen;

	/**
	 * @param \CirrusSearch\Parser\KeywordRegistry $keywordRegistry
	 * @param Escaper $escaper
	 * @param string $qmarkStripLevel level of question mark stripping to apply
	 * @param ParsedQueryClassifiersRepository $classifierRepository
	 * @param NamespacePrefixParser $namespacePrefixParser
	 * @param int|null $maxQueryLen maximum length of the query in chars
	 * @see Util::stripQuestionMarks() for acceptable $qmarkStripLevel values
	 */
	public function __construct(
		\CirrusSearch\Parser\KeywordRegistry $keywordRegistry,
		Escaper $escaper,
		$qmarkStripLevel,
		ParsedQueryClassifiersRepository $classifierRepository,
		NamespacePrefixParser $namespacePrefixParser,
		?int $maxQueryLen
	) {
		$this->keywordRegistry = $keywordRegistry;
		$this->escaper = $escaper;
		$this->keywordParser = new KeywordParser();
		$this->phraseQueryParser = new PhraseQueryParser( $escaper );
		$this->nonPhraseParser = new NonPhraseParser( $escaper );
		$this->questionMarkStripLevel = $qmarkStripLevel;
		$this->classifierRepository = $classifierRepository;
		$this->namespacePrefixParser = $namespacePrefixParser;
		$this->maxQueryLen = $maxQueryLen ?: 300;
	}

	/**
	 * Reinit internal parser states
	 * @param string $rawQuery
	 */
	private function reInit( $rawQuery ) {
		$this->rawQuery = $rawQuery;
		$this->query = null;
		$this->keywordOffsetsTracker = new OffsetTracker();
		$this->offset = 0;
		$this->token = null;
		$this->lookBehind = null;
		$this->preTaggedNodes = [];
		$this->warnings = [];
		$this->queryCleanups = [];
		$this->namespaceHeader = null;
		$this->offset = 0;
	}

	/**
	 * Apply some cleanups to the input query prior to parsing it
	 * Ideally the parser should be able to handle the query without modifying it
	 * but in some cases it simply way easier to handle this this way.
	 * Cleanups applied:
	 * - Question mark stripping depending on $this->questionMarkStripLevel
	 * - gershayim quirks if $this->escaper->getLanguage() is hebrew
	 */
	private function cleanup() {
		$query = $this->rawQuery;
		$nquery = Util::stripQuestionMarks( $query, $this->questionMarkStripLevel );
		if ( $nquery !== $query ) {
			$this->queryCleanups[ParsedQuery::CLEANUP_QMARK_STRIPPING] = true;
			$query = $nquery;
		}
		if ( $this->escaper->getLanguage() === 'he' ) {
			$nquery = preg_replace( self::GERSHAYIM_REGEX, '$1\\"$2', $query );
			if ( $nquery !== $query ) {
				$this->queryCleanups[ParsedQuery::CLEANUP_GERSHAYIM_QUIRKS] = true;
				$query = $nquery;
			}
		}
		if ( strlen( $query ) > 0 && $query[0] === '~' ) {
			$query = substr( $query, 1 );
			$this->queryCleanups[ParsedQuery::TILDE_HEADER] = true;
		}
		$this->query = $query;
	}

	/**
	 * @param string $query
	 * @return \CirrusSearch\Parser\AST\ParsedQuery
	 * @throws SearchQueryParseException
	 */
	public function parse( string $query ): ParsedQuery {
		$this->reInit( $query );
		$queryLen = mb_strlen( $query );
		if ( $queryLen > self::QUERY_LEN_HARD_LIMIT ) {
			throw new SearchQueryParseException( 'cirrussearch-query-too-long',
				$queryLen, self::QUERY_LEN_HARD_LIMIT );
		}
		$this->cleanup();
		$this->parseNsHeader();
		$this->token = new Token( $this->query );
		$this->lookBehind = new Token( $this->query );

		// First parse keywords
		$nonGreedyHeaders = [];
		$greedyHeaders = [];
		$greedy = [];
		$allowingEmpty = [];
		$normalKeywords = [];
		foreach ( $this->keywordRegistry->getKeywords() as $keyword ) {
			// Parsing depends on the nature of the keyword
			// 1. non greedy query headers
			// 2. greedy query headers
			// 3. greedy
			// 4. allowed empty values (see prefer-recent)
			// 5. normal
			// FIXME: refactor this so that parsing is less dependent on keyword ordering
			// we could try to identify all keyword prefixes in a single regex like /(?<=\G|[\pZ\pC])(intitle|prefer-recent|...):/
			// and iterate over there. Currently we workaround this issue by separating keywords into categories but that is not
			// sufficient it's still dependent on ordering within a specific category.
			if ( !$keyword->greedy() && $keyword->queryHeader() ) {
				$nonGreedyHeaders[] = $keyword;
			} elseif ( $keyword->greedy() && $keyword->queryHeader() ) {
				$greedyHeaders[] = $keyword;
			} elseif ( $keyword->greedy() ) {
				$greedy[] = $keyword;
			} elseif ( $keyword->allowEmptyValue() ) {
				$allowingEmpty[] = $keyword;
			} else {
				$normalKeywords[] = $keyword;
			}
		}
		$this->parseKeywords( $nonGreedyHeaders );
		$this->parseKeywords( $greedyHeaders );
		$this->parseKeywords( $greedy );
		$this->parseKeywords( $allowingEmpty );
		$this->parseKeywords( $normalKeywords );
		$this->warnings = array_merge( $this->warnings, $this->keywordParser->getWarnings() );
		// All parsed keywords have their offsets marked in $this->keywordOffsetsTracker
		// We then reparse the query from the beginning finding holes between keyword offsets
		uasort( $this->preTaggedNodes, function ( ParsedNode $a, ParsedNode $b ) {
			if ( $a->getStartOffset() < $b->getStartOffset() ) {
				return -1;
			} else {
				// We cannot have equality here
				return 1;
			}
		} );
		reset( $this->preTaggedNodes );

		$this->checkQueryLen();
		$root = $this->expression();
		$additionalNamespaces = $this->extractRequiredNamespaces( $root );
		return new ParsedQuery( $root, $this->query, $this->rawQuery, $this->queryCleanups,
			$this->namespaceHeader, $additionalNamespaces, $this->warnings, $this->classifierRepository );
	}

	private function createClause( ParsedNode $node, $explicit = false, $occur = null ) {
		if ( $occur === null ) {
			$occur = self::DEFAULT_OCCUR;
		}
		if ( $node instanceof NegatedNode ) {
			// OR NOT is simply MUST_NOT, there's no SHOULD_NOT in lucene
			// so simply do what lucene QueryString does: force MUST_NOT whenever
			// we encounter a negated clause.
			return new BooleanClause( $node->getChild(), BooleanClause::MUST_NOT,
				$explicit || $node->getNegationType() === 'NOT' );
		}
		return new BooleanClause( $node, $occur, $explicit );
	}

	/**
	 * (([NOT] Node) (AND|OR)?)*
	 * Tries to follow behavior with backward order precedence:
	 * - A AND B OR C => MUST:A SHOULD:B SHOULD:C
	 * - A OR B AND C => SHOULD:A MUST:B MUST:C
	 *
	 * NOT is always MUST_NOT:
	 * - A OR NOT B: A -B
	 *
	 * Syntax errors fallback:
	 * - NOT: MUST:NOT
	 * - NOT NOT: MUST_NOT:NOT
	 * - NOT AND FOO: MUST_NOT:AND MUST:FOO
	 * - NOT !FOO: MUST:FOO
	 * @return ParsedNode
	 */
	private function expression() {
		$clauses = [];
		$left = null;
		// Last boolean operator seen, -1 means none
		$lastBoolType = -1;
		$explicitNegation = false;
		// TODO: simplify, this is a bit hairy
		while ( $this->nextToken() ) {
			if ( $left === null ) {
				// First iteration
				if ( !$this->isLeaf() ) {
					$left = $this->fallbackToWord( [ Token::NOT, Token::PARSED_NODE ] );
				} else {
					$left = $this->negatedLeaf();
				}
				Assert::postcondition( $left !== null, '$left must not be null' );
				continue;
			}

			$explicitNegation = false;
			// The last boolean operator seen before the last one, -1 means none
			// used to know if the node was attached explicitly by the user
			$beforeLastBoolType = $lastBoolType;
			$lastBoolType = - 1;
			switch ( $this->token->getType() ) {
				case Token::NOT:
					// NOT something
					$this->advance();
					if ( !$this->nextToken() ) {
						// NOT<EOF>
						// strategy is to simply eat the NOT token as a word query
						$node = $this->unexpectedEOF( [ Token::PARSED_NODE ] );
						if ( $left instanceof WordsQueryNode ) {
							// Collapse it to previous words
							$left = $this->mergeWords( $left, $node );
						} else {
							// or add new boolean clause
							$clauses[] = $this->createClause( $left, $beforeLastBoolType !== -1 );
							$left = $node;
						}
					} else {
						$explicitNegation = true;
						$clauses[] = $this->createClause( $left );
						$left = $this->explicitlyNegatedNode();
					}
					Assert::postcondition( $left !== null, '$left must not be null' );
					break;
				case Token::PARSED_NODE:
					// A word or a keyword
					// note: negation prefix is eaten by the tokenizer
					if ( $left instanceof WordsQueryNode
							&& $this->token->getNode() instanceof WordsQueryNode
					) {
						$lastBoolType = $beforeLastBoolType;
						$left = $this->collapseWords( $left );

					} else {
						$clauses[] = $this->createClause( $left, $explicitNegation );
						$left = $this->leaf();
					}
					Assert::postcondition( $left !== null, '$left must not be null' );
					break;
				case Token::BOOL_AND:
				case Token::BOOL_OR:
					$lastBoolType = $this->token->getType();
					$this->advance();
					if ( !$this->nextToken() ) {
						$lastBoolType = $beforeLastBoolType;
						$node = $this->unexpectedEOF( [ Token::NOT, Token::PARSED_NODE ] );
						if ( $left instanceof WordsQueryNode ) {
							// "catapult ||"
							$left = $this->mergeWords( $left, $node );
						} else {
							// "!catapult ||"
							$clauses[] = $this->createClause( $left, $beforeLastBoolType !== -1 );
							$left = $node;
						}
						Assert::postcondition( $left !== null, '$left must not be null' );
						break;
					}
					$occur = $this->boolToOccur( $lastBoolType );
					if ( $this->isLeaf() ) {
						$node = $this->negatedLeaf();
						$clauses[] = $this->createClause( $left, true, $occur );
						$left = $node;
					} else {
						$clauses[] = $this->createClause( $left );
						$left = $this->fallbackToWord( [ Token::NOT, Token::PARSED_NODE ] );
					}
					Assert::postcondition( $left !== null, '$left must not be null' );
					break;
				default:
					throw new \Exception( "BUG: unexpected token type {$this->token->getType()}" );
			}
		}

		if ( $left === null ) {
			return new EmptyQueryNode( 0, strlen( $this->query ) );
		}
		if ( $clauses !== [] ) {
			if ( $lastBoolType !== - 1 ) {
				$occur = $this->boolToOccur( $lastBoolType );
				$clauses[] = $this->createClause( $left, true, $occur );
			} else {
				$clauses[] = $this->createClause( $left, $explicitNegation );
			}
			return $this->createBoolNode( $clauses );
		} elseif ( $left instanceof NegatedNode ) {
			$clauses[] = $this->createClause( $left );
			return $this->createBoolNode( $clauses );
		}
		return $left;
	}

	/**
	 * @param BooleanClause[] $clauses
	 * @return ParsedBooleanNode
	 */
	private function createBoolNode( array $clauses ) {
		$end = end( $clauses )->getNode()->getEndOffset();
		$start = reset( $clauses )->getNode()->getStartOffset();
		return new ParsedBooleanNode( $start, $end, $clauses );
	}

	/**
	 * Collapse the current token with $left.
	 * @param WordsQueryNode $left
	 * @return WordsQueryNode
	 */
	private function collapseWords( WordsQueryNode $left ) {
		$start = $left->getStartOffset();
		$end = $this->token->getEnd();
		$word = new WordsQueryNode( $start, $end,
			$this->escaper->unescape( substr( $this->query, $start, $end - $start ) ) );
		$this->advance();
		return $word;
	}

	private function mergeWords( WordsQueryNode $left, WordsQueryNode $right ) {
		$start = $left->getStartOffset();
		$end = $right->getEndOffset();
		return new WordsQueryNode( $start, $end,
			$this->escaper->unescape( substr( $this->query, $start, $end - $start ) ) );
	}

	/**
	 * @param int[] $expected token types
	 * @return WordsQueryNode
	 */
	private function fallbackToWord( array $expected ) {
		$this->warnings[] = new ParseWarning(
			'cirrussearch-parse-error-unexpected-token',
			$this->token->getStart(),
			Token::getTypeLabels( $expected ),
			Token::getTypeLabel( $this->token->getType() )
		);
		$word = new WordsQueryNode( $this->token->getStart(), $this->token->getEnd(),
			$this->token->getImage() );
		$this->advance();
		return $word;
	}

	/**
	 * @param int[] $expected token types
	 * @return WordsQueryNode
	 */
	private function unexpectedEOF( array $expected ) {
		$this->warnings[] = new ParseWarning(
			'cirrussearch-parse-error-unexpected-end',
			strlen( $this->query ),
			Token::getTypeLabels( $expected ),
			Token::getTypeLabel( $this->token->getType() )
		);
		return new WordsQueryNode( $this->lookBehind->getStart(), $this->lookBehind->getEnd(),
			$this->lookBehind->getImage() );
	}

	private function negatedLeaf() {
		if ( $this->token->getType() === Token::NOT ) {
			$this->advance();
			if ( !$this->nextToken() ) {
				return $this->unexpectedEOF( [ Token::PARSED_NODE ] );
			}
			return $this->explicitlyNegatedNode();
		}
		return $this->leaf();
	}

	private function leaf() {
		$node = $this->token->getNode();
		$this->advance();
		return $node;
	}

	/**
	 * (word|phrase|keyword|wildcard|fuzzy)
	 *
	 * Warnings on :
	 *
	 * !(word|phrase|keyword|special_word) => double negation dropped
	 * (AND|OR) => NOT("AND|OR")
	 * EOF => "NOT"
	 *
	 * @return ParsedNode
	 */
	private function explicitlyNegatedNode() {
		if ( $this->token->getType() === Token::PARSED_NODE ) {
			$node = $this->leaf();
			if ( $node instanceof NegatedNode ) {
				$this->warnings[] = new ParseWarning( 'cirrussearch-parse-error-double-negation',
					$node->getStartOffset() );
				$node = $node->getChild();
			} else {
				$node = new NegatedNode( $this->lookBehind->getStart(), $node->getEndOffset(),
					// @phan-suppress-next-line PhanTypeMismatchArgumentNullable $node not null after getType
					$node, $this->lookBehind->getImage() );
			}
		} else {
			$node = $this->fallbackToWord( [ Token::PARSED_NODE ] );
		}
		return $node;
	}

	/**
	 * @return bool true if it's a simple word token
	 */
	private function isLeaf() {
		return $this->token->getType() === Token::PARSED_NODE
			|| $this->token->getType() === Token::NOT;
	}

	/**
	 * @param KeywordFeature[] $keywords
	 */
	private function parseKeywords( array $keywords ) {
		foreach ( $keywords as $kw ) {
			$parsedKeywords =
				$this->keywordParser->parse( $this->query, $kw, $this->keywordOffsetsTracker, $this->offset );
			$this->keywordOffsetsTracker->appendNodes( $parsedKeywords );
			foreach ( $parsedKeywords as $keyword ) {
				$this->preTaggedNodes[] = $keyword;
			}
		}
	}

	/**
	 * @return bool
	 */
	private function nextToken() {
		Assert::precondition( $this->token->getStart() < $this->offset,
			'You should not call nextToken() twice on the same offsets' );
		$this->token->copyTo( $this->lookBehind );
		$this->token->reset();
		$nextPretagged = current( $this->preTaggedNodes );
		$queryLen = strlen( $this->query );
		$maxOffset = $nextPretagged !== false ?
			$nextPretagged->getStartOffset() : $queryLen;

		$this->consumeWS();

		if ( $this->offset >= $queryLen ) {
			$this->token->eof();
			return false;
		}

		if ( $nextPretagged !== false && $this->offset === $nextPretagged->getStartOffset() ) {
			$this->token->node( $nextPretagged );
			return true;
		}

		Assert::precondition( $this->offset < $maxOffset, '$this->offset < $maxOffset' );
		if ( $this->consumeBoolOp() ) {
			return true;
		}

		if ( $this->consumePhrase( $maxOffset ) ) {
			return true;
		}

		if ( $this->consumeWord() ) {
			return true;
		}

		if ( $this->consumeUnbalancedPhrase( $maxOffset ) ) {
			$this->warnings[] = new ParseWarning( "cirrus-parse-error-unbalanced-phrase", $this->token->getStart() );
			// this is theorically the only remaining option (unbalanced phrase query)
			// if not it means the above is broken and needs a fix
			return true;
		}
		throw new \RuntimeException( "BUG: cannot consume query at offset $this->offset (need to go to $maxOffset)" );
	}

	/**
	 * @return bool
	 */
	private function consumeWS() {
		$matches = [];
		if ( preg_match( self::WHITESPACE_REGEX, $this->query, $matches, 0, $this->offset ) === 1 ) {
			$this->offset += strlen( $matches[0] );
			return true;
		}
		return false;
	}

	/**
	 * Consume an explicit boolean operator (and/or/not)
	 * @return bool true if consumed, false otherwise.
	 */
	private function consumeBoolOp() {
		$match = [];
		if ( preg_match( self::EXPLICIT_BOOLEAN_OPERATOR, $this->query, $match, 0, $this->offset ) ) {
			// Check captured backward so no need to check that the group actually matched
			if ( isset( $match['NOT'] ) ) {
				$this->token->setType( Token::NOT, $this->offset, $this->offset + strlen( $match['NOT'] ) );
			} elseif ( isset( $match['OR'] ) ) {
				$this->token->setType( Token::BOOL_OR, $this->offset, $this->offset + strlen( $match['OR'] ) );
			} else {
				$this->token->setType( Token::BOOL_AND, $this->offset,
				$this->offset + strlen( $match['AND'] ) );
			}
			return true;
		}
		return false;
	}

	/**
	 * @param int $maxOffset
	 * @return bool true if consumed, false otherwise.
	 */
	private function consumePhrase( $maxOffset ) {
		$node = $this->phraseQueryParser->parse( $this->query, $this->offset, $maxOffset );
		if ( $node !== null ) {
			$this->token->node( $node );
			return true;
		}
		return false;
	}

	/**
	 * @return bool
	 */
	private function consumeWord() {
		$node = $this->nonPhraseParser->parse( $this->query, $this->offset );
		if ( $node !== null ) {
			$this->token->node( $node );
			return true;
		}
		return false;
	}

	/**
	 * @param int $maxOffset
	 * @return bool
	 */
	private function consumeUnbalancedPhrase( $maxOffset ) {
		$matches = [];
		if ( preg_match( PhraseQueryParser::PHRASE_START, $this->query, $matches, 0, $this->offset ) === 1 ) {
			$negated = isset( $matches['negate'] ) && strlen( $matches['negate'] ) > 0 ? $matches['negate'] : '';
			$wholeStart = $this->offset;
			$phraseStart = $wholeStart + strlen( $negated );
			$innerStart = $phraseStart + strlen( '"' );
			Assert::invariant( $wholeStart <= $maxOffset, '$start <= $to' );
			$phrase = $maxOffset > $innerStart ? substr( $this->query, $innerStart, $maxOffset - $innerStart ) : "";
			$node = PhraseQueryNode::unbalanced( $phraseStart, $maxOffset, $phrase );
			if ( $negated !== '' ) {
				$node = new NegatedNode( $wholeStart, $maxOffset, $node, $negated );
			}
			$this->token->node( $node );
			return true;
		}
		return false;
	}

	/**
	 * advance current offset to the end of the current token
	 */
	private function advance() {
		$pretagged = current( $this->preTaggedNodes );
		if ( $pretagged !== false && $this->token->getStart() === $pretagged->getStartOffset() ) {
			next( $this->preTaggedNodes );
		}
		$this->offset = $this->token->getEnd();
	}

	/**
	 * @param int $boolOperator
	 * @return string
	 */
	private function boolToOccur( $boolOperator ) {
		return $boolOperator === Token::BOOL_OR ? BooleanClause::SHOULD : BooleanClause::MUST;
	}

	/**
	 * @param ParsedNode $root
	 * @return array|string array of additional namespaces, 'all' for everything
	 */
	private function extractRequiredNamespaces( ParsedNode $root ) {
		$visitor = new class( [], [ PrefixFeature::class ] ) extends KeywordNodeVisitor {
			/** @var string|int[] */
			public $total = [];

			/**
			 * @param KeywordFeatureNode $node
			 */
			public function doVisitKeyword( KeywordFeatureNode $node ) {
				if ( $this->total === 'all' ) {
					return;
				}

				Assert::parameter( $node->getKeyword() instanceof PrefixFeature, '$node', 'must be parsed from PrefixFeature' );
				// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
				$additional = $node->getParsedValue()[PrefixFeature::PARSED_NAMESPACES];
				if ( $additional === 'all' ) {
					$this->total = 'all';
					return;
				}
				Assert::precondition( is_array( $additional ),
					'PrefixFeature::PARSED_NAMESPACES key must point to an array or "all"' );
				$this->total = array_merge( $this->total, array_filter( $additional, function ( $v ) {
					return !in_array( $v, $this->total );
				} ) );
			}
		};
		$root->accept( $visitor );
		return $visitor->total;
	}

	/**
	 * Inspect $this->query to see if it mentions a namespace in the first few chars
	 * If yes $this->namespaceHeader will be set and this->offset will be advanced
	 * to the actual start of the query.
	 */
	private function parseNsHeader() {
		Assert::precondition( $this->offset === 0, 'ns header must be the first parsed bits or ' .
			'you must properly handle offset in this method.' );
		$queryAndNs = $this->namespacePrefixParser->parse( $this->query );
		if ( $queryAndNs !== false ) {
			Assert::postcondition( count( $queryAndNs ) === 2,
				'\SearchEngine::parseNamespacePrefixes() must return false or a 2 elements array' );
			$queryOffset = strlen( $this->query ) - strlen( $queryAndNs[0] );
			if ( $queryAndNs[1] ) {
				Assert::postcondition( is_array( $queryAndNs[1] ) && count( $queryAndNs[1] ) === 1,
						'\SearchEngine::parseNamespacePrefixes() should return an array whose second ' .
						'element is falsy or an array of size 1' );
				$this->namespaceHeader = new NamespaceHeaderNode( $this->offset, $queryOffset, reset( $queryAndNs[1] ) );
			} else {
				$this->namespaceHeader = new NamespaceHeaderNode( $this->offset, $queryOffset, 'all' );
			}
			$this->offset = $queryOffset;
		}
	}

	/**
	 * Check the length of the query and throws SearchQueryParseException
	 * if it's more than what we allow.
	 *
	 * @throws SearchQueryParseException
	 */
	private function checkQueryLen(): void {
		Assert::precondition( $this->query !== null, "Query must be set" );
		$maxLen = $this->maxQueryLen;
		// don't limit incategory
		foreach ( $this->preTaggedNodes as $n ) {
			if ( $n instanceof KeywordFeatureNode && $this->unlimitedKeywords( $n->getKey() ) ) {
				$maxLen += mb_strlen( substr( $this->query, $n->getStartOffset(), $n->getEndOffset() ) );
			}
		}
		$queryLen = mb_strlen( $this->query );
		if ( $queryLen > $maxLen ) {
			throw new SearchQueryParseException( 'cirrussearch-query-too-long',
				$queryLen, $maxLen );
		}
	}

	/**
	 * @param string $keyword
	 * @return bool true if this keyword name should not be taken into account
	 * when calculating the query length
	 */
	private function unlimitedKeywords( string $keyword ): bool {
		return self::UNLIMITED_KEYWORDS[$keyword] ?? false;
	}
}
