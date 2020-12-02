<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Wt2Html\TT;

use Wikimedia\Parsoid\Tokens\EndTagTk;
use Wikimedia\Parsoid\Tokens\KV;
use Wikimedia\Parsoid\Tokens\SelfclosingTagTk;
use Wikimedia\Parsoid\Tokens\Token;
use Wikimedia\Parsoid\Utils\TokenUtils;
use Wikimedia\Parsoid\Wt2Html\TokenTransformManager;

/**
 * OnlyInclude sadly forces synchronous template processing, as it needs to
 * hold onto all tokens in case an onlyinclude block is encountered later.
 * This can fortunately be worked around by caching the tokens after
 * onlyinclude processing (which is a good idea anyway).
 */
class OnlyInclude extends TokenHandler {
	/** @var array */
	private $accum = [];

	/** @var bool */
	private $inOnlyInclude = false;

	/** @var bool */
	private $foundOnlyInclude = false;

	/**
	 * OnlyInclude constructor.
	 * @param TokenTransformManager $manager manager environment
	 * @param array $options options
	 */
	public function __construct( TokenTransformManager $manager, array $options ) {
		parent::__construct( $manager, $options );
		if ( empty( $this->options['isInclude'] ) ) {
			$this->accum = [];
			$this->inOnlyInclude = false;
			$this->foundOnlyInclude = false;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onAny( $token ) {
		return !empty( $this->options['isInclude'] ) ? $this->onAnyInclude( $token ) : $token;
	}

	/**
	 * @inheritDoc
	 */
	public function onTag( Token $token ) {
		return empty( $this->options['isInclude'] ) && $token->getName() === 'onlyinclude' ?
			$this->onOnlyInclude( $token ) : $token;
	}

	/**
	 * @param Token $token
	 * @return array
	 */
	private function onOnlyInclude( Token $token ): array {
		$tsr = $token->dataAttribs->tsr;
		$src = empty( $this->options['inTemplate'] )
			? $token->getWTSource( $this->manager->getFrame() )
			: null;
		$attribs = [
			new KV( 'typeof', 'mw:Includes/OnlyInclude' .
				( ( $token instanceof EndTagTk ) ? '/End' : '' ) )
		];
		$meta = new SelfclosingTagTk( 'meta', $attribs, (object)[ 'tsr' => $tsr, 'src' => $src ] );
		return [ 'tokens' => [ $meta ] ];
	}

	/**
	 * @param Token|array $token
	 * @return array
	 */
	private function onAnyInclude( $token ) {
		$tagName = null;
		$isTag = null;
		$meta = null;

		$tc = TokenUtils::getTokenType( $token );
		if ( $tc === 'EOFTk' ) {
			$this->inOnlyInclude = false;
			if ( count( $this->accum ) && !$this->foundOnlyInclude ) {
				$res = $this->accum;
				$res[] = $token;
				$this->accum = [];
				return [ 'tokens' => $res ];
			} else {
				$this->foundOnlyInclude = false;
				$this->accum = [];
				return [ 'tokens' => [ $token ] ];
			}
		}

		$isTag = $tc === 'TagTk' || $tc === 'EndTagTk' || $tc === 'SelfclosingTagTk';
		if ( $isTag ) {
			switch ( $token->getName() ) {
				case 'onlyinclude':
					$tagName = 'mw:Includes/OnlyInclude';
					break;
				case 'includeonly':
					$tagName = 'mw:Includes/IncludeOnly';
					break;
				case 'noinclude':
					$tagName = 'mw:Includes/NoInclude';
					break;
			}
		}

		if ( $isTag && $token->getName() === 'onlyinclude' ) {
			if ( !$this->inOnlyInclude ) {
				$this->foundOnlyInclude = true;
				$this->inOnlyInclude = true;
				// wrap collected tokens into meta tag for round-tripping
				$meta = TokenCollector::buildMetaToken( $this->manager, $tagName,
					$tc === 'EndTagTk', $token->dataAttribs->tsr ?? null, '' );
			} else {
				$this->inOnlyInclude = false;
				$meta = TokenCollector::buildMetaToken( $this->manager, $tagName,
					$tc === 'EndTagTk', $token->dataAttribs->tsr ?? null, '' );
			}
			return [ 'tokens' => [ $meta ] ];
		} else {
			if ( $this->inOnlyInclude ) {
				return [ 'tokens' => [ $token ] ];
			} else {
				$this->accum[] = $token;
				return [];
			}
		}
	}
}
