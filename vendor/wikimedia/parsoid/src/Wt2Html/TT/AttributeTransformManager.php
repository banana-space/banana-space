<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Wt2Html\TT;

use Wikimedia\Parsoid\Tokens\KV;
use Wikimedia\Parsoid\Utils\TokenUtils;
use Wikimedia\Parsoid\Wt2Html\Frame;

/**
 * Utility transformation manager for expanding attributes
 * whose keys and/or values are not plain strings.
 */
class AttributeTransformManager {
	/**
	 * @var array
	 */
	private $options;

	/**
	 * @var Frame
	 */
	private $frame;

	/**
	 * @param Frame $frame
	 * @param array $options
	 *  - bool inTemplate (reqd) Is this being invoked while processing a template?
	 *  - bool expandTemplates (reqd) Should we expand templates encountered here?
	 */
	public function __construct( Frame $frame, array $options ) {
		$this->options = $options;
		$this->frame = $frame;
	}

	/**
	 * @param KV $cur
	 * @return KV
	 */
	private function processOne( KV $cur ): KV {
		$k = $cur->k;
		$v = $cur->v;
		if ( $cur->v === null ) {
			$cur->v = $v = '';
		}

		// fast path for string-only attributes
		if ( is_string( $k ) && is_string( $v ) ) {
			// PERF-FIXME: Maybe return $cur itself?
			return new KV( $k, $v, $cur->srcOffsets );
		}

		$n = is_array( $v ) ? count( $v ) : -1;
		if ( $n > 1 || ( $n === 1 && !is_string( $v[0] ) ) ) {
			// transform the value
			$tokens = $this->frame->expand( $v, [
				'expandTemplates' => $this->options['expandTemplates'],
				'inTemplate' => $this->options['inTemplate'],
				'type' => 'tokens/x-mediawiki/expanded',
				'srcOffsets' => $cur->srcOffsets->value,
			] );
			$v = TokenUtils::stripEOFTkfromTokens( $tokens );
		}

		$n = is_array( $k ) ? count( $k ) : -1;
		if ( $n > 1 || ( $n === 1 && !is_string( $k[0] ) ) ) {
			// transform the key
			$tokens = $this->frame->expand( $k, [
				'expandTemplates' => $this->options['expandTemplates'],
				'inTemplate' => $this->options['inTemplate'],
				'type' => 'tokens/x-mediawiki/expanded',
				'srcOffsets' => $cur->srcOffsets->key,
			] );
			$k = TokenUtils::stripEOFTkfromTokens( $tokens );
		}

		return new KV( $k, $v, $cur->srcOffsets );
	}

	/**
	 * Expand both key and values of all key/value pairs. Used for generic
	 * (non-template) tokens in the AttributeExpander handler, which runs after
	 * templates are already expanded.
	 *
	 * @param KV[] $attributes
	 * @return KV[] expanded attributes
	 */
	public function process( array $attributes ): array {
		// Transform each argument (key and value).
		return array_map( [ $this, 'processOne' ], $attributes );
	}
}
