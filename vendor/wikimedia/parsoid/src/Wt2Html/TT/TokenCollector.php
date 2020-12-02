<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Wt2Html\TT;

use Wikimedia\Assert\Assert;
use Wikimedia\Parsoid\Tokens\EndTagTk;
use Wikimedia\Parsoid\Tokens\EOFTk;
use Wikimedia\Parsoid\Tokens\KV;
use Wikimedia\Parsoid\Tokens\SelfclosingTagTk;
use Wikimedia\Parsoid\Tokens\SourceRange;
use Wikimedia\Parsoid\Tokens\TagTk;
use Wikimedia\Parsoid\Tokens\Token;
use Wikimedia\Parsoid\Utils\PHPUtils;
use Wikimedia\Parsoid\Wt2Html\TokenTransformManager;

/**
 * Small utility class that encapsulates the common 'collect all tokens
 * starting from a token of type x until token of type y or (optionally) the
 * end-of-input'. Only supported for synchronous in-order transformation
 * stages (SyncTokenTransformManager), as async out-of-order expansions
 * would wreak havoc with this kind of collector.
 */
abstract class TokenCollector extends TokenHandler {
	protected $onAnyEnabled;
	protected $scopeStack;

	/**
	 * TokenCollector constructor.
	 * @param TokenTransformManager $manager manager enviroment
	 * @param array $options various configuration options
	 */
	public function __construct( TokenTransformManager $manager, array $options ) {
		parent::__construct( $manager, $options );
		$this->onAnyEnabled = false;
		$this->scopeStack = [];
	}

	/**
	 * Token type to register for ('tag', 'text' etc)
	 * @return string
	 */
	abstract protected function type(): string;

	/**
	 * (optional, only for token type 'tag'): tag name.
	 * @return string
	 */
	abstract protected function name(): string;

	/**
	 * Match the 'end' tokens as closing tag as well (accept unclosed sections).
	 * @return bool
	 */
	abstract protected function toEnd(): bool;

	/**
	 * FIXME: Document this
	 * @return bool
	 */
	abstract protected function ackEnd(): bool;

	/**
	 * FIXME: Document this
	 * @param array $array
	 * @return array
	 */
	abstract protected function transformation( array $array ): array;

	/**
	 * Handle the delimiter token.
	 * XXX: Adjust to sync phase callback when that is modified!
	 * @param Token $token
	 * @return array
	 */
	private function onDelimiterToken( Token $token ) : array {
		$haveOpenTag = count( $this->scopeStack ) > 0;
		if ( $token instanceof TagTk ) {
			if ( count( $this->scopeStack ) === 0 ) {
				$this->onAnyEnabled = true;
				// Set up transforms
				$this->manager->env->log( 'debug', 'starting collection on ', $token );
			}

			// Push a new scope
			$newScope = [];
			$this->scopeStack[] = &$newScope;
			$newScope[] = $token;

			return [];
		} elseif ( $token instanceof SelfclosingTagTk ) {
			// We need to handle <ref /> for example, so call the handler.
			return $this->transformation( [ $token, $token ] );
		} elseif ( $haveOpenTag ) {
			// EOFTk or EndTagTk
			$this->manager->env->log( 'debug', 'finishing collection on ', $token );

			// Pop top scope and push token onto it
			$activeTokens = array_pop( $this->scopeStack );
			$activeTokens[] = $token;

			// clean up
			if ( count( $this->scopeStack ) === 0 || $token instanceof EOFTk ) {
				$this->onAnyEnabled = false;
			}

			if ( $token instanceof EndTagTk ) {
				// Transformation can be either sync or async, but receives all collected
				// tokens instead of a single token.
				return $this->transformation( $activeTokens );
				// XXX sync version: return tokens
			} else {
				// EOF -- collapse stack!
				$allToks = [];
				for ( $i = 0,  $n = count( $this->scopeStack );  $i < $n;  $i++ ) {
					$allToks = array_merge( $allToks, $this->scopeStack[$i] );
				}
				$allToks = array_merge( $allToks, $activeTokens );

				$res = $this->toEnd() ? $this->transformation( $allToks ) : [ 'tokens' => $allToks ];
				if ( isset( $res['tokens'] ) ) {
					if ( count( $res['tokens'] )
						&& !( PHPUtils::lastItem( $res['tokens'] ) instanceof EOFTk )
					) {
						$this->manager->env->log( 'error', $this::name(), 'handler dropped the EOFTk!' );

						// preserve the EOFTk
						$res['tokens'][] = $token;
					}
				}

				return $res;
			}
		} else {
			// EndTagTk should be the only one that can reach here.
			Assert::invariant( $token instanceof EndTagTk, 'Expected an end tag.' );
			if ( $this->ackEnd() ) {
				return $this->transformation( [ $token ] );
			} else {
				// An unbalanced end tag. Ignore it.
				return [ 'tokens' => [ $token ] ];
			}
		}
	}

	/**
	 * Handle 'any' token in between delimiter tokens. Activated when
	 * encountering the delimiter token, and collects all tokens until the end
	 * token is reached.
	 * @param Token|string $token
	 * @return array
	 */
	private function onAnyToken( $token ) : array {
		// Simply collect anything ordinary in between
		$this->scopeStack[count( $this->scopeStack ) - 1][] = $token;
		return [];
	}

	/**
	 * This helper function will build a meta token in the right way for these tags.
	 * @param TokenTransformManager $manager
	 * @param string $tokenName
	 * @param bool $isEnd
	 * @param SourceRange $tsr
	 * @param string|null $src
	 * @return SelfclosingTagTk
	 */
	public static function buildMetaToken(
		TokenTransformManager $manager, string $tokenName, bool $isEnd,
		SourceRange $tsr, ?string $src
	) : SelfclosingTagTk {
		if ( $isEnd ) {
			$tokenName .= '/End';
		}

		$srcText = $manager->getFrame()->getSrcText();
		$newSrc = $tsr->substr( $srcText );

		return new SelfclosingTagTk( 'meta',
			[ new KV( 'typeof', $tokenName ) ],
			(object)[ 'tsr' => $tsr, 'src' => $newSrc ]
		);
	}

	/**
	 * @param TokenTransformManager $manager
	 * @param string $tokenName
	 * @param Token $startDelim
	 * @param Token|null $endDelim
	 * @return SelfclosingTagTk
	 */
	protected static function buildStrippedMetaToken(
		TokenTransformManager $manager, string $tokenName, Token $startDelim, ?Token $endDelim
	) : SelfclosingTagTk {
		$da = $startDelim->dataAttribs;
		$tsr0 = $da ? $da->tsr : null;
		$t0 = $tsr0 ? $tsr0->start : null;
		$t1 = null;

		if ( $endDelim !== null ) {
			$da = $endDelim->dataAttribs ?? null;
			$tsr1 = $da ? $da->tsr : null;
			$t1 = $tsr1 ? $tsr1->end : null;
		} else {
			$t1 = strlen( $manager->getFrame()->getSrcText() );
		}

		return self::buildMetaToken( $manager, $tokenName, false, new SourceRange( $t0, $t1 ), '' );
	}

	/**
	 * @inheritDoc
	 */
	public function onTag( Token $token ) {
		return $token->getName() === $this::name() ? $this->onDelimiterToken( $token ) : $token;
	}

	/**
	 * @inheritDoc
	 */
	public function onEnd( EOFTk $token ) {
		return $this->onAnyEnabled ? $this->onDelimiterToken( $token ) : $token;
	}

	/**
	 * @inheritDoc
	 */
	public function onAny( $token ) {
		return $this->onAnyToken( $token );
	}
}
